<?php
/**
 * 数据库连接与初始化（SQLite，首次访问自动建库建表）
 */
require_once __DIR__ . '/config.php';

if (!is_dir(DATA_PATH)) {
    mkdir(DATA_PATH, 0775, true);
}

$pdo = new PDO('sqlite:' . DB_PATH);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE,
    display_name  TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    role          TEXT NOT NULL DEFAULT 'family' CHECK (role IN ('admin','family')),
    created_at    TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS elders (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL,
    gender      TEXT DEFAULT '' CHECK (gender IN ('','male','female')),
    birth_year  INTEGER,
    death_year  INTEGER,
    era         TEXT DEFAULT '',          -- 年代，如：1920年代 / 民国
    hometown    TEXT DEFAULT '',          -- 籍贯
    bio         TEXT DEFAULT '',          -- 简介
    photo_file  TEXT DEFAULT '',          -- 头像文件（avatars 目录）
    created_at  TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS stories (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    elder_id      INTEGER NOT NULL REFERENCES elders(id) ON DELETE CASCADE,
    title         TEXT NOT NULL,
    story_year    INTEGER,                -- 故事发生年份，可空
    location      TEXT DEFAULT '',        -- 地点
    era           TEXT DEFAULT '',        -- 年代标签，如：六十年代
    transcript    TEXT DEFAULT '',        -- 录音文字稿
    audio_file    TEXT DEFAULT '',        -- 录音文件（audio 目录）
    audio_mime    TEXT DEFAULT '',
    visibility    TEXT NOT NULL DEFAULT 'public' CHECK (visibility IN ('public','family')),
    status        TEXT NOT NULL DEFAULT 'published' CHECK (status IN ('published','archived')),
    created_by    INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at    TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    updated_at    TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS photos (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    story_id    INTEGER NOT NULL REFERENCES stories(id) ON DELETE CASCADE,
    file_name   TEXT NOT NULL,            -- uploads/photos 下文件名
    mime        TEXT DEFAULT '',
    caption     TEXT DEFAULT '',
    sort_order  INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS submissions (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    elder_id       INTEGER REFERENCES elders(id) ON DELETE SET NULL,
    contributor    TEXT NOT NULL DEFAULT '',   -- 提交人姓名
    contact        TEXT DEFAULT '',            -- 联系方式
    relation       TEXT DEFAULT '',            -- 与长辈关系
    title          TEXT NOT NULL,
    story_year     INTEGER,
    location       TEXT DEFAULT '',
    content        TEXT NOT NULL,
    photo_file     TEXT DEFAULT '',            -- uploads/submissions 下文件名
    photo_mime     TEXT DEFAULT '',
    photo_caption  TEXT DEFAULT '',
    status         TEXT NOT NULL DEFAULT 'pending'
                   CHECK (status IN ('pending','approved','rejected')),
    review_note    TEXT DEFAULT '',
    reviewed_by    INTEGER REFERENCES users(id) ON DELETE SET NULL,
    reviewed_at    TEXT,
    new_story_id   INTEGER,                   -- 通过后生成的 story id
    created_at     TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE INDEX IF NOT EXISTS idx_stories_elder ON stories(elder_id);
CREATE INDEX IF NOT EXISTS idx_stories_year ON stories(story_year);
CREATE INDEX IF NOT EXISTS idx_photos_story ON photos(story_id);
CREATE INDEX IF NOT EXISTS idx_submissions_status ON submissions(status);
");

// 首次初始化：写入默认管理员
$count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count === 0) {
    $stmt = $pdo->prepare(
        'INSERT INTO users (username, display_name, password_hash, role) VALUES (?,?,?,?)'
    );
    $stmt->execute([
        DEFAULT_ADMIN_USER,
        '家族管理员',
        password_hash(DEFAULT_ADMIN_PASS, PASSWORD_DEFAULT),
        'admin',
    ]);
}

// 保护上传目录：写入 Apache/Nginx 说明与禁止直接执行
$ht = UPLOAD_PATH . '/.htaccess';
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0775, true);
}
if (!file_exists($ht)) {
    @file_put_contents($ht, "Options -ExecCGI\nphp_flag engine off\n");
}
// 数据库目录禁止访问
@file_put_contents(DATA_PATH . '/.htaccess', "Require all denied\nDeny from all\n");
