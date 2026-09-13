<?php
require_once __DIR__ . '/../functions.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$user = ['username' => '', 'display_name' => '', 'role' => 'family'];
if ($isEdit) {
    $st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    $user = $st->fetch();
    if (!$user) {
        set_flash('error', '账号不存在');
        redirect('admin/users.php');
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $user['username'] = trim($_POST['username'] ?? '');
    $user['display_name'] = trim($_POST['display_name'] ?? '');
    $user['role'] = ($_POST['role'] ?? 'family') === 'admin' ? 'admin' : 'family';
    $password = $_POST['password'] ?? '';

    if (!preg_match('/^[\p{L}\p{N}_.\-]{2,40}$/u', $user['username'])) {
        $errors[] = '用户名需为 2-40 位中文、字母、数字或 _ . -';
    }
    if ($user['display_name'] === '') {
        $errors[] = '请填写称呼';
    }
    if (!$isEdit && strlen($password) < 6) {
        $errors[] = '新账号密码至少 6 位';
    }
    if ($isEdit && $password !== '' && strlen($password) < 6) {
        $errors[] = '新密码至少 6 位';
    }
    // 不允许把最后一个管理员降级
    if ($isEdit && $user['role'] !== 'admin') {
        if ((int)$user['id'] === (int)current_user()['id']) {
            $errors[] = '不能降级当前登录的管理员账号';
        }
        $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
        if ($admins <= 1 && $user['role'] === 'admin') {
            $errors[] = '至少保留一个管理员';
        }
    }

    // 用户名唯一
    $chk = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
    $chk->execute([$user['username'], $id]);
    if ($chk->fetchColumn()) {
        $errors[] = '用户名已存在';
    }

    if (!$errors) {
        if ($isEdit) {
            $pdo->prepare('UPDATE users SET username=?, display_name=?, role=? WHERE id=?')
                ->execute([$user['username'], $user['display_name'], $user['role'], $id]);
            if ($password !== '') {
                $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            set_flash('success', '账号已更新');
        } else {
            $pdo->prepare('INSERT INTO users (username, display_name, password_hash, role) VALUES (?,?,?,?)')
                ->execute([$user['username'], $user['display_name'], password_hash($password, PASSWORD_DEFAULT), $user['role']]);
            $id = (int)$pdo->lastInsertId();
            set_flash('success', '账号已创建');
        }
        redirect('admin/users.php');
    }
}

$pageTitle = $isEdit ? '编辑账号' : '新建账号';
$page = 'users.php';
require __DIR__ . '/parts/header.php';
?>
<a href="<?= h(url('admin/users.php')) ?>" class="back-link">← 返回账号列表</a>

<?php if ($errors): ?>
  <div class="flash flash-error"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="form form-narrow">
  <?= csrf_field() ?>
  <label>用户名（登录用） <span class="req">*</span>
    <input type="text" name="username" required value="<?= h($user['username']) ?>" autocomplete="off">
  </label>
  <label>称呼 <span class="req">*</span>
    <input type="text" name="display_name" required maxlength="40" value="<?= h($user['display_name']) ?>" placeholder="如：二叔、小敏">
  </label>
  <label>角色
    <select name="role">
      <option value="family" <?= $user['role'] === 'family' ? 'selected' : '' ?>>家人（可查看私密内容）</option>
      <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>管理员（可管理全部内容、审核投稿）</option>
    </select>
  </label>
  <label>密码 <?= $isEdit ? '（留空表示不修改）' : '<span class="req">*</span>' ?>
    <input type="password" name="password" autocomplete="new-password" placeholder="<?= $isEdit ? '至少 6 位' : '至少 6 位' ?>" <?= $isEdit ? '' : 'required' ?>>
  </label>
  <div class="form-actions">
    <button class="btn btn-primary"><?= $isEdit ? '保存' : '创建账号' ?></button>
  </div>
</form>
<?php require __DIR__ . '/parts/footer.php'; ?>
