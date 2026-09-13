<?php
require_once __DIR__ . '/functions.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if (attempt_login($u, $p)) {
        set_flash('success', '欢迎回家，' . current_user()['display_name'] . '！现在可以查看全部家人故事了。');
        $next = $_GET['next'] ?? '';
        // 只允许站内跳转
        if ($next && strpos($next, BASE_URL . '/') === 0) {
            header('Location: ' . $next);
            exit;
        }
        redirect('index.php');
    }
    $error = '用户名或密码不正确';
}

$pageTitle = '家人登录';
$page = 'login.php';
require __DIR__ . '/parts/header.php';
?>
<div class="auth-card">
  <h1>家人登录</h1>
  <p class="muted">登录后可查看仅家人可见的故事与录音。账号由家族管理员创建。</p>
  <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
  <form method="post" class="form form-narrow">
    <?= csrf_field() ?>
    <label>用户名
      <input type="text" name="username" required autofocus autocomplete="username">
    </label>
    <label>密码
      <input type="password" name="password" required autocomplete="current-password">
    </label>
    <button type="submit" class="btn btn-primary">登录</button>
  </form>
</div>
<?php require __DIR__ . '/parts/footer.php'; ?>
