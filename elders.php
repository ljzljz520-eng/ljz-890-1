<?php
require_once __DIR__ . '/functions.php';

$elders = $pdo->query("
    SELECT e.*,
           (SELECT COUNT(*) FROM stories s
              WHERE s.elder_id = e.id AND s.status='published') AS story_count,
           (SELECT MIN(s.story_year) FROM stories s
              WHERE s.elder_id = e.id AND s.status='published' AND s.story_year IS NOT NULL) AS first_year
    FROM elders e
    ORDER BY e.birth_year IS NULL, e.birth_year DESC, e.id DESC
")->fetchAll();

$pageTitle = '长辈名录';
$page = 'elders.php';
require __DIR__ . '/parts/header.php';
?>
<div class="page-head">
  <h1>长辈名录</h1>
  <p class="muted">共 <?= count($elders) ?> 位长辈，点击查看他们的生平与故事</p>
</div>

<div class="elder-grid elder-grid-wide">
  <?php foreach ($elders as $e): ?>
    <a class="elder-card elder-card-horizontal" href="<?= h(url('elder.php?id=' . $e['id'])) ?>">
      <div class="elder-avatar">
        <?php if ($e['photo_file']): ?>
          <img src="<?= h(url('media.php?kind=avatars&f=' . rawurlencode($e['photo_file']))) ?>" alt="<?= h($e['name']) ?>">
        <?php else: ?>
          <span class="avatar-placeholder"><?= h(mb_substr($e['name'], 0, 1)) ?></span>
        <?php endif; ?>
      </div>
      <div class="elder-card-body">
        <h3><?= h($e['name']) ?>
          <?php if ($e['gender'] === 'male'): ?><span class="gender">♂</span>
          <?php elseif ($e['gender'] === 'female'): ?><span class="gender gender-f">♀</span><?php endif; ?>
        </h3>
        <p class="muted small"><?= h(life_span($e)) ?></p>
        <?php if ($e['hometown']): ?><p class="muted small">📍 <?= h($e['hometown']) ?></p><?php endif; ?>
        <p class="muted small"><?= (int)$e['story_count'] ?> 段故事<?= $e['first_year'] ? '，最早可追溯至 ' . (int)$e['first_year'] . ' 年' : '' ?></p>
      </div>
    </a>
  <?php endforeach; ?>
</div>
<?php if (!$elders): ?>
  <p class="empty-hint">还没有长辈资料。</p>
<?php endif; ?>

<?php require __DIR__ . '/parts/footer.php'; ?>
