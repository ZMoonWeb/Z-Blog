<p align="center">
  <img src="public/assets/img/Z-Blog.png" alt="Z-Blog" width="900">
</p>

<p align="center">
  <a href="./README.md">简体中文</a>
  <span> | </span>
  <a href="./README-zh-TW.md">繁體中文</a>
</p>

<p align="center">
  <a href="https://github.com/ZMoonWeb/Z-Blog/releases/latest"><img alt="Release" src="https://img.shields.io/github/v/release/ZMoonWeb/Z-Blog?style=flat-square&label=release&labelColor=475569&color=38bdf8"></a>&nbsp;&nbsp;&nbsp;
  <a href="https://github.com/ZMoonWeb/Z-Blog/releases/latest"><img alt="Release downloads" src="https://img.shields.io/github/downloads/ZMoonWeb/Z-Blog/total?style=flat-square&label=release%20downloads&labelColor=475569&color=0ea5e9&cacheSeconds=300"></a>&nbsp;&nbsp;&nbsp;
  <a href="https://github.com/ZMoonWeb/Z-Blog/blob/main/LICENSE"><img alt="License MIT" src="https://img.shields.io/badge/license-MIT-green?style=flat-square&labelColor=475569"></a>&nbsp;&nbsp;&nbsp;
  <a href="https://github.com/ZMoonWeb/Z-Blog/stargazers"><img alt="Stars" src="https://img.shields.io/github/stars/ZMoonWeb/Z-Blog?style=flat-square&label=stars&labelColor=475569&color=facc15"></a>&nbsp;&nbsp;&nbsp;
  <a href="https://github.com/ZMoonWeb/Z-Blog/network/members"><img alt="Forks" src="https://img.shields.io/github/forks/ZMoonWeb/Z-Blog?style=flat-square&label=forks&labelColor=475569&color=c084fc"></a>&nbsp;&nbsp;&nbsp;
  <a href="https://github.com/ZMoonWeb/Z-Blog/issues"><img alt="Issues" src="https://img.shields.io/github/issues/ZMoonWeb/Z-Blog?style=flat-square&label=issues&labelColor=475569&color=fb7185"></a>&nbsp;&nbsp;&nbsp;
  <img alt="Language PHP" src="https://img.shields.io/badge/language-PHP-777BB4?style=flat-square&logo=php&logoColor=white&labelColor=475569">&nbsp;&nbsp;&nbsp;
  <img alt="Database MySQL" src="https://img.shields.io/badge/database-MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white&labelColor=475569">&nbsp;&nbsp;&nbsp;
  <img alt="Last commit" src="https://img.shields.io/github/last-commit/ZMoonWeb/Z-Blog?style=flat-square&label=last%20commit&labelColor=475569&color=2dd4bf">&nbsp;&nbsp;&nbsp;
  <img alt="Repo size" src="https://img.shields.io/github/repo-size/ZMoonWeb/Z-Blog?style=flat-square&label=repo%20size&labelColor=475569&color=22d3ee">
</p>

## 简介

Z-Blog 是一个从零搭建的 PHP 博客系统，定位是轻量、现代、易部署。它包含前台阅读体验、后台内容管理、留言互动、公告管理、活动审计、站点个性化配置、日志记录和版本更新检测，适合个人博客、作品记录、项目日志和小型内容站点。

项目不依赖大型框架，基于原生 PHP、Composer 和模块化目录组织代码。核心能力放在 `app/Core`，业务能力拆分到 `app/Modules`，数据模型保留在 `app/Models`，视图和组件放在 `resources/views`。

## 界面预览

**加载动画**

<p align="center">
  <span style="display: inline-block; width: 48%; text-align: center; vertical-align: top;">
    <img src="public/assets/img/loading-light.png" alt="加载动画浅色模式" width="100%"><br>
    <sub>浅色模式</sub>
  </span>
  <span style="display: inline-block; width: 48%; text-align: center; vertical-align: top;">
    <img src="public/assets/img/loading-dark.png" alt="加载动画暗色模式" width="100%"><br>
    <sub>暗色模式</sub>
  </span>
</p>

**欢迎页**

<p align="center">
  <span style="display: inline-block; width: 48%; text-align: center; vertical-align: top;">
    <img src="public/assets/img/welcome-light.png" alt="欢迎页浅色模式" width="100%"><br>
    <sub>浅色模式</sub>
  </span>
  <span style="display: inline-block; width: 48%; text-align: center; vertical-align: top;">
    <img src="public/assets/img/welcome-dark.png" alt="欢迎页暗色模式" width="100%"><br>
    <sub>暗色模式</sub>
  </span>
</p>

**首页**

<p align="center">
  <span style="display: inline-block; width: 48%; text-align: center; vertical-align: top;">
    <img src="public/assets/img/home-light.png" alt="首页浅色模式" width="100%"><br>
    <sub>浅色模式</sub>
  </span>
  <span style="display: inline-block; width: 48%; text-align: center; vertical-align: top;">
    <img src="public/assets/img/home-dark.png" alt="首页暗色模式" width="100%"><br>
    <sub>暗色模式</sub>
  </span>
