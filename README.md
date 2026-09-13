# 家音 · 家族口述史网站

一个用 **PHP + SQLite** 编写的家族口述史站点，无需复杂环境即可部署：

- 📝 **后台内容管理（PHP）**：录入长辈姓名、年代、地点、录音文字稿、录音文件与照片
- 🧭 **前台浏览**：按长辈浏览、按年份时间线浏览，支持长辈 / 年代 / 关键词筛选
- ✉️ **亲友投稿审核**：任何人可提交补充故事，管理员审核通过后才发布
- 🔒 **隐私分级**：每个故事可设为「🌐 公开」或「🔒 仅家人可见」；私密故事的文字稿、录音、照片全部需要家人登录，且文件经 `media.php` 鉴权读取，无法绕过
- 👨‍👩‍👧 **家人账号**：管理员创建家人账号用于查看私密内容；账号密码使用 `password_hash` 加密存储
- 🛡️ 表单 CSRF 防护、上传白名单 + MIME 校验、SQL 全部使用预处理、删除二次确认、蜜罐反垃圾

## 目录结构

```
├── index.php            首页
├── elders.php           长辈名录
├── elder.php            长辈主页（按人物浏览）
├── stories.php          时间线（按年代浏览 + 筛选搜索）
├── story.php            故事详情（文字稿 / 录音播放 / 照片墙）
├── submit.php           亲友提交补充故事（进入审核队列）
├── submit_done.php      提交成功页
├── login.php/logout.php 家人登录 / 退出
├── media.php            上传文件鉴权入口（私密内容未登录不可读）
├── admin/               后台（仅管理员）
│   ├── index.php            概览
│   ├── elders.php           长辈列表/删除
│   ├── elder_edit.php       录入/编辑长辈（头像上传）
│   ├── stories.php          故事列表（筛选）
│   ├── story_edit.php       故事编辑（录音 + 多张照片 + 可见性）
│   ├── submissions.php      投稿队列（待审/已通过/已拒绝）
│   ├── submission_edit.php  审核：通过即发布为故事 / 拒绝
│   ├── users.php            家人账号管理
│   └── user_edit.php        新建/编辑账号、改密码
├── parts/               前台公共模板
├── assets/css/          样式
├── data/                SQLite 数据库（自动生成，勿外传）
└── uploads/             上传文件（avatars/photos/audio/submissions）
```

## 快速运行

要求 PHP 8.0+（带 pdo_sqlite、fileinfo 扩展，Debian/Ubuntu：
`apt install php-cli php-sqlite3`）。

```bash
cd 项目目录
php -S 0.0.0.0:8000
# 浏览器打开 http://localhost:8000
```

首次访问会自动创建 `data/family.sqlite` 与默认管理员：

- 用户名：`admin`
- 密码：`family2026`
- **登录后请立即到「后台 → 家人账号」修改密码！**

## 使用流程

1. 登录后台 `admin/`，先在「长辈管理」录入长辈（姓名、生卒年、年代、籍贯、简介、头像）。
2. 在「故事管理 → 新增故事」中整理一段口述史：标题、发生年份、地点、文字稿、录音文件、多张照片，并选择：
   - **公开**：所有访客可见；
   - **仅家人可见**：只有登录的家人账号能看到。
3. 亲友在前台「补充故事」页投稿（可附一张老照片、留下联系方式），内容进入「投稿审核」队列。
4. 管理员在审核页确认长辈归属、标题、年份、可见性后点「通过并发布」，即生成正式故事；之后还能在故事编辑页继续润色文字、补充录音和照片。也可以「拒绝」并记录原因，之后可改判。

## 生产部署（Apache / Nginx）

- Apache：项目自带 `.htaccess`，已禁止直接访问 `data/` 和 `uploads/`。确保 `AllowOverride All`。
- Nginx：参考 `nginx-family.conf.example`，必须拒绝 `/data/` 与 `/uploads/` 的直接访问。
- `data/` 与 `uploads/` 需要 PHP 运行身份的写权限（如 `chown -R www-data:www-data data uploads`）。
- 建议仅对家人网络开放后台，或在前面加一层 HTTP Basic Auth / VPN。

## 备份

只需备份两个东西：`data/family.sqlite`（全部文字数据）与 `uploads/`（录音、照片）。
