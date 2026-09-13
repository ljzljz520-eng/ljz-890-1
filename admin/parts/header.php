<?php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../functions.php';
}
require_admin();
$pageTitle = $pageTitle ?? '后台管理';
$page = $page ?? basename($_SERVER['SCRIPT_NAME'] ?? '');

$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE status='pending'")->fetchColumn();
$flashes = take_flashes();

function navActive(string $name, string $current): string {
    return $name === $current ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> · 后台 · <?= h(SITE_NAME) ?></title>
<link rel="stylesheet" href="<?= h(url('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= h(url('assets/css/admin.css')) ?>">
</head>
<body class="admin-body">
<aside class="admin-sidebar" id="adminSidebar">
  <div class="admin-logo"><span class="brand-mark">家</span> 管理后台</div>
  <nav class="admin-nav">
    <a class="<?= navActive('index.php', $page) ?>" href="<?= h(url('admin/index.php')) ?>">📊 概览</a>
    <a class="<?= navActive('elders.php', $page) ?> <?= in_array($page, ['elder_edit.php']) ? 'active' : '' ?>"
       href="<?= h(url('admin/elders.php')) ?>">👴 长辈管理</a>
    <a class="<?= navActive('stories.php', $page) ?> <?= in_array($page, ['story_edit.php']) ? 'active' : '' ?>"
       href="<?= h(url('admin/stories.php')) ?>">📖 故事管理</a>
    <a class="<?= navActive('submissions.php', $page) ?> <?= in_array($page, ['submission_edit.php']) ? 'active' : '' ?>"
       href="<?= h(url('admin/submissions.php')) ?>">
      ✉️ 投稿审核
      <?php if ($pendingCount): ?><span class="badge badge-danger"><?= $pendingCount ?></span><?php endif; ?>
    </a>
    <a class="<?= navActive('users.php', $page) ?> <?= in_array($page, ['user_edit.php']) ? 'active' : '' ?>"
       href="<?= h(url('admin/users.php')) ?>">👨‍👩‍👧 家人账号</a>
  </nav>
  <div class="admin-sidebar-foot">
    <a href="<?= h(url('index.php')) ?>">↩ 返回前台</a>
  </div>
</aside>
<div class="admin-main">
  <header class="admin-topbar">
    <button class="nav-toggle admin-toggle" id="sidebarToggle">☰</button>
    <h1><?= h($pageTitle) ?></h1>
    <div class="admin-user">
      <span>👤 <?= h(current_user()['display_name']) ?>（管理员）</span>
      <form method="post" action="<?= h(url('logout.php')) ?>" class="inline-form">
        <?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm">退出</button>
      </form>
    </div>
  </header>

  <?php if ($flashes): ?>
    <div class="admin-content">
      <?php foreach ($flashes as $f): ?>
        <div class="flash flash-<?= h($f['type']) ?>"><?= h($f['msg']) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="admin-content">
