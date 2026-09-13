<?php
require_once __DIR__ . '/../functions.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$presetElder = (int)($_GET['elder_id'] ?? 0);
$isEdit = $id > 0;

$story = [
    'elder_id' => $presetElder, 'title' => '', 'story_year' => '', 'location' => '',
    'era' => '', 'transcript' => '', 'audio_file' => '', 'audio_mime' => '',
    'visibility' => 'public', 'status' => 'published',
];
if ($isEdit) {
    $st = $pdo->prepare('SELECT * FROM stories WHERE id = ?');
    $st->execute([$id]);
    $story = $st->fetch();
    if (!$story) {
        set_flash('error', '故事不存在');
        redirect('admin/stories.php');
    }
}

$elders = $pdo->query('SELECT id, name FROM elders ORDER BY birth_year IS NULL, birth_year DESC')->fetchAll();
if (!$elders) {
    set_flash('error', '请先录入至少一位长辈，再添加故事。');
    redirect('admin/elder_edit.php');
}

/* ---------- 照片：更新说明 / 删除 ---------- */
if ($isEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['subaction'] ?? '') === 'photo') {
    check_csrf();
    $pid = (int)($_POST['photo_id'] ?? 0);
    $ps = $pdo->prepare('SELECT file_name FROM photos WHERE id = ? AND story_id = ?');
    $ps->execute([$pid, $id]);
    $file = $ps->fetchColumn();
    if ($file) {
        if (($_POST['op'] ?? '') === 'delete') {
            $pdo->prepare('DELETE FROM photos WHERE id = ?')->execute([$pid]);
            delete_storage_file('photos', $file);
            set_flash('success', '照片已删除');
        } else {
            $pdo->prepare('UPDATE photos SET caption = ? WHERE id = ?')
                ->execute([trim($_POST['caption'] ?? ''), $pid]);
            set_flash('success', '照片说明已更新');
        }
    }
    redirect('admin/story_edit.php?id=' . $id);
}

