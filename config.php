<?php
/**
 * 家族口述史 - 全局配置
 */
date_default_timezone_set('Asia/Shanghai');

// 使用项目内可写目录保存会话（避免系统 session 目录权限问题）
$sessDir = __DIR__ . '/data/sessions';
if (!is_dir($sessDir)) {
    @mkdir($sessDir, 0775, true);
}
if (is_dir($sessDir) && is_writable($sessDir)) {
    session_save_path($sessDir);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 站点名（前台标题用）
const SITE_NAME = '家音 · 家族口述史';

// 自动推导站点根目录 URL（适配放在根目录或子目录部署）
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
if (basename($scriptDir) === 'admin') {
    $scriptDir = dirname($scriptDir);
}
define('BASE_URL', rtrim($scriptDir, '/')); // 形如 "" 或 "/family-history"

// 文件路径
define('ROOT_PATH', __DIR__);
define('DATA_PATH', __DIR__ . '/data');
define('DB_PATH', DATA_PATH . '/family.sqlite');
define('UPLOAD_PATH', __DIR__ . '/uploads');

// 允许上传的文件类型
const ALLOWED_IMAGE = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
];
const ALLOWED_AUDIO = [
    'mp3' => 'audio/mpeg',
    'm4a' => 'audio/mp4',
    'aac' => 'audio/aac',
    'ogg' => 'audio/ogg',
    'wav' => 'audio/wav',
];
const MAX_IMAGE_SIZE = 8 * 1024 * 1024;  // 照片 8MB
const MAX_AUDIO_SIZE = 50 * 1024 * 1024; // 录音 50MB

// 默认管理员（仅首次初始化数据库时创建，请尽快修改密码！）
const DEFAULT_ADMIN_USER = 'admin';
const DEFAULT_ADMIN_PASS = 'family2026';
