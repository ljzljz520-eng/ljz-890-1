<?php
/**
 * 受保护的媒体文件入口
 * 所有 uploads 下的文件都建议通过此入口访问，以便按可见性控制
 *   media.php?kind=avatars&f=xxx.jpg
 *   media.php?kind=photos&f=xxx.jpg      (检查所属故事可见性)
 *   media.php?kind=audio&f=xxx.mp3       (检查所属故事可见性)
 *   media.php?kind=submissions&f=xxx.jpg (仅管理员)
 */
require_once __DIR__ . '/functions.php';

$allowedKinds = ['avatars', 'photos', 'audio', 'submissions'];
$kind = $_GET['kind'] ?? '';
$file = $_GET['f'] ?? '';

if (!in_array($kind, $allowedKinds, true) || $file === '' || strpos($file, '/') !== false || strpos($file, '..') !== false) {
    http_response_code(400);
    exit('无效的请求');
}

// 访问控制
if ($kind === 'submissions') {
    require_admin();
} elseif ($kind === 'photos') {
    $stmt = $pdo->prepare('SELECT s.visibility FROM photos p JOIN stories s ON s.id = p.story_id WHERE p.file_name = ?');
    $stmt->execute([$file]);
    $vis = $stmt->fetchColumn();
    if ($vis === false) {
        http_response_code(404);
        exit('图片不存在');
    }
    if ($vis === 'family' && !is_logged_in()) {
        http_response_code(403);
        exit('该图片仅家人可见');
    }
} elseif ($kind === 'audio') {
    $stmt = $pdo->prepare('SELECT visibility FROM stories WHERE audio_file = ?');
    $stmt->execute([$file]);
    $vis = $stmt->fetchColumn();
    if ($vis === false) {
        http_response_code(404);
        exit('录音不存在');
    }
    if ($vis === 'family' && !is_logged_in()) {
        http_response_code(403);
        exit('该录音仅家人可见');
    }
}

$path = UPLOAD_PATH . '/' . $kind . '/' . $file;
$real = realpath($path);
$base = realpath(UPLOAD_PATH . '/' . $kind);
if (!$real || !$base || strpos($real, $base) !== 0 || !is_file($real)) {
    http_response_code(404);
    exit('文件不存在');
}

$mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
          'gif' => 'image/gif', 'webp' => 'image/webp',
          'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac',
          'ogg' => 'audio/ogg', 'wav' => 'audio/wav'];
$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
$mime = $mimes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($real));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($real);
