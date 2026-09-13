<?php
require_once __DIR__ . '/functions.php';

[$visSql] = visibility_scope('s');

// 筛选：长辈、年代（十年段）、搜索
$where = ["s.status = 'published'", $visSql];
$params = [];

$elderId = (int)($_GET['elder_id'] ?? 0);
if ($elderId) {
    $where[] = 's.elder_id = ?';
    $params[] = $elderId;
}
$decade = $_GET['decade'] ?? '';
if (preg_match('/^\d{4}$/', $decade)) {
    $start = (int)$decade;
    $where[] = 's.story_year >= ? AND s.story_year < ?';
    $params[] = $start;
    $params[] = $start + 10;
}
$kw = trim($_GET['q'] ?? '');
if ($kw !== '') {
    $where[] = '(s.title LIKE ? OR s.transcript LIKE ? OR s.location LIKE ?)';
    $params[] = "%{$kw}%";
    $params[] = "%{$kw}%";
    $params[] = "%{$kw}%";
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT s.*, e.name AS elder_name
    FROM stories s JOIN elders e ON e.id = s.elder_id
    WHERE {$whereSql}
    ORDER BY s.story_year IS NULL, s.story_year DESC, s.id DESC
");
$stmt->execute($params);
$stories = $stmt->fetchAll();

// 按年份分组
$grouped = [];
$unknown = [];
foreach ($stories as $s) {
    if ($s['story_year']) {
        $grouped[(int)$s['story_year']][] = $s;
    } else {
        $unknown[] = $s;
    }
}
krsort($grouped);

$elders = $pdo->query('SELECT id, name FROM elders ORDER BY birth_year IS NULL, birth_year DESC')->fetchAll();
// 有故事的年代选项
$decades = $pdo->query("
    SELECT DISTINCT (story_year/10)*10 AS d FROM stories
    WHERE status='published' AND story_year IS NOT NULL ORDER BY d DESC
")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = '故事时间线';
$page = 'stories.php';
require __DIR__ . '/parts/header.php';
?>
<div class="page-head">
  <h1>家族时间线</h1>
  <p class="muted">按时间浏览家族记忆<?= !is_logged_in() ? '，登录后可看到仅家人可见的故事' : '' ?></p>
</div>

<form class="filter-bar" method="get">
  <select name="elder_id">
    <option value="0">全部长辈</option>
    <?php foreach ($elders as $e): ?>
      <option value="<?= (int)$e['id'] ?>" <?= $elderId === (int)$e['id'] ? 'selected' : '' ?>><?= h($e['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="decade">
    <option value="">全部年代</option>
    <?php foreach ($decades as $d): ?>
      <option value="<?= (int)$d ?>" <?= $decade !== '' && (int)$decade === (int)$d ? 'selected' : '' ?>><?= (int)$d ?> 年代</option>
    <?php endforeach; ?>
  </select>
  <input type="search" name="q" value="<?= h($kw) ?>" placeholder="搜索标题、地点、文字稿…">
  <button type="submit" class="btn btn-primary btn-sm">筛选</button>
  <a class="btn btn-ghost btn-sm" href="<?= h(url('stories.php')) ?>">重置</a>
</form>

<?php if (!$stories): ?>
  <p class="empty-hint">没有符合条件的故事。</p>
<?php endif; ?>

<?php foreach ($grouped as $year => $list): ?>
  <section class="year-block">
    <h2 class="year-heading"><span><?= (int)$year ?></span><small><?= h(era_text($year)) ?></small></h2>
    <div class="story-cards">
      <?php foreach ($list as $s): ?>
        <article class="story-card story-card-box">
          <h3>
            <a href="<?= h(url('story.php?id=' . $s['id'])) ?>"><?= h($s['title']) ?></a>
            <?php if ($s['visibility'] === 'family'): ?><span class="tag tag-family" title="仅家人可见">🔒</span><?php endif; ?>
          </h3>
          <p class="story-meta">
            <a href="<?= h(url('elder.php?id=' . $s['elder_id'])) ?>"><?= h($s['elder_name']) ?></a>
            <?php if ($s['location']): ?> · 📍 <?= h($s['location']) ?><?php endif; ?>
          </p>
          <p class="story-excerpt"><?= h(excerpt($s['transcript'], 90)) ?></p>
          <?php if ($s['audio_file']): ?>
            <p class="muted small">🎙 含录音</p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>

<?php if ($unknown): ?>
  <section class="year-block">
    <h2 class="year-heading"><span>年代不详</span></h2>
    <div class="story-cards">
      <?php foreach ($unknown as $s): ?>
        <article class="story-card story-card-box">
          <h3><a href="<?= h(url('story.php?id=' . $s['id'])) ?>"><?= h($s['title']) ?></a>
            <?php if ($s['visibility'] === 'family'): ?><span class="tag tag-family">🔒</span><?php endif; ?>
          </h3>
          <p class="story-meta"><a href="<?= h(url('elder.php?id=' . $s['elder_id'])) ?>"><?= h($s['elder_name']) ?></a><?= $s['location'] ? ' · 📍 ' . h($s['location']) : '' ?></p>
          <p class="story-excerpt"><?= h(excerpt($s['transcript'], 90)) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/parts/footer.php'; ?>