</p>

<h2 style="margin-top: 24px; margin-bottom: 0; padding-bottom: 0;">赞助商</h2>
<details style="margin-top: 0;"><summary><strong>想成为赞助商？点我了解</strong></summary>
欢迎通过邮件联系：<a href="mailto:3635716439@qq.com">3635716439@qq.com</a>
</details>
<br>
<table>
  <tr>
    <th align="left">图标</th>
    <th align="left">名称</th>
    <th align="left">简介</th>
    <th align="left">跳转</th>
  </tr>
  <tr>
    <td><img src="public/assets/img/ZMoon.png" width="44" alt="筑梦科技"></td>
    <td>筑梦科技</td>
    <td>开发商</td>
    <td><a href="https://qm.qq.com/q/DYI7jJPTDq">点我了解详情</a></td>
  </tr>
</table>

## 功能特性

- 前台页面：首页、文章详情、热门排行、公告、留言板、关于页和个人主页。
- 内容管理：文章发布、编辑、删除、封面图、标签、分类和 Markdown/HTML 内容渲染。
- 互动能力：文章浏览记录、点赞、评论、留言发布、留言详情和后台回复。
- 后台管理：仪表盘、文章、分类、公告、留言、互动记录、活动记录、前台设置、后台设置和个人资料。
- 管理审计：后台关键操作会写入管理员活动记录，并保留操作对象、状态、详情和变更摘要。
- 个性化配置：站点 Logo、顶部头像、侧栏头像、侧栏背景、个人主页背景、座右铭、复制按钮、首页轮播和关于页内容。
- 安全能力：CSRF 校验、安全响应头、Session 配置、登录失败锁定、密码策略、HTML 清洗和图片上传校验。
- 安装向导：支持环境检测、`.env` 配置检测、数据库连接检测和安装状态写入。
- 日志系统：支持每日 JSON 日志、异常记录、请求日志和日志保留天数配置。
- 更新检测：后台自动检测远端版本，发现新版本后展示更新提示并跳转到 GitHub Release。
- 主题体验：前后台明暗主题适配，内置 MiSans 字体资源。

## 技术栈

| 类型 | 说明 |
| --- | --- |
| 后端 | PHP 8.1+ |
| 数据库 | MySQL / MariaDB |
| 依赖管理 | Composer |
| 架构 | 原生 PHP + Core + Modules + Models |
| 路由 | 项目内置模块路由 |
| 配置加载 | `vlucas/phpdotenv` |
| Markdown | `league/commonmark` |
| 前端 | 原生 HTML / CSS / JavaScript |
| 前端构建 | Tailwind CSS CLI，可选 |
| 字体 | MiSans |

## 环境要求

- PHP `>= 8.1`
- MySQL 或 MariaDB
- Composer
- PHP 扩展：`pdo`、`pdo_mysql`、`mbstring`
- 建议启用 `fileinfo` 扩展，用于更准确的上传 MIME 校验
- Web 服务器：Nginx、Apache、宝塔面板等均可

生产环境请将站点运行目录指向 `public/`。

## 快速开始

下载最新版发布包：

```text
https://github.com/ZMoonWeb/Z-Blog/releases/latest
```

将 ZIP 压缩包上传到服务器站点目录并解压。Release 包建议包含 `vendor/` 依赖目录；如果你是从源码包或 Git 仓库部署，请先在项目根目录执行：

```bash
composer install --no-dev --optimize-autoloader
```

配置站点域名，并将网站运行目录设置为：

```text
public
```

Nginx 伪静态配置：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

创建配置文件：

```bash
cp .env.example .env
```

编辑 `.env`，至少填写应用名称、站点地址、数据库、邮件、日志和时区相关配置。`APP_VERSION` 用于后台更新检测，请跟随当前发布版本填写。

浏览器访问：

```text
https://你的域名/install
```

按照安装向导完成环境检测、数据库连接和管理员账号创建。

## 关键配置

