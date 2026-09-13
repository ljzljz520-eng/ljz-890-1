<?php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../functions.php';
}
$pageTitle = $pageTitle ?? SITE_NAME;
$currentUser = current_user();
$page = $page ?? basename($_SERVER['SCRIPT_NAME'] ?? '');
$flashes = take_flashes();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> · <?= h(SITE_NAME) ?></title>
<link rel="stylesheet" href="<?= h(url('assets/css/style.css')) ?>">
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= h(url('index.php')) ?>">
      <span class="brand-mark">家</span>
      <span class="brand-text"><?= h(SITE_NAME) ?></span>
    </a>
    <nav class="main-nav" id="mainNav">
      <a href="<?= h(url('index.php')) ?>" class="<?= $page === 'index.php' ? 'active' : '' ?>">首页</a>
      <a href="<?= h(url('elders.php')) ?>" class="<?= $page === 'elders.php' || $page === 'elder.php' ? 'active' : '' ?>">长辈</a>
      <a href="<?= h(url('stories.php')) ?>" class="<?= $page === 'stories.php' || $page === 'story.php' ? 'active' : '' ?>">时间线</a>
      <a href="<?= h(url('submit.php')) ?>" class="<?= $page === 'submit.php' ? 'active' : '' ?>">补充故事</a>
      <?php if (is_admin()): ?>
        <a href="<?= h(url('admin/index.php')) ?>">后台管理</a>
      <?php endif; ?>
    </nav>
    <div class="header-user">
      <?php if ($currentUser): ?>
        <span class="user-badge" title="当前以家人身份登录">
          👤 <?= h($currentUser['display_name']) ?>
          <?php if ($currentUser['role'] === 'admin'): ?><em>管理员</em><?php endif; ?>
        </span>
        <form method="post" action="<?= h(url('logout.php')) ?>" class="inline-form">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-ghost btn-sm">退出</button>
        </form>
      <?php else: ?>
        <a href="<?= h(url('login.php')) ?>" class="btn btn-outline btn-sm">家人登录</a>
      <?php endif; ?>
      <button class="nav-toggle" id="navToggle" aria-label="菜单">☰</button>
    </div>
  </div>
</header>

<?php if ($flashes): ?>
<div class="container flash-wrap">
  <?php foreach ($flashes as $f): ?>
    <div class="flash flash-<?= h($f['type']) ?>"><?= h($f['msg']) ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<main class="container site-main">
