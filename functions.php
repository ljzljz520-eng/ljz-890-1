<?php
/**
 * 公共函数库
 */
require_once __DIR__ . '/db.php';

/* ---------- 输出与 URL ---------- */

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return BASE_URL . '/' . $path;
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/* ---------- 用户 / 权限 ---------- */

function current_user(): ?array
{
    static $cachedUid = null;
    static $user = null;
    $uid = $_SESSION['uid'] ?? null;
    if ($cachedUid === $uid) {
        return $user; // 同一次请求且 uid 未变时使用缓存（含未登录）
    }
    $cachedUid = $uid;
    $user = null;
    if (empty($uid)) {
        return null;
    }
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    $u = $stmt->fetch();
    $user = $u ?: null;
    if (!$user) {
        unset($_SESSION['uid']);
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', '该内容仅家人可见，请先登录。');
        redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? url()));
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('403 - 仅管理员可访问后台。');
    }
}

function attempt_login(string $username, string $password): bool
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = $u['id'];
        return true;
    }
    return false;
}

/* ---------- 提示消息 ---------- */

function set_flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function check_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('表单已过期或校验失败，请返回重试。');
    }
}

/* ---------- 文件上传 ---------- */

/**
 * 保存单个上传文件
 * @return array{ok:bool,error?:string,file?:string,mime?:string}
 */
function save_upload(array $file, string $subdir, array $allowedExt, int $maxSize): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'file' => '']; // 没传文件不算错误
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => '文件上传失败（错误码 ' . $file['error'] . '）'];
    }
    if ($file['size'] > $maxSize) {
        return ['ok' => false, 'error' => '文件超过大小限制（' . round($maxSize / 1048576) . 'MB）'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowedExt[$ext])) {
        return ['ok' => false, 'error' => '不支持的文件类型：.' . h($ext)];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedMimes = array_values(array_unique($allowedExt));
    // 部分录音容器 mime 有差异，宽松校验同大类
    $mimeOk = in_array($mime, $allowedMimes, true)
        || (strpos($mime, 'audio/') === 0 && isset($allowedExt[$ext]) && in_array('audio/mpeg', $allowedMimes, true))
        || (strpos($mime, 'image/') === 0 && in_array('image/jpeg', $allowedMimes, true));
    if (!$mimeOk) {
        return ['ok' => false, 'error' => '文件内容与类型不符（检测到 ' . h($mime) . '）'];
    }

    $dir = UPLOAD_PATH . '/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['ok' => false, 'error' => '文件保存失败，请检查 uploads 目录权限'];
    }
    return ['ok' => true, 'file' => $name, 'mime' => $mime];
}

/**
 * 将多文件 input (name="photos[]") 归一化为数组
 */
function normalize_file_array(string $field): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) {
        return [];
    }
    $out = [];
    foreach ($_FILES[$field]['name'] as $i => $name) {
        if ($_FILES[$field]['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = [
            'name' => $name,
            'type' => $_FILES[$field]['type'][$i],
            'tmp_name' => $_FILES[$field]['tmp_name'][$i],
            'error' => $_FILES[$field]['error'][$i],
            'size' => $_FILES[$field]['size'][$i],
        ];
    }
    return $out;
}

/* ---------- 文本 / 文件辅助 ---------- */

function excerpt(string $text, int $len = 110): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $len) {
        return mb_substr($text, 0, $len, 'UTF-8') . '……';
    }
    if (strlen($text) > $len * 3) {
        return substr($text, 0, $len * 3) . '……';
    }
    return $text;
}

/**
 * 安全删除 uploads 内文件（防路径穿越）
 */
function delete_storage_file(string $subdir, string $name): void
{
    if ($name === '' || strpos($name, '/') !== false || strpos($name, '..') !== false) {
        return;
    }
    $path = realpath(UPLOAD_PATH . '/' . $subdir . '/' . $name);
    $base = realpath(UPLOAD_PATH . '/' . $subdir);
    if ($path && $base && strpos($path, $base) === 0 && is_file($path)) {
        @unlink($path);
    }
}

/* ---------- 故事可见性 ---------- */

/**
 * 根据登录状态返回可见性 SQL 片段和绑定参数
 * @return array{0:string,1:array}
 */
function visibility_scope(string $alias = ''): array
{
    $col = $alias ? "{$alias}.visibility" : 'visibility';
    if (is_logged_in()) {
        return ['1=1', []]; // 家人可见全部
    }
    return ["{$col} = 'public'", []];
}

/* ---------- 年代展示 ---------- */

function year_label(?int $year): string
{
    return $year ? ((int)$year . ' 年') : '年代不详';
}

function life_span(array $elder): string
{
    $parts = [];
    if (!empty($elder['birth_year'])) {
        $parts[] = (int)$elder['birth_year'] . ' 年生';
    }
    if (!empty($elder['death_year'])) {
        $parts[] = (int)$elder['death_year'] . ' 年离世';
    }
    return implode(' · ', $parts);
}

function era_text(?int $year): string
{
    if (!$year) {
        return '';
    }
    $y = (int)$year;
    if ($y >= 1912 && $y <= 1949) {
        return '民国年间';
    }
    if ($y < 1912) {
        return '清末';
    }
    $decadeStart = floor($y / 10) * 10;
    return $decadeStart . ' 年代';
}

/* ---------- mbstring 兼容（未安装 mbstring 扩展时的简易实现） ---------- */
if (!function_exists('mb_strlen')) {
    function mb_strlen($s, $enc = null) {
        preg_match_all('/./us', (string)$s, $m);
        return count($m[0]);
    }
    function mb_substr($s, $start, $length = null, $enc = null) {
        preg_match_all('/./us', (string)$s, $m);
        $chars = $m[0];
        if ($length === null) {
            $slice = array_slice($chars, $start);
        } else {
            $slice = array_slice($chars, $start, $length);
        }
        return implode('', $slice);
    }
}