| 配置项 | 说明 |
| --- | --- |
| `APP_NAME` | 应用名称 |
| `APP_VERSION` | 当前 Blog 版本号 |
| `APP_ENV` | 运行环境 |
| `APP_DEBUG` | 是否开启调试 |
| `APP_URL` | 站点地址 |
| `APP_TIMEZONE` | 默认时区 |
| `APP_UPDATE_CHECK_URL` | 更新检测接口地址 |
| `APP_UPDATE_RELEASE_URL` | 新版本跳转地址 |
| `APP_POSTS_PER_PAGE` | 首页文章分页数量 |
| `DB_HOST` | 数据库主机 |
| `DB_PORT` | 数据库端口 |
| `DB_DATABASE` | 数据库名称 |
| `DB_USERNAME` | 数据库用户名 |
| `DB_PASSWORD` | 数据库密码 |
| `DB_CHARSET` | 数据库字符集 |
| `MAIL_MAILER` | 邮件驱动 |
| `MAIL_HOST` | SMTP 服务器 |
| `MAIL_PORT` | SMTP 端口 |
| `MAIL_USERNAME` | SMTP 用户名 |
| `MAIL_PASSWORD` | SMTP 密码 |
| `MAIL_ENCRYPTION` | SMTP 加密方式 |
| `MAIL_FROM_ADDRESS` | 发件邮箱 |
| `MAIL_FROM_NAME` | 发件人名称 |
| `LOG_CHANNEL` | 日志通道 |
| `LOG_LEVEL` | 日志级别 |
| `LOG_PATH` | 日志文件路径 |
| `LOG_MAX_FILES` | 日志保留文件数 |
| `LOG_REQUESTS` | 是否记录请求日志 |

实际部署时不要提交 `.env`，只提交 `.env.example` 作为模板。

## 目录结构

```text
Z-Blog/
├── app/
│   ├── Core/                 # 应用核心：配置、路由、HTTP、日志、安全、数据库、视图
│   ├── Models/               # 数据模型
│   └── Modules/              # 业务模块：文章、分类、公告、留言、设置、个人资料等
├── config/                   # 应用、数据库、邮件、日志、安全、会话、上传、更新和模块配置
├── database/
│   └── migrations/           # 数据库迁移文件
├── public/                   # Web 根目录
│   ├── assets/               # CSS、JavaScript、图片、字体等静态资源
│   ├── uploads/              # 用户上传目录
│   └── index.php             # 入口文件
├── resources/
│   ├── assets/               # 前端源文件
│   └── views/                # 页面模板和组件
├── routes/                   # 全局路由入口，主要业务路由由模块注册
├── storage/                  # 缓存、日志等运行时文件
├── vendor/                   # Composer 依赖
├── .env.example              # 环境变量模板
├── composer.json             # Composer 配置
├── composer.lock             # 依赖锁定文件
├── package.json              # 前端构建脚本
└── install.php               # 安装向导
```

## 模块结构

业务代码集中在 `app/Modules`。一个完整模块通常包含：

```text
ModuleName/
├── Controllers/        # 请求入口
├── Services/           # 业务逻辑
├── Repositories/       # 数据访问封装
├── Requests/           # 表单校验
└── routes.php          # 模块路由
```

不是每个模块都必须包含所有目录，小模块可以只保留 `routes.php` 和 `Services`。模块是否启用由 `config/modules.php` 控制。

## 部署说明

1. 上传项目到服务器。
2. 如果发布包没有包含 `vendor/`，执行 `composer install --no-dev --optimize-autoloader`。
3. 复制 `.env.example` 为 `.env` 并填写配置。
4. 将站点运行目录设置为 `public/`。
5. 配置伪静态，将请求重写到 `public/index.php`。
6. 确保 `storage/` 和 `public/uploads/` 可写。
7. 访问 `/install` 完成安装。

Nginx 可使用快速开始中的 `try_files` 配置。Apache 环境请自行添加等效重写规则，将不存在的文件请求转发到 `public/index.php`。

## 前端资源

项目默认提交编译后的 CSS 和 JavaScript，普通部署不需要构建前端资源。如果修改 `resources/assets/css/tailwind.css`，可以使用：

```bash
npm install
npm run build:tailwind
```

开发时监听 Tailwind：

```bash
npm run watch:tailwind
```

## 更新机制

后台首页会自动检查当前版本。检测到新版本时，管理员可以在提示弹窗中查看提示，并前往 GitHub Release 下载新版。

更新检测相关字段：

- 当前版本读取 `.env` 中的 `APP_VERSION`。
- 检测接口读取 `.env` 中的 `APP_UPDATE_CHECK_URL`。
- 跳转地址优先使用远端返回的 `release_url`，也可以通过 `APP_UPDATE_RELEASE_URL` 配置默认地址。
- 检测接口会发送 `action=check_update`、当前版本和请求时间。
- 发现新版本后会再次发送 `action=get_update_notes` 获取更新说明。
- 项目不会自动覆盖服务器文件，更新包下载和覆盖由管理员自行处理。

## 安全建议

- 不要提交 `.env`、日志文件、缓存文件和真实上传文件。
- 生产环境关闭 `APP_DEBUG`。
- 数据库账号建议使用最小权限。
- 后台账号请使用强密码。
- 定期备份数据库和 `public/uploads/`。
- 发布新版本前确认 `.env.example` 保持最新。

## 贡献

欢迎提交 Issue、建议和 Pull Request。提交代码前建议先确认：

- 代码风格与项目现有结构保持一致。
- 配置项同步更新 `.env.example`。
- 涉及数据库或安装流程的改动需要同步安装向导。
- 涉及后台操作的改动需要考虑活动记录。

## 许可证

Z-Blog 基于 [MIT License](LICENSE) 开源。
