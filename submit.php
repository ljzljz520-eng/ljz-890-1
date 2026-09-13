<?php
require_once __DIR__ . '/functions.php';

// 选长辈
$elders = $pdo->query('SELECT id, name FROM elders ORDER BY birth_year IS NULL, birth_year DESC')->fetchAll();
$presetElder = (int)($_GET['elder_id'] ?? 0);

$errors = [];
$old = ['elder_id' => $presetElder, 'contributor' => '', 'contact' => '', 'relation' => '',
        'title' => '', 'story_year' => '', 'location' => '', 'content' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    // 蜜罐反垃圾：正常用户看不到这个字段
    if (($_POST['website'] ?? '') !== '') {
        redirect('submit_done.php');
    }

    foreach (['contributor', 'contact', 'relation', 'title', 'location', 'content'] as $k) {
        $old[$k] = trim($_POST[$k] ?? '');
    }
    $old['elder_id'] = (int)($_POST['elder_id'] ?? 0);
    $old['story_year'] = trim($_POST['story_year'] ?? '');

    if ($old['title'] === '') {
        $errors[] = '请填写故事标题';
    }
    if ($old['content'] === '') {
        $errors[] = '请填写故事内容';
    }
    $yearVal = null;
    if ($old['story_year'] !== '') {
        if (!preg_match('/^\d{4}$/', $old['story_year'])) {
            $errors[] = '年份请用 4 位数字，如 1958';
        } else {
            $y = (int)$old['story_year'];
            if ($y < 1800 || $y > (int)date('Y') + 1) {
                $errors[] = '年份超出合理范围';
            }
            $yearVal = $y;
        }
    }

    // 处理照片（可选，1 张）
    $savedPhoto = '';
    $savedMime = '';
    if (!empty($_FILES['photo']['name'])) {
        $up = save_upload($_FILES['photo'], 'submissions', ALLOWED_IMAGE, MAX_IMAGE_SIZE);
        if (!$up['ok']) {
            $errors[] = $up['error'];
        } else {
            $savedPhoto = $up['file'];
            $savedMime = $up['mime'] ?? '';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare("
            INSERT INTO submissions
              (elder_id, contributor, contact, relation, title, story_year, location, content,
               photo_file, photo_mime, photo_caption, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?, 'pending')
        ");
        $stmt->execute([
            $old['elder_id'] ?: null,
            $old['contributor'],
            $old['contact'],
            $old['relation'],
            $old['title'],
            $yearVal,
            $old['location'],
            $old['content'],
            $savedPhoto,
            $savedMime,
            trim($_POST['photo_caption'] ?? ''),
        ]);
        redirect('submit_done.php');
    }

    // 出错时清理已上传的临时文件
    if ($savedPhoto) {
        delete_storage_file('submissions', $savedPhoto);
    }
}

$pageTitle = '补充故事';
$page = 'submit.php';
require __DIR__ . '/parts/header.php';
?>
<div class="page-head">
  <h1>为家族记忆添一块拼图</h1>
  <p class="muted">提交后会进入管理员的审核队列，审核通过即可在网站上展示。</p>
</div>

<?php if ($errors): ?>
  <div class="flash flash-error">
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form form-wide">
  <?= csrf_field() ?>
  <!-- 蜜罐 -->
  <input type="text" name="website" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">

  <div class="form-grid">
    <label>故事属于哪位长辈？
      <select name="elder_id">
        <option value="0">— 请选择（可选，暂不确定可留空）—</option>
        <?php foreach ($elders as $e): ?>
          <option value="<?= (int)$e['id'] ?>" <?= $old['elder_id'] === (int)$e['id'] ? 'selected' : '' ?>><?= h($e['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>故事标题 <span class="req">*</span>
      <input type="text" name="title" required maxlength="120" value="<?= h($old['title']) ?>" placeholder="如：爷爷在生产队的那一年">
    </label>

    <label>您的姓名
      <input type="text" name="contributor" maxlength="60" value="<?= h($old['contributor']) ?>" placeholder="方便管理员与您联系">
    </label>

    <label>与长辈的关系
      <input type="text" name="relation" maxlength="60" value="<?= h($old['relation']) ?>" placeholder="如：长孙、外甥女">
    </label>

    <label>联系方式（微信 / 电话 / 邮箱，不公开）
      <input type="text" name="contact" maxlength="120" value="<?= h($old['contact']) ?>">
    </label>

    <label>故事发生年份
      <input type="text" name="story_year" inputmode="numeric" pattern="\d{4}" maxlength="4"
             value="<?= h($old['story_year']) ?>" placeholder="如 1958（不确定可留空）">
    </label>

    <label>地点
      <input type="text" name="location" maxlength="120" value="<?= h($old['location']) ?>" placeholder="如：江苏盐城 · 老家村口">
    </label>

    <label>附一张老照片（可选，≤8MB）
      <input type="file" name="photo" accept="image/*">
    </label>

    <label class="span-2">照片说明
      <input type="text" name="photo_caption" maxlength="200" placeholder="如：1972 年全家福，后排左二是爷爷">
    </label>
  </div>

  <label>故事内容 <span class="req">*</span>
    <textarea name="content" rows="10" required placeholder="请把您记得的经过写下来：时间、地点、人物、说过的话……越具体越好"><?= h($old['content']) ?></textarea>
  </label>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">提交审核</button>
    <p class="muted small">提交内容需经家族管理员审核，不会立即公开。</p>
  </div>
</form>

<?php require __DIR__ . '/parts/footer.php'; ?>
