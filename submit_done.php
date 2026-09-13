<?php
require_once __DIR__ . '/functions.php';
$pageTitle = '提交成功';
$page = 'submit.php';
require __DIR__ . '/parts/header.php';
?>
<div class="auth-card text-center">
  <div class="done-icon">🕊️</div>
  <h1>收到您的故事了</h1>
  <p class="muted">感谢您为家族留下这段记忆。<br>管理员审核通过后，它会出现在时间线中。</p>
  <div class="form-actions" style="justify-content:center">
    <a href="<?= h(url('index.php')) ?>" class="btn btn-primary">返回首页</a>
    <a href="<?= h(url('stories.php')) ?>" class="btn btn-outline">继续浏览</a>
  </div>
</div>
<?php require __DIR__ . '/parts/footer.php'; ?>