/* ---------- 保存故事 ---------- */
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['subaction'] ?? '') !== 'photo') {
    check_csrf();
    foreach (['title', 'location', 'era', 'transcript'] as $k) {
        $story[$k] = trim($_POST[$k] ?? '');
    }
    $story['elder_id'] = (int)($_POST['elder_id'] ?? 0);
    $story['story_year'] = trim($_POST['story_year'] ?? '');
    $story['visibility'] = ($_POST['visibility'] ?? 'public') === 'family' ? 'family' : 'public';
    $story['status'] = ($_POST['status'] ?? 'published') === 'archived' ? 'archived' : 'published';

    if (!$story['elder_id']) {
        $errors[] = '请选择讲述长辈';
    } else {
        $ex = $pdo->prepare('SELECT 1 FROM elders WHERE id = ?');
        $ex->execute([$story['elder_id']]);
        if (!$ex->fetchColumn()) {
            $errors[] = '所选长辈不存在';
        }
    }
    if ($story['title'] === '') {
        $errors[] = '标题必填';
    }
    if ($story['story_year'] !== '' && preg_match('/^\d{4}$/', $story['story_year'])) {
        $story['story_year'] = (int)$story['story_year'];
    } else {
        $story['story_year'] = null;
        if (trim($_POST['story_year'] ?? '') !== '') {
            $errors[] = '年份需为 4 位数字';
        }
    }

    // 录音
    $newAudio = '';
    if (!empty($_FILES['audio']['name'])) {
        $up = save_upload($_FILES['audio'], 'audio', ALLOWED_AUDIO, MAX_AUDIO_SIZE);
        if (!$up['ok']) {
            $errors[] = '录音：' . $up['error'];
        } else {
            $newAudio = $up['file'];
            $newAudioMime = $up['mime'] ?? 'audio/mpeg';
        }
    }

    // 多张照片
    $newPhotos = [];
    foreach (normalize_file_array('photos') as $f) {
        $up = save_upload($f, 'photos', ALLOWED_IMAGE, MAX_IMAGE_SIZE);
        if (!$up['ok']) {
            $errors[] = '照片「' . $f['name'] . '」：' . $up['error'];
            break;
        }
        $newPhotos[] = $up;
    }

    if (!$errors) {
        $uid = current_user()['id'] ?? null;
        if ($isEdit) {
            $sql = 'UPDATE stories SET elder_id=?, title=?, story_year=?, location=?, era=?,
                    transcript=?, visibility=?, status=?, updated_at=datetime(\'now\',\'localtime\')';
            $params = [$story['elder_id'], $story['title'], $story['story_year'], $story['location'],
                       $story['era'], $story['transcript'], $story['visibility'], $story['status']];
            if ($newAudio) {
                $sql .= ', audio_file=?, audio_mime=?';
                $params[] = $newAudio;
                $params[] = $newAudioMime;
            }
            $sql .= ' WHERE id=?';
            $params[] = $id;
            $pdo->prepare($sql)->execute($params);
            if ($newAudio && !empty($story['audio_file'])) {
                delete_storage_file('audio', $story['audio_file']);
            }
            set_flash('success', '故事已保存');
        } else {
            $pdo->prepare("
                INSERT INTO stories (elder_id, title, story_year, location, era, transcript,
                                     audio_file, audio_mime, visibility, status, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ")->execute([
                $story['elder_id'], $story['title'], $story['story_year'], $story['location'],
                $story['era'], $story['transcript'],
                $newAudio, $newAudioMime ?? '', $story['visibility'], $story['status'], $uid,
            ]);
            $id = (int)$pdo->lastInsertId();
            set_flash('success', '故事已创建');
        }

        // 保存新照片
        foreach ($newPhotos as $i => $p) {
            $pdo->prepare('INSERT INTO photos (story_id, file_name, mime, sort_order) VALUES (?,?,?,?)')
                ->execute([$id, $p['file'], $p['mime'] ?? '', $i]);
        }
        redirect('admin/story_edit.php?id=' . $id);
    }

    // 出错清理已传文件
    if ($newAudio) {
        delete_storage_file('audio', $newAudio);
    }
    foreach ($newPhotos as $p) {
        delete_storage_file('photos', $p['file']);
    }
}

$photos = [];
if ($isEdit) {
    $ps = $pdo->prepare('SELECT * FROM photos WHERE story_id = ? ORDER BY sort_order, id');
    $ps->execute([$id]);
    $photos = $ps->fetchAll();
}

$pageTitle = $isEdit ? '编辑故事' : '新增故事';
$page = 'stories.php';
require __DIR__ . '/parts/header.php';
?>
<a href="<?= h(url('admin/stories.php')) ?>" class="back-link">← 返回故事列表</a>

<?php if ($errors): ?>
  <div class="flash flash-error"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form form-wide">
  <?= csrf_field() ?>
  <input type="hidden" name="subaction" value="save">

  <div class="form-grid">
    <label>讲述长辈 <span class="req">*</span>
      <select name="elder_id" required>
        <option value="">— 请选择 —</option>
        <?php foreach ($elders as $e): ?>
          <option value="<?= (int)$e['id'] ?>" <?= (int)$story['elder_id'] === (int)$e['id'] ? 'selected' : '' ?>><?= h($e['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>故事标题 <span class="req">*</span>
      <input type="text" name="title" required maxlength="120" value="<?= h($story['title']) ?>" placeholder="如：逃难路上的那碗粥">
    </label>
    <label>发生年份
      <input type="text" name="story_year" inputmode="numeric" pattern="\d{4}" maxlength="4"
             value="<?= h($story['story_year']) ?>" placeholder="如 1958">
    </label>
    <label>年代标签（可选）
      <input type="text" name="era" maxlength="60" value="<?= h($story['era']) ?>" placeholder="留空将按年份自动显示">
    </label>
    <label class="span-2">地点
      <input type="text" name="location" maxlength="120" value="<?= h($story['location']) ?>" placeholder="如：江苏盐城 · 老生产队">
    </label>
  </div>

  <label>录音文字稿
    <textarea name="transcript" rows="12" placeholder="将录音逐字整理在这里，可以分段、保留原话…"><?= h($story['transcript']) ?></textarea>
  </label>

  <fieldset class="form-fieldset">
    <legend>录音文件（mp3 / m4a / wav / ogg，≤50MB）</legend>
    <?php if (!empty($story['audio_file'])): ?>
      <div class="current-audio">
        <audio controls preload="none" class="audio-player">
          <source src="<?= h(url('media.php?kind=audio&f=' . rawurlencode($story['audio_file']))) ?>"
                  type="<?= h($story['audio_mime'] ?: 'audio/mpeg') ?>">
        </audio>
        <p class="muted small">当前录音：<?= h($story['audio_file']) ?>，上传新文件将替换它。</p>
      </div>
    <?php endif; ?>
    <input type="file" name="audio" accept="audio/*">
  </fieldset>

  <fieldset class="form-fieldset">
    <legend>照片（可一次选多张，每张 ≤8MB）</legend>
    <?php if ($photos): ?>
      <div class="photo-admin-grid">
        <?php foreach ($photos as $p): ?>
          <div class="photo-admin-item">
            <img src="<?= h(url('media.php?kind=photos&f=' . rawurlencode($p['file_name']))) ?>" alt="">
            <form method="post" class="photo-caption-form">
              <?= csrf_field() ?>
              <input type="hidden" name="subaction" value="photo">
              <input type="hidden" name="photo_id" value="<?= (int)$p['id'] ?>">
              <input type="text" name="caption" value="<?= h($p['caption']) ?>" placeholder="照片说明">
              <button name="op" value="caption" class="btn btn-outline btn-sm">保存说明</button>
            </form>
            <form method="post" class="js-confirm" data-confirm="删除这张照片？">
              <?= csrf_field() ?>
              <input type="hidden" name="subaction" value="photo">
              <input type="hidden" name="photo_id" value="<?= (int)$p['id'] ?>">
              <input type="hidden" name="op" value="delete">
              <button class="btn btn-danger btn-sm btn-block">删除照片</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <input type="file" name="photos[]" accept="image/*" multiple>
  </fieldset>

  <fieldset class="form-fieldset">
    <legend>隐私与发布</legend>
    <div class="radio-row">
      <label class="radio-card">
        <input type="radio" name="visibility" value="public" <?= $story['visibility'] === 'public' ? 'checked' : '' ?>>
        <span><strong>🌐 公开</strong><small>所有访客（含未登录的亲友）都能看</small></span>
      </label>
      <label class="radio-card">
        <input type="radio" name="visibility" value="family" <?= $story['visibility'] === 'family' ? 'checked' : '' ?>>
        <span><strong>🔒 仅家人可见</strong><small>只有登录的家人账号能查看文字稿、录音和照片</small></span>
      </label>
    </div>
    <label class="inline-select">状态：
      <select name="status">
        <option value="published" <?= $story['status'] === 'published' ? 'selected' : '' ?>>已发布（前台可见）</option>
        <option value="archived" <?= $story['status'] === 'archived' ? 'selected' : '' ?>>已归档（前台隐藏，仅后台保留）</option>
      </select>
    </label>
  </fieldset>

  <div class="form-actions">
    <button class="btn btn-primary"><?= $isEdit ? '保存修改' : '创建故事' ?></button>
    <?php if ($isEdit): ?>
      <a class="btn btn-outline" href="<?= h(url('story.php?id=' . $id)) ?>" target="_blank">预览前台效果</a>
    <?php endif; ?>
  </div>
</form>

<script>
document.querySelectorAll('.js-confirm').forEach(f => f.addEventListener('submit', e => {
  if (!confirm(f.dataset.confirm)) e.preventDefault();
}));
</script>
<?php require __DIR__ . '/parts/footer.php'; ?>
