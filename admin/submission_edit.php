<?php
require_once __DIR__ . '/../functions.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM submissions WHERE id = ?');
$st->execute([$id]);
$sub = $st->fetch();
if (!$sub) {
    set_flash('error', '投稿不存在');
    redirect('admin/submissions.php');
}

$elders = $pdo->query('SELECT id, name FROM elders ORDER BY birth_year IS NULL, birth_year DESC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $note = trim($_POST['review_note'] ?? '');

    if ($action === 'approve') {
        $elderId = (int)($_POST['elder_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $yearRaw = trim($_POST['story_year'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $visibility = ($_POST['visibility'] ?? 'public') === 'family' ? 'family' : 'public';
        $errors = [];

        if (!$elderId) {
            $errors[] = '通过前请为故事选择归属长辈（若还没有该长辈，请先去「长辈管理」录入）';
        }
        if ($title === '') {
            $errors[] = '标题不能为空';
        }
        $year = null;
        if ($yearRaw !== '') {
            if (preg_match('/^\d{4}$/', $yearRaw)) {
                $year = (int)$yearRaw;
            } else {
                $errors[] = '年份需为 4 位数字';
            }
        }
        if ($errors) {
            set_flash('error', implode('；', $errors));
            redirect('admin/submission_edit.php?id=' . $id);
        }

        // 创建故事，正文注明来源
        $body = $sub['content'];
        if ($sub['contributor'] || $sub['relation']) {
            $who = trim(($sub['relation'] ?: '亲友') . ' ' . ($sub['contributor'] ?: ''));
            $body .= "\n\n—— 由" . $who . "补充，经管理员审核收录";
        }
        $pdo->prepare("
            INSERT INTO stories (elder_id, title, story_year, location, era, transcript,
                                 visibility, status, created_by)
            VALUES (?,?,?,?,?,?,?, 'published', ?)
        ")->execute([
            $elderId, $title, $year, $location,
            era_text((int)$year), $body, $visibility, current_user()['id'] ?? null,
        ]);
        $newId = (int)$pdo->lastInsertId();

        // 迁移投稿照片
        if ($sub['photo_file']) {
            $old = UPLOAD_PATH . '/submissions/' . $sub['photo_file'];
            $newName = $sub['photo_file'];
            if (is_file($old)) {
                if (!rename($old, UPLOAD_PATH . '/photos/' . $newName)) {
                    $newName = '';
                }
            }
            if ($newName) {
                $pdo->prepare('INSERT INTO photos (story_id, file_name, mime, caption) VALUES (?,?,?,?)')
                    ->execute([$newId, $newName, $sub['photo_mime'], $sub['photo_caption']]);
            }
        }

        $pdo->prepare("
            UPDATE submissions SET status='approved', review_note=?, reviewed_by=?,
                   reviewed_at=datetime('now','localtime'), new_story_id=? WHERE id=?
        ")->execute([$note, current_user()['id'] ?? null, $newId, $id]);

        set_flash('success', '已通过并发布为新故事，可以继续编辑完善。');
        redirect('admin/story_edit.php?id=' . $newId);
    }

    if ($action === 'reject') {
        $pdo->prepare("
            UPDATE submissions SET status='rejected', review_note=?, reviewed_by=?,
                   reviewed_at=datetime('now','localtime') WHERE id=?
        ")->execute([$note, current_user()['id'] ?? null, $id]);
        set_flash('success', '已拒绝该投稿（可在列表中改判）。');
        redirect('admin/submissions.php?status=rejected');
    }

    if ($action === 'repend') {
        $pdo->prepare("UPDATE submissions SET status='pending', review_note='' WHERE id=?")->execute([$id]);
        set_flash('success', '已重新放回待审核。');
        redirect('admin/submission_edit.php?id=' . $id);
    }
}

$pageTitle = '审核投稿';
$page = 'submissions.php';
require __DIR__ . '/parts/header.php';
?>
<a href="<?= h(url('admin/submissions.php')) ?>" class="back-link">← 返回投稿列表</a>

<div class="submission-detail">
  <div class="submission-meta panel">
    <h2><?= h($sub['title']) ?></h2>
    <p class="muted">
      状态：
      <?php
      $map = ['pending' => '<span class="status status-pending">待审核</span>',
              'approved' => '<span class="status status-approved">已通过</span>',
              'rejected' => '<span class="status status-rejected">已拒绝</span>'];
      echo $map[$sub['status']];
      ?>
      · 提交于 <?= h($sub['created_at']) ?>
    </p>
    <dl class="info-list">
      <div><dt>提交人</dt><dd><?= h($sub['contributor'] ?: '匿名') ?></dd></div>
      <div><dt>关系</dt><dd><?= h($sub['relation'] ?: '—') ?></dd></div>
      <div><dt>联系方式</dt><dd><?= h($sub['contact'] ?: '—') ?></dd></div>
      <div><dt>年份</dt><dd><?= $sub['story_year'] ? (int)$sub['story_year'] . ' 年' : '未填写' ?></dd></div>
      <div><dt>地点</dt><dd><?= h($sub['location'] ?: '—') ?></dd></div>
    </dl>
  </div>

  <?php if ($sub['photo_file'] && is_file(UPLOAD_PATH . '/submissions/' . $sub['photo_file'])): ?>
    <div class="panel">
      <h2>附上的照片</h2>
      <img class="submission-photo" src="<?= h(url('media.php?kind=submissions&f=' . rawurlencode($sub['photo_file']))) ?>" alt="">
      <?php if ($sub['photo_caption']): ?><p class="muted"><?= h($sub['photo_caption']) ?></p><?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="panel">
    <h2>故事原文</h2>
    <div class="submission-content"><?= nl2br(h($sub['content'])) ?></div>
  </div>

  <?php if ($sub['status'] === 'approved' && $sub['new_story_id']): ?>
    <div class="panel panel-ok">
      已发布为故事：
      <a href="<?= h(url('admin/story_edit.php?id=' . (int)$sub['new_story_id'])) ?>">前往编辑 →</a>
      ｜ <a href="<?= h(url('story.php?id=' . (int)$sub['new_story_id'])) ?>" target="_blank">查看前台</a>
      <form method="post" class="inline-form" style="margin-left:16px">
        <?= csrf_field() ?><input type="hidden" name="action" value="repend">
        <button class="btn btn-ghost btn-sm">改判为待审核</button>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($sub['status'] === 'pending'): ?>
  <form method="post" class="panel review-panel">
    <?= csrf_field() ?>
    <h2>审核处理</h2>
    <p class="muted small">通过后将创建一篇新故事。您可以在此调整归类、标题与可见性；正文可保存后再润色。</p>
    <div class="form-grid">
      <label>归属长辈 <span class="req">*</span>
        <select name="elder_id">
          <option value="0">— 请选择 —</option>
          <?php foreach ($elders as $e): ?>
            <option value="<?= (int)$e['id'] ?>" <?= (int)$sub['elder_id'] === (int)$e['id'] ? 'selected' : '' ?>><?= h($e['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>故事标题
        <input type="text" name="title" value="<?= h($sub['title']) ?>">
      </label>
      <label>年份
        <input type="text" name="story_year" inputmode="numeric" pattern="\d{4}" maxlength="4"
               value="<?= h($sub['story_year']) ?>">
      </label>
      <label>地点
        <input type="text" name="location" value="<?= h($sub['location']) ?>">
      </label>
      <label class="span-2">发布可见性
        <span class="radio-row">
          <label class="radio-card"><input type="radio" name="visibility" value="public" checked>
            <span><strong>🌐 公开</strong><small>所有访客可见</small></span></label>
          <label class="radio-card"><input type="radio" name="visibility" value="family">
            <span><strong>🔒 仅家人</strong><small>登录家人可见</small></span></label>
        </span>
      </label>
      <label class="span-2">审核备注（仅后台可见）
        <textarea name="review_note" rows="2" placeholder="如：内容已与姑姑电话核实"><?= h($sub['review_note']) ?></textarea>
      </label>
    </div>
    <div class="form-actions">
      <button name="action" value="approve" class="btn btn-success">✓ 通过并发布</button>
      <button name="action" value="reject" class="btn btn-danger js-confirm-reject">✕ 拒绝</button>
    </div>
  </form>
  <?php endif; ?>

  <?php if ($sub['status'] === 'rejected'): ?>
  <form method="post" class="panel">
    <?= csrf_field() ?><input type="hidden" name="action" value="repend">
    <p>拒绝原因：<?= h($sub['review_note'] ?: '—') ?></p>
    <button class="btn btn-outline">改判为待审核</button>
  </form>
  <?php endif; ?>
</div>

<script>
document.querySelector('.js-confirm-reject')?.addEventListener('click', e => {
  if (!confirm('确定拒绝该投稿？拒绝后不会发布。')) e.preventDefault();
});
</script>
<?php require __DIR__ . '/parts/footer.php'; ?>
