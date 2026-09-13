<?php
require_once __DIR__ . '/../functions.php';

$stats = [
    'elders'     => (int)$pdo->query('SELECT COUNT(*) FROM elders')->fetchColumn(),
    'stories'    => (int)$pdo->query("SELECT COUNT(*) FROM stories WHERE status='published'")->fetchColumn(),
    'family'     => (int)$pdo->query("SELECT COUNT(*) FROM stories WHERE visibility='family'")->fetchColumn(),
    'photos'     => (int)$pdo->query('SELECT COUNT(*) FROM photos')->fetchColumn(),
    'pending'    => (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE status='pending'")->fetchColumn(),
    'users'      => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
];

$recentSubmissions = $pdo->query("
    SELECT su.*, e.name AS elder_name FROM submissions su
    LEFT JOIN elders e ON e.id = su.elder_id
    ORDER BY su.id DESC LIMIT 6
")->fetchAll();

$recentStories = $pdo->query("
    SELECT s.*, e.name AS elder_name FROM stories s JOIN elders e ON e.id = s.elder_id
    ORDER BY s.id DESC LIMIT 6
")->fetchAll();

$pageTitle = '概览';
$page = 'index.php';
require __DIR__ . '/parts/header.php';
?>
<div class="stat-grid">
  <div class="stat-card"><div class="stat-num"><?= $stats['elders'] ?></div><div class="stat-label">长辈</div></div>
  <div class="stat-card"><div class="stat-num"><?= $stats['stories'] ?></div><div class="stat-label">已发布故事</div></div>
  <div class="stat-card stat-warn"><div class="stat-num"><?= $stats['family'] ?></div><div class="stat-label">🔒 仅家人可见</div></div>
  <div class="stat-card"><div class="stat-num"><?= $stats['photos'] ?></div><div class="stat-label">照片</div></div>
  <a class="stat-card stat-link <?= $stats['pending'] ? 'stat-danger' : '' ?>" href="<?= h(url('admin/submissions.php')) ?>">
    <div class="stat-num"><?= $stats['pending'] ?></div><div class="stat-label">待审核投稿</div>
  </a>
  <div class="stat-card"><div class="stat-num"><?= $stats['users'] ?></div><div class="stat-label">家人账号</div></div>
</div>

<div class="admin-cols">
  <section class="panel">
    <div class="panel-head">
      <h2>最新投稿</h2>
      <a href="<?= h(url('admin/submissions.php')) ?>" class="btn btn-ghost btn-sm">全部</a>
    </div>
    <table class="table">
      <thead><tr><th>标题</th><th>提交人</th><th>状态</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($recentSubmissions as $s): ?>
        <tr>
          <td><?= h($s['title']) ?><br><small class="muted"><?= h($s['elder_name'] ?? '未指定长辈') ?></small></td>
          <td><?= h($s['contributor'] ?: '—') ?></td>
          <td><?php
            $map = ['pending' => '<span class="status status-pending">待审核</span>',
                    'approved' => '<span class="status status-approved">已通过</span>',
                    'rejected' => '<span class="status status-rejected">已拒绝</span>'];
            echo $map[$s['status']] ?? '';
          ?></td>
          <td><a class="btn btn-outline btn-sm" href="<?= h(url('admin/submission_edit.php?id=' . $s['id'])) ?>">处理</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recentSubmissions): ?><tr><td colspan="4" class="muted">暂无投稿</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="panel">
    <div class="panel-head">
      <h2>最新录入的故事</h2>
      <a href="<?= h(url('admin/story_edit.php')) ?>" class="btn btn-primary btn-sm">＋ 新增故事</a>
    </div>
    <table class="table">
      <thead><tr><th>标题</th><th>长辈</th><th>可见性</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($recentStories as $s): ?>
        <tr>
          <td><?= h($s['title']) ?></td>
          <td><?= h($s['elder_name']) ?></td>
          <td><?= $s['visibility'] === 'family'
              ? '<span class="status status-family">🔒 家人</span>'
              : '<span class="status status-public">公开</span>' ?></td>
          <td><a class="btn btn-outline btn-sm" href="<?= h(url('admin/story_edit.php?id=' . $s['id'])) ?>">编辑</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recentStories): ?><tr><td colspan="4" class="muted">还没有故事</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
</div>
<?php require __DIR__ . '/parts/footer.php'; ?>
