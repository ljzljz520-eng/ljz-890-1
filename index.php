<?php
require_once __DIR__ . '/functions.php';

[$visSql] = visibility_scope('s');

// 最近的故事（按年份倒序，年份为空排最后）
$recent = $pdo->query("
    SELECT s.*, e.name AS elder_name, e.photo_file AS elder_photo
    FROM stories s JOIN elders e ON e.id = s.elder_id
    WHERE s.status = 'published' AND {$visSql}
    ORDER BY s.story_year IS NULL, s.story_year DESC, s.id DESC
    LIMIT 5
")->fetchAll();

$elders = $pdo->query("
    SELECT e.*, (SELECT COUNT(*) FROM stories s WHERE s.elder_id = e.id AND s.status='published') AS story_count
    FROM elders e ORDER BY e.birth_year IS NULL, e.birth_year DESC, e.id DESC
")->fetchAll();

$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE status='pending'")->fetchColumn();

$pageTitle = '首页';
$page = 'index.php';
require __DIR__ . '/parts/header.php';
?>

<section class="hero">
  <div class="hero-inner">
    <h1>把长辈的故事，<br>一代代传下去</h1>
    <p>这里记录家族长辈的姓名、年代、走过的地方与他们亲口讲述的往事。<br>
       公开故事欢迎所有亲友阅读，私密故事仅登录的家人可见。</p>
    <div class="hero-actions">
      <a href="<?= h(url('stories.php')) ?>" class="btn btn-primary">浏览时间线</a>
      <a href="<?= h(url('submit.php')) ?>" class="btn btn-outline-light">我要补充故事</a>
    </div>
  </div>
</section>

<section class="section">
  <div class="section-head">
    <h2>家中长辈</h2>
    <a href="<?= h(url('elders.php')) ?>" class="more-link">查看全部 →</a>
  </div>
  <div class="elder-grid">
    <?php foreach (array_slice($elders, 0, 4) as $e): ?>
      <a class="elder-card" href="<?= h(url('elder.php?id=' . $e['id'])) ?>">
        <div class="elder-avatar">
          <?php if ($e['photo_file']): ?>
            <img src="<?= h(url('media.php?kind=avatars&f=' . rawurlencode($e['photo_file']))) ?>" alt="<?= h($e['name']) ?>">
          <?php else: ?>
            <span class="avatar-placeholder"><?= h(mb_substr($e['name'], 0, 1)) ?></span>
          <?php endif; ?>
        </div>
        <div class="elder-card-body">
          <h3><?= h($e['name']) ?></h3>
          <p class="muted"><?= h(life_span($e) ?: ($e['era'] ?: '—')) ?></p>
          <p class="muted small"><?= (int)$e['story_count'] ?> 段故事</p>
        </div>
      </a>
    <?php endforeach; ?>
    <?php if (!$elders): ?>
      <p class="empty-hint">还没有录入长辈资料。</p>
    <?php endif; ?>
  </div>
</section>

<section class="section section-alt">
  <div class="section-head">
    <h2>最近的故事</h2>
    <a href="<?= h(url('stories.php')) ?>" class="more-link">完整时间线 →</a>
  </div>
  <ul class="story-list">
    <?php foreach ($recent as $s): ?>
      <li class="story-item">
        <div class="story-year">
          <span class="year-num"><?= h($s['story_year'] ? (int)$s['story_year'] : '—') ?></span>
          <span class="year-era"><?= h($s['era'] ?: era_text((int)$s['story_year'])) ?></span>
        </div>
        <div class="story-card">
          <h3>
            <a href="<?= h(url('story.php?id=' . $s['id'])) ?>"><?= h($s['title']) ?></a>
            <?php if ($s['visibility'] === 'family'): ?>
              <span class="tag tag-family" title="仅家人可见">🔒 家人可见</span>
            <?php endif; ?>
          </h3>
          <p class="story-meta">
            讲述人：<a href="<?= h(url('elder.php?id=' . $s['elder_id'])) ?>"><?= h($s['elder_name']) ?></a>
            <?php if ($s['location']): ?> · 📍 <?= h($s['location']) ?><?php endif; ?>
          </p>
          <p class="story-excerpt"><?= h(excerpt($s['transcript'])) ?></p>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$recent): ?>
    <p class="empty-hint">还没有可浏览的故事。<?php if (!is_admin()): ?>管理员可在后台录入。<?php endif; ?></p>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/parts/footer.php'; ?>
