<?php
require_once __DIR__ . '/../functions.php';
require_admin();

$status = $_GET['status'] ?? 'pending';
if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
    $status = 'pending';
}
if ($status === 'all') {
    $rows = $pdo->query("
        SELECT su.*, e.name AS elder_name FROM submissions su
        LEFT JOIN elders e ON e.id = su.elder_id
        ORDER BY su.id DESC
    ")->fetchAll();
} else {
    $st = $pdo->prepare("
        SELECT su.*, e.name AS elder_name FROM submissions su
        LEFT JOIN elders e ON e.id = su.elder_id
        WHERE su.status = ? ORDER BY su.id DESC
    ");
    $st->execute([$status]);
    $rows = $st->fetchAll();
}
$counts = [
    'pending' => (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE status='pending'")->fetchColumn(),
    'approved' => (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE status='approved'")->fetchColumn(),
    'rejected' => (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE status='rejected'")->fetchColumn(),
];

$pageTitle = '投稿审核';
$page = 'submissions.php';
require __DIR__ . '/parts/header.php';
?>
<div class="tabs">
  <a class="tab <?= $status === 'pending' ? 'on' : '' ?>" href="<?= h(url('admin/submissions.php?status=pending')) ?>">待审核 (<?= $counts['pending'] ?>)</a>
  <a class="tab <?= $status === 'approved' ? 'on' : '' ?>" href="<?= h(url('admin/submissions.php?status=approved')) ?>">已通过 (<?= $counts['approved'] ?>)</a>
  <a class="tab <?= $status === 'rejected' ? 'on' : '' ?>" href="<?= h(url('admin/submissions.php?status=rejected')) ?>">已拒绝 (<?= $counts['rejected'] ?>)</a>
  <a class="tab <?= $status === 'all' ? 'on' : '' ?>" href="<?= h(url('admin/submissions.php?status=all')) ?>">全部</a>
</div>

<table class="table table-cards">
  <thead><tr><th>故事</th><th>提交人</th><th>长辈 / 年份 / 地点</th><th>提交时间</th><th class="col-actions">操作</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $s): ?>
    <tr class="submission-row submission-<?= h($s['status']) ?>">
      <td>
        <strong><?= h($s['title']) ?></strong>
        <?php if ($s['photo_file']): ?><span class="muted small"> 📷 附照片</span><?php endif; ?>
        <p class="muted small"><?= h(excerpt($s['content'], 70)) ?></p>
        <?php if ($s['status'] !== 'pending'): ?>
          <p class="muted small">审核备注：<?= h($s['review_note'] ?: '—') ?></p>
        <?php endif; ?>
      </td>
      <td>
        <?= h($s['contributor'] ?: '匿名') ?>
        <?php if ($s['relation']): ?><br><small class="muted"><?= h($s['relation']) ?></small><?php endif; ?>
        <?php if ($s['contact']): ?><br><small class="muted"><?= h($s['contact']) ?></small><?php endif; ?>
      </td>
      <td>
        <?= h($s['elder_name'] ?? '<未指定>') ?>
        <br><small class="muted"><?= $s['story_year'] ? (int)$s['story_year'] . ' 年' : '年份不详' ?><?= $s['location'] ? ' · ' . h($s['location']) : '' ?></small>
      </td>
      <td class="muted small"><?= h($s['created_at']) ?></td>
      <td class="col-actions">
        <a class="btn btn-primary btn-sm" href="<?= h(url('admin/submission_edit.php?id=' . $s['id'])) ?>">
          <?= $s['status'] === 'pending' ? '审核' : '查看' ?>
        </a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php if (!$rows): ?><p class="empty-hint">这里空空如也。</p><?php endif; ?>
<?php require __DIR__ . '/parts/footer.php'; ?>
