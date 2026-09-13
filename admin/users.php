<?php
require_once __DIR__ . '/../functions.php';
require_admin();

// 删除账号
if (($_GET['action'] ?? '') === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $delId = (int)($_POST['id'] ?? 0);
    if ($delId === (int)current_user()['id']) {
        set_flash('error', '不能删除当前登录的账号');
    } else {
        $role = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $role->execute([$delId]);
        $r = $role->fetchColumn();
        if ($r === 'admin') {
            $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
            if ($admins <= 1) {
                set_flash('error', '至少要保留一个管理员账号');
                redirect('admin/users.php');
            }
        }
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$delId]);
        set_flash('success', '账号已删除');
    }
    redirect('admin/users.php');
}

$users = $pdo->query("
    SELECT u.*, (SELECT COUNT(*) FROM stories s WHERE s.created_by = u.id) AS story_count
    FROM users u ORDER BY u.role DESC, u.id
")->fetchAll();

$pageTitle = '家人账号';
$page = 'users.php';
require __DIR__ . '/parts/header.php';
?>
<div class="toolbar">
  <p class="muted">家人账号用于查看「仅家人可见」的故事；管理员还能进入后台录入内容、审核投稿。</p>
  <a class="btn btn-primary" href="<?= h(url('admin/user_edit.php')) ?>">＋ 新建账号</a>
</div>

<table class="table table-cards">
  <thead><tr><th>用户名</th><th>称呼</th><th>角色</th><th>录入故事</th><th>创建时间</th><th class="col-actions">操作</th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr>
      <td><strong><?= h($u['username']) ?></strong>
        <?php if ((int)$u['id'] === (int)current_user()['id']): ?><span class="tag tag-family">当前账号</span><?php endif; ?>
      </td>
      <td><?= h($u['display_name']) ?></td>
      <td><?= $u['role'] === 'admin'
            ? '<span class="status status-approved">管理员</span>'
            : '<span class="status status-family">家人</span>' ?></td>
      <td><?= (int)$u['story_count'] ?></td>
      <td class="muted small"><?= h($u['created_at']) ?></td>
      <td class="col-actions">
        <a class="btn btn-outline btn-sm" href="<?= h(url('admin/user_edit.php?id=' . $u['id'])) ?>">编辑 / 改密码</a>
        <?php if ((int)$u['id'] !== (int)current_user()['id']): ?>
        <form method="post" action="<?= h(url('admin/users.php?action=delete')) ?>" class="inline-form js-confirm"
              data-confirm="确定删除账号「<?= h($u['username']) ?>」？">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button class="btn btn-danger btn-sm">删除</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<script>
document.querySelectorAll('.js-confirm').forEach(f => f.addEventListener('submit', e => {
  if (!confirm(f.dataset.confirm)) e.preventDefault();
}));
</script>
<?php require __DIR__ . '/parts/footer.php'; ?>
