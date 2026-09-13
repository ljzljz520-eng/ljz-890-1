<?php
require_once __DIR__ . '/../functions.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$elder = [
    'name' => '', 'gender' => '', 'birth_year' => '', 'death_year' => '',
    'era' => '', 'hometown' => '', 'bio' => '', 'photo_file' => '',
];
if ($isEdit) {
    $st = $pdo->prepare('SELECT * FROM elders WHERE id = ?');
    $st->execute([$id]);
    $elder = $st->fetch();
    if (!$elder) {
        set_flash('error', '长辈不存在');
        redirect('admin/elders.php');
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach (['name', 'gender', 'era', 'hometown', 'bio'] as $k) {
        $elder[$k] = trim($_POST[$k] ?? '');
    }
    $elder['birth_year'] = trim($_POST['birth_year'] ?? '');
    $elder['death_year'] = trim($_POST['death_year'] ?? '');

    if ($elder['name'] === '') {
        $errors[] = '姓名必填';
    }
    if (!in_array($elder['gender'], ['', 'male', 'female'], true)) {
        $elder['gender'] = '';
    }
    foreach (['birth_year', 'death_year'] as $yk) {
        if ($elder[$yk] !== '' && !preg_match('/^\d{4}$/', $elder[$yk])) {
            $errors[] = ($yk === 'birth_year' ? '出生' : '离世') . '年份需为 4 位数字';
            $elder[$yk] = '';
        } else {
            $elder[$yk] = $elder[$yk] === '' ? null : (int)$elder[$yk];
        }
    }
    if ($elder['birth_year'] && $elder['death_year'] && $elder['death_year'] < $elder['birth_year']) {
        $errors[] = '离世年份不能早于出生年份';
    }

    // 头像
    $newPhoto = '';
    if (!empty($_FILES['photo']['name'])) {
        $up = save_upload($_FILES['photo'], 'avatars', ALLOWED_IMAGE, MAX_IMAGE_SIZE);
        if (!$up['ok']) {
            $errors[] = $up['error'];
        } else {
            $newPhoto = $up['file'];
        }
    }

    if (!$errors) {
        if ($isEdit) {
            $sql = 'UPDATE elders SET name=?, gender=?, birth_year=?, death_year=?, era=?, hometown=?, bio=?';
            $params = [
                $elder['name'], $elder['gender'], $elder['birth_year'], $elder['death_year'],
                $elder['era'], $elder['hometown'], $elder['bio'],
            ];
            if ($newPhoto) {
                $sql .= ', photo_file=?';
                $params[] = $newPhoto;
            }
            $sql .= ' WHERE id=?';
            $params[] = $id;
            $pdo->prepare($sql)->execute($params);
            if ($newPhoto && !empty($elder['photo_file'])) {
                delete_storage_file('avatars', $elder['photo_file']);
            }
            set_flash('success', '长辈资料已更新');
        } else {
            $st = $pdo->prepare('INSERT INTO elders (name, gender, birth_year, death_year, era, hometown, bio, photo_file)
                                VALUES (?,?,?,?,?,?,?,?)');
            $st->execute([
                $elder['name'], $elder['gender'], $elder['birth_year'], $elder['death_year'],
                $elder['era'], $elder['hometown'], $elder['bio'], $newPhoto,
            ]);
            $id = (int)$pdo->lastInsertId();
            set_flash('success', '长辈已录入，现在可以为 TA 添加故事');
        }
        redirect('admin/elder_edit.php?id=' . $id);
    }
    if ($newPhoto) {
        delete_storage_file('avatars', $newPhoto);
    }
}

$pageTitle = $isEdit ? '编辑长辈' : '录入长辈';
$page = 'elders.php';
require __DIR__ . '/parts/header.php';
?>
<a href="<?= h(url('admin/elders.php')) ?>" class="back-link">← 返回长辈列表</a>

<?php if ($errors): ?>
  <div class="flash flash-error"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form form-wide">
  <?= csrf_field() ?>
  <div class="form-grid">
    <label>姓名 <span class="req">*</span>
      <input type="text" name="name" required maxlength="60" value="<?= h($elder['name']) ?>">
    </label>
    <label>性别
      <select name="gender">
        <option value="" <?= $elder['gender'] === '' ? 'selected' : '' ?>>未填写</option>
        <option value="male" <?= $elder['gender'] === 'male' ? 'selected' : '' ?>>男</option>
        <option value="female" <?= $elder['gender'] === 'female' ? 'selected' : '' ?>>女</option>
      </select>
    </label>
    <label>出生年份
      <input type="text" name="birth_year" inputmode="numeric" pattern="\d{4}" maxlength="4" value="<?= h($elder['birth_year']) ?>">
    </label>
    <label>离世年份（在世可留空）
      <input type="text" name="death_year" inputmode="numeric" pattern="\d{4}" maxlength="4" value="<?= h($elder['death_year']) ?>">
    </label>
    <label>年代标签
      <input type="text" name="era" maxlength="60" value="<?= h($elder['era']) ?>" placeholder="如：民国生人 / 二十年代">
    </label>
    <label>籍贯 / 常住地
      <input type="text" name="hometown" maxlength="120" value="<?= h($elder['hometown']) ?>" placeholder="如：山东曲阜">
    </label>
  </div>

  <label>生平简介
    <textarea name="bio" rows="5" placeholder="一两段话介绍这位长辈"><?= h($elder['bio']) ?></textarea>
  </label>

  <div class="avatar-edit">
    <?php if (!empty($elder['photo_file'])): ?>
      <img class="avatar-preview" src="<?= h(url('media.php?kind=avatars&f=' . rawurlencode($elder['photo_file']))) ?>" alt="">
    <?php else: ?>
      <div class="avatar-preview avatar-placeholder avatar-lg"><?= h(mb_substr($elder['name'] ?: '长', 0, 1)) ?></div>
    <?php endif; ?>
    <label>头像照片（≤8MB，上传新照片会替换旧的）
      <input type="file" name="photo" accept="image/*">
    </label>
  </div>

  <div class="form-actions">
    <button class="btn btn-primary"><?= $isEdit ? '保存修改' : '录入长辈' ?></button>
    <?php if ($isEdit): ?>
      <a class="btn btn-outline" href="<?= h(url('admin/story_edit.php?elder_id=' . $id)) ?>">接着为 TA 添加故事 →</a>
    <?php endif; ?>
  </div>
</form>
<?php require __DIR__ . '/parts/footer.php'; ?>
