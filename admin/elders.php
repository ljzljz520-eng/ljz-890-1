<?php
require_once __DIR__ . '/../functions.php';
require_admin();

// 删除
if (($_GET['action'] ?? '') === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $st = $pdo->prepare('SELECT photo_file FROM elders WHERE id = ?');
    $st->execute([$id]);
    $photo = $st->fetchColumn();
    $pdo->prepare('DELETE FROM elders WHERE id = ?')->execute([$id]);
    if ($photo) {
        delete_storage_file('avatars', $photo);
    }
    set_flash('success', '长辈及其故事已删除。');
    redirect('admin/elders.php');
}

$elders = $pdo->query("
    SELECT e.*, (SELECT COUNT(*) FROM stories s WHERE s.elder_id = e.id) AS story_count
    FROM elders e ORDER BY e.birth_year IS NULL, e.birth_year DESC, e.id DESC
")->fetchAll();

$pageTitle = '长辈管理';
$page = 'elders.php';
require __DIR__ . '/parts/header.php';
?>
<div class="toolbar">
  <a class="btn btn-primary" href="<?= h(url('admin/elder_edit.php')) ?>">＋ 录入长辈</a>
</div>

<table class="table table-cards">
  <thead>
    <tr><th>长辈</th><th>生卒</th><th>籍贯</th><th>故事数</th><th class="col-actions">操作</th></tr>
  </thead>
  <tbody>
  <?php foreach ($elders as $e): ?>
    <tr>
      <td>
        <div class="cell-person">
          <?php if ($e['photo_file']): ?>
            <img class="cell-avatar" src="<?= h(url('media.php?kind=avatars&f=' . rawurlencode($e['photo_file']))) ?>" alt="">
          <?php else: ?>
            <span class="cell-avatar avatar-placeholder"><?= h(mb_substr($e['name'], 0, 1)) ?></span>
          <?php endif; ?>
          <div>
            <strong><?= h($e['name']) ?></strong>
            <?php if ($e['era']): ?><br><small class="muted"><?= h($e['era']) ?></small><?php endif; ?>
          </div>
        </div>
      </td>
      <td><?= h(life_span($e) ?: '—') ?></td>
      <td><?= h($e['hometown'] ?: '—') ?></td>
      <td><?= (int)$e['story_count'] ?></td>
      <td class="col-actions">
        <a class="btn btn-outline btn-sm" href="<?= h(url('elder.php?id=' . $e['id'])) ?>" target="_blank">查看</a>
        <a class="btn btn-outline btn-sm" href="<?= h(url('admin/elder_edit.php?id=' . $e['id'])) ?>">编辑</a>
        <form method="post" action="<?= h(url('admin/elders.php?action=delete')) ?>" class="inline-form js-confirm"
              data-confirm="确定删除「<?= h($e['name']) ?>」？该长辈下所有故事、照片和录音都会一并删除，且不可恢复！">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
          <button class="btn btn-danger btn-sm">删除</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php if (!$elders): ?><p class="empty-hint">还没有长辈，点右上角「录入长辈」开始吧。</p><?php endif; ?>

<script>
document.querySelectorAll('.js-confirm').forEach(f => f.addEventListener('submit', e => {
  if (!confirm(f.dataset.confirm)) e.preventDefault();
}));
</script>
<?php require __DIR__ . '/parts/footer.php'; ?>
