<?php
require_once __DIR__ . '/functions.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT s.*, e.name AS elder_name, e.photo_file AS elder_photo, e.id AS elder_id
    FROM stories s JOIN elders e ON e.id = s.elder_id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$story = $stmt->fetch();

if (!$story || $story['status'] !== 'published') {
    http_response_code(404);
    $pageTitle = '未找到';
    require __DIR__ . '/parts/header.php';
    echo '<p class="empty-hint">故事不存在或已归档。</p>';
    require __DIR__ . '/parts/footer.php';
    exit;
}
if ($story['visibility'] === 'family' && !is_logged_in()) {
    require_login();
}

$photos = $pdo->prepare('SELECT * FROM photos WHERE story_id = ? ORDER BY sort_order, id');
$photos->execute([$id]);
$photos = $photos->fetchAll();

$pageTitle = $story['title'];
$page = 'story.php';
require __DIR__ . '/parts/header.php';
?>
<div class="breadcrumb">
  <a href="<?= h(url('stories.php')) ?>">时间线</a> /
  <a href="<?= h(url('elder.php?id=' . $story['elder_id'])) ?>"><?= h($story['elder_name']) ?></a> /
  <span><?= h($story['title']) ?></span>
</div>

<article class="story-detail">
  <header class="story-detail-head">
    <h1><?= h($story['title']) ?></h1>
    <div class="story-detail-meta">
      <a class="chip" href="<?= h(url('elder.php?id=' . $story['elder_id'])) ?>">
        讲述人：<?= h($story['elder_name']) ?>
      </a>
      <?php if ($story['story_year']): ?>
        <span class="chip">🗓 <?= (int)$story['story_year'] ?> 年<?= $story['era'] ? ' · ' . h($story['era']) : '' ?></span>
      <?php elseif ($story['era']): ?>
        <span class="chip">🗓 <?= h($story['era']) ?></span>
      <?php endif; ?>
      <?php if ($story['location']): ?>
        <span class="chip">📍 <?= h($story['location']) ?></span>
      <?php endif; ?>
      <?php if ($story['visibility'] === 'family'): ?>
        <span class="chip chip-locked">🔒 仅家人可见</span>
      <?php else: ?>
        <span class="chip">🌐 公开</span>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($story['audio_file']): ?>
    <div class="audio-block">
      <h2>🎙 原始录音</h2>
      <audio controls preload="metadata" class="audio-player">
        <source src="<?= h(url('media.php?kind=audio&f=' . rawurlencode($story['audio_file']))) ?>"
                type="<?= h($story['audio_mime'] ?: 'audio/mpeg') ?>">
        您的浏览器不支持音频播放，<a href="<?= h(url('media.php?kind=audio&f=' . rawurlencode($story['audio_file']))) ?>">点此下载</a>。
      </audio>
    </div>
  <?php endif; ?>

  <div class="transcript">
    <h2>录音文字稿</h2>
    <?php if ($story['transcript'] !== ''): ?>
      <div class="transcript-text"><?= nl2br(h($story['transcript'])) ?></div>
    <?php else: ?>
      <p class="muted">（暂无文字稿）</p>
    <?php endif; ?>
  </div>

  <?php if ($photos): ?>
    <div class="photos-block">
      <h2>照片（<?= count($photos) ?>）</h2>
      <div class="photo-grid">
        <?php foreach ($photos as $p): ?>
          <figure class="photo-item">
            <a href="<?= h(url('media.php?kind=photos&f=' . rawurlencode($p['file_name']))) ?>" target="_blank" rel="noopener">
              <img src="<?= h(url('media.php?kind=photos&f=' . rawurlencode($p['file_name']))) ?>"
                   alt="<?= h($p['caption']) ?>" loading="lazy">
            </a>
            <?php if ($p['caption']): ?>
              <figcaption><?= h($p['caption']) ?></figcaption>
            <?php endif; ?>
          </figure>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="story-actions">
    <a class="btn btn-outline" href="<?= h(url('submit.php?elder_id=' . $story['elder_id'])) ?>">为这段记忆补充更多</a>
    <a class="btn btn-ghost" href="<?= h(url('elder.php?id=' . $story['elder_id'])) ?>">返回 <?= h($story['elder_name']) ?> 的主页</a>
  </div>
</article>

<?php require __DIR__ . '/parts/footer.php'; ?>
