<?php
require_once __DIR__ . '/../functions.php';
require_admin();

if (($_GET['action'] ?? '') === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $st = $pdo->prepare('SELECT audio_file FROM stories WHERE id = ?');
    $st->execute([$id]);
    $audio = $st->fetchColumn();
    $ps = $pdo->prepare('SELECT file_name FROM photos WHERE story_id = ?');
    $ps->execute([$id]);
    $pdo->prepare('DELETE FROM stories WHERE id = ?')->execute([$id]);
    if ($audio) {
        delete_storage_file('audio', $audio);
    }
    foreach ($ps->fetchAll(PDO::FETCH_COLUMN) as $f) {
        delete_storage_file('photos', $f);
    }
    set_flash('success', '故事及其录音、照片已删除。');
    redirect('admin/stories.php');
}

$where = [];
$params = [];
$elderId = (int)($_GET['elder_id'] ?? 0);
$vis = $_GET['visibility'] ?? '';
$kw = trim($_GET['q'] ?? '');
if ($elderId) { $where[] = 's.elder_id = ?'; $params[] = $elderId; }
if (in_array($vis, ['public', 'family'], true)) { $where[] = 's.visibility = ?'; $params[] = $vis; }
if ($kw !== '') { $where[] = '(s.title LIKE ? OR s.transcript LIKE ?)'; $params[] = "%$kw%"; $params[] = "%$kw%"; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stories = $pdo->prepare("
    SELECT s.*, e.name AS elder_name,
           (SELECT COUNT(*) FROM photos p WHERE p.story_id = s.id) AS photo_count
    FROM stories s JOIN elders e ON e.id = s.elder_id
    {$whereSql}
    ORDER BY s.id DESC
");
$stories->execute($params);
$stories = $stories->fetchAll();
$elders = $pdo->query('SELECT id, name FROM elders ORDER BY birth_year IS NULL, birth_year DESC')->fetchAll();

$pageTitle = '故事管理';
$page = 'stories.php';
require __DIR__ . '/parts/header.php';
?>
<div class="toolbar">
  <form class="filter-bar filter-bar-inline" method="get">
    <select name="elder_id">
      <option value="0">全部长辈</option>
      <?php foreach ($elders as $e): ?>
        <option value="<?= (int)$e['id'] ?>" <?= $elderId === (int)$e['id'] ? 'selected' : '' ?>><?= h($e['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="visibility">
      <option value="">全部可见性</option>
      <option value="public" <?= $vis === 'public' ? 'selected' : '' ?>>🌐 公开</option>
      <option value="family" <?= $vis === 'family' ? 'selected' : '' ?>>🔒 仅家人</option>
    </select>
    <input type="search" name="q" value="<?= h($kw) ?>" placeholder="搜索故事…">
    <button class="btn btn-outline btn-sm">筛选</button>
  </form>
  <a class="btn btn-primary" href="<?= h(url('admin/story_edit.php')) ?>">＋ 新增故事</a>
</div>

<table class="table table-cards">
  <thead><tr><th>标题</th><th>讲述人</th><th>年份 / 地点</th><th>可见性</th><th>状态</th><th class="col-actions">操作</th></tr></thead>
  <tbody>
  <?php foreach ($stories as $s): ?>
    <tr>
      <td>
        <strong><?= h($s['title']) ?></strong>
        <div class="muted small">
          <?= $s['audio_file'] ? '🎙 ' : '' ?><?= (int)$s['photo_count'] ?> 张照片
        </div>
      </td>
      <td><a href="<?= h(url('admin/stories.php?elder_id=' . $s['elder_id'])) ?>"><?= h($s['elder_name']) ?></a></td>
      <td><?= $s['story_year'] ? (int)$s['story_year'] . ' 年' : '年代不详' ?><?= $s['location'] ? '<br><small class="muted">' . h($s['location']) . '</small>' : '' ?></td>
      <td><?= $s['visibility'] === 'family'
            ? '<span class="status status-family">🔒 家人</span>'
            : '<span class="status status-public">公开</span>' ?></td>
      <td><?= $s['status'] === 'published'
            ? '<span class="status status-approved">已发布</span>'
            : '<span class="status status-rejected">已归档</span>' ?></td>
      <td class="col-actions">
        <a class="btn btn-outline btn-sm" href="<?= h(url('story.php?id=' . $s['id'])) ?>" target="_blank">查看</a>
        <a class="btn btn-outline btn-sm" href="<?= h(url('admin/story_edit.php?id=' . $s['id'])) ?>">编辑</a>
        <form method="post" action="<?= h(url('admin/stories.php?action=delete')) ?>" class="inline-form js-confirm"
              data-confirm="确定删除故事「<?= h($s['title']) ?>」？录音和照片也会删除。">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <button class="btn btn-danger btn-sm">删除</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php if (!$stories): ?><p class="empty-hint">没有符合条件的故事。</p><?php endif; ?>

<script>
document.querySelectorAll('.js-confirm').forEach(f => f.addEventListener('submit', e => {
  if (!confirm(f.dataset.confirm)) e.preventDefault();
}));
</script>
<?php require __DIR__ . '/parts/footer.php'; ?>
