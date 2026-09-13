<?php
require_once __DIR__ . '/functions.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM elders WHERE id = ?');
$stmt->execute([$id]);
$elder = $stmt->fetch();
if (!$elder) {
    http_response_code(404);
    $pageTitle = '未找到';
    require __DIR__ . '/parts/header.php';
    echo '<p class="empty-hint">长辈不存在。</p>';
    require __DIR__ . '/parts/footer.php';
    exit;
}

[$visSql] = visibility_scope('s');
$stmt = $pdo->prepare("
    SELECT s.* FROM stories s
    WHERE s.elder_id = ? AND s.status = 'published' AND {$visSql}
    ORDER BY s.story_year IS NULL, s.story_year ASC, s.id ASC
");
$stmt->execute([$id]);
$stories = $stmt->fetchAll();

// 照片数
$photoCount = 0;
foreach ($stories as $s) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM photos WHERE story_id = ?');
    $st->execute([$s['id']]);
    $photoCount += (int)$st->fetchColumn();
}

$pageTitle = $elder['name'];
$page = 'elder.php';
require __DIR__ . '/parts/header.php';
?>
<div class="breadcrumb"><a href="<?= h(url('elders.php')) ?>">长辈名录</a> / <span><?= h($elder['name']) ?></span></div>

<div class="elder-profile">
  <div class="elder-profile-photo">
    <?php if ($elder['photo_file']): ?>
      <img src="<?= h(url('media.php?kind=avatars&f=' . rawurlencode($elder['photo_file']))) ?>" alt="<?= h($elder['name']) ?>">
    <?php else: ?>
      <span class="avatar-placeholder avatar-lg"><?= h(mb_substr($elder['name'], 0, 1)) ?></span>
    <?php endif; ?>
  </div>
  <div class="elder-profile-info">
    <h1><?= h($elder['name']) ?></h1>
    <dl class="info-list">
      <?php if ($span = life_span($elder)): ?>
        <div><dt>生卒</dt><dd><?= h($span) ?></dd></div>
      <?php endif; ?>
      <?php if ($elder['era']): ?>
        <div><dt>年代</dt><dd><?= h($elder['era']) ?></dd></div>
      <?php endif; ?>
      <?php if ($elder['hometown']): ?>
        <div><dt>籍贯 / 常住地</dt><dd><?= h($elder['hometown']) ?></dd></div>
      <?php endif; ?>
      <div><dt>故事</dt><dd><?= count($stories) ?> 段 · 照片 <?= $photoCount ?> 张</dd></div>
    </dl>
    <?php if ($elder['bio']): ?>
      <div class="bio"><?= nl2br(h($elder['bio'])) ?></div>
    <?php endif; ?>
  </div>
</div>

<div class="page-subhead">
  <h2>TA 的故事</h2>
  <a class="btn btn-outline btn-sm" href="<?= h(url('submit.php?elder_id=' . $elder['id'])) ?>">补充 TA 的故事</a>
</div>

<ul class="timeline">
  <?php foreach ($stories as $s): ?>
    <li class="timeline-item">
      <div class="timeline-dot"></div>
      <div class="timeline-year"><?= h(year_label($s['story_year'] ? (int)$s['story_year'] : null)) ?></div>
      <div class="story-card">
        <h3>
          <a href="<?= h(url('story.php?id=' . $s['id'])) ?>"><?= h($s['title']) ?></a>
          <?php if ($s['visibility'] === 'family'): ?><span class="tag tag-family">🔒 家人可见</span><?php endif; ?>
        </h3>
        <p class="story-meta"><?php if ($s['location']): ?>📍 <?= h($s['location']) ?><?php else: ?>地点不详<?php endif; ?></p>
        <p class="story-excerpt"><?= h(excerpt($s['transcript'])) ?></p>
      </div>
    </li>
  <?php endforeach; ?>
</ul>
<?php if (!$stories): ?>
  <p class="empty-hint">暂时还没有公开故事。</p>
<?php endif; ?>

<?php require __DIR__ . '/parts/footer.php'; ?>
