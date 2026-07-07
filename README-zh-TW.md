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

## 簡介

Z-Blog 是一個從零搭建的 PHP 部落格系統，定位是輕量、現代、易部署。它包含前台閱讀體驗、後台內容管理、留言互動、公告管理、活動稽核、站點個性化設定、日誌記錄和版本更新檢測，適合個人部落格、作品記錄、專案日誌和小型內容站點。

專案不依賴大型框架，基於原生 PHP、Composer 和模組化目錄組織程式碼。核心能力放在 `app/Core`，業務能力拆分到 `app/Modules`，資料模型保留在 `app/Models`，視圖和元件放在 `resources/views`。

## 介面預覽

**載入動畫**

<p align="center">
  <span style="display: inline-block; width: 48%; text-align: center; vertical-align: top;">
    <img src="public/assets/img/loading-light.png" alt="載入動畫淺色模式" width="100%"><br>
    <sub>淺色模式</sub>
  </span>
  <span style="display: inline-block; width: 48%; text-align: center; vertical-align: top;">
    <img src="public/assets/img/loading-dark.png" alt="載入動畫暗色模式" width="100%"><br>
    <sub>暗色模式</sub>
  </span>
</p>

**歡迎頁**

<p align="center">
  <span style="display: inline-block; width: 48%; text-align: center; vertical-align: top;">
    <img src="public/assets/img/welcome-light.png" alt="歡迎頁淺色模式" width="100%"><br>
    <sub>淺色模式</sub>
  </span>
  <span style="display: inline-block; width: 48%; text-align: center; vertical-align: top;">
    <img src="public/assets/img/welcome-dark.png" alt="歡迎頁暗色模式" width="100%"><br>
    <sub>暗色模式</sub>
  </span>
</p>

**首頁**

<p align="center">
  <span style="display: inline-block; width: 48%; text-align: center; vertical-align: top;">
    <img src="public/assets/img/home-light.png" alt="首頁淺色模式" width="100%"><br>
    <sub>淺色模式</sub>
  </span>
  <span style="display: inline-block; width: 48%; text-align: center; vertical-align: top;">
    <img src="public/assets/img/home-dark.png" alt="首頁暗色模式" width="100%"><br>
    <sub>暗色模式</sub>
  </span>
</p>

<h2 style="margin-top: 24px; margin-bottom: 0; padding-bottom: 0;">贊助商</h2>
<details style="margin-top: 0;"><summary><strong>想成為贊助商？點我了解</strong></summary>
歡迎透過郵件聯絡：<a href="mailto:3635716439@qq.com">3635716439@qq.com</a>
</details>
<br>
<table>
  <tr>
    <th align="left">圖示</th>
    <th align="left">名稱</th>
    <th align="left">簡介</th>
    <th align="left">跳轉</th>
  </tr>
  <tr>
    <td><img src="public/assets/img/ZMoon.png" width="44" alt="築夢科技"></td>
    <td>築夢科技</td>
    <td>開發商</td>
    <td><a href="https://qm.qq.com/q/DYI7jJPTDq">點我了解詳情</a></td>
  </tr>
</table>

## 功能特性

- 前台頁面：首頁、文章詳情、熱門排行、公告、留言板、關於頁和個人主頁。
- 內容管理：文章發布、編輯、刪除、封面圖、標籤、分類和 Markdown/HTML 內容渲染。
- 互動能力：文章瀏覽記錄、點讚、評論、留言發布、留言詳情和後台回覆。
- 後台管理：儀表盤、文章、分類、公告、留言、互動記錄、活動記錄、前台設定、後台設定和個人資料。
- 管理稽核：後台關鍵操作會寫入管理員活動記錄，並保留操作對象、狀態、詳情和變更摘要。
- 個性化設定：站點 Logo、頂部頭像、側欄頭像、側欄背景、個人主頁背景、座右銘、複製按鈕、首頁輪播和關於頁內容。
- 安全能力：CSRF 校驗、安全回應標頭、Session 設定、登入失敗鎖定、密碼策略、HTML 清洗和圖片上傳校驗。
- 安裝向導：支援環境檢測、`.env` 設定檢測、資料庫連線檢測和安裝狀態寫入。
- 日誌系統：支援每日 JSON 日誌、異常記錄、請求日誌和日誌保留天數設定。
- 更新檢測：後台自動檢測遠端版本，發現新版本後展示更新提示並跳轉到 GitHub Release。
- 主題體驗：前後台明暗主題適配，內建 MiSans 字體資源。

## 技術棧

| 類型 | 說明 |
| --- | --- |
| 後端 | PHP 8.1+ |
| 資料庫 | MySQL / MariaDB |
| 依賴管理 | Composer |
| 架構 | 原生 PHP + Core + Modules + Models |
| 路由 | 專案內建模組路由 |
| 設定載入 | `vlucas/phpdotenv` |
| Markdown | `league/commonmark` |
| 前端 | 原生 HTML / CSS / JavaScript |
| 前端建置 | Tailwind CSS CLI，可選 |
| 字體 | MiSans |

## 環境需求

- PHP `>= 8.1`
- MySQL 或 MariaDB
- Composer
- PHP 擴充：`pdo`、`pdo_mysql`、`mbstring`
- 建議啟用 `fileinfo` 擴充，用於更準確的上傳 MIME 校驗
- Web 伺服器：Nginx、Apache、寶塔面板等均可

生產環境請將站點執行目錄指向 `public/`。

## 快速開始

下載最新版發布包：

```text
https://github.com/ZMoonWeb/Z-Blog/releases/latest
```

將 ZIP 壓縮包上傳到伺服器站點目錄並解壓。Release 包建議包含 `vendor/` 依賴目錄；如果你是從原始碼包或 Git 倉庫部署，請先在專案根目錄執行：

```bash
composer install --no-dev --optimize-autoloader
```

設定站點網域，並將網站執行目錄設定為：

```text
public
```

Nginx 偽靜態設定：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

建立設定檔：

```bash
cp .env.example .env
```

編輯 `.env`，至少填寫應用名稱、站點地址、資料庫、郵件、日誌和時區相關設定。`APP_VERSION` 用於後台更新檢測，請跟隨目前發布版本填寫。

瀏覽器訪問：

```text
https://你的域名/install
```

按照安裝向導完成環境檢測、資料庫連線和管理員帳號建立。

## 關鍵設定

| 設定項 | 說明 |
| --- | --- |
| `APP_NAME` | 應用名稱 |
| `APP_VERSION` | 目前 Blog 版本號 |
| `APP_ENV` | 執行環境 |
| `APP_DEBUG` | 是否開啟除錯 |
| `APP_URL` | 站點地址 |
| `APP_TIMEZONE` | 預設時區 |
| `APP_UPDATE_CHECK_URL` | 更新檢測介面地址 |
| `APP_UPDATE_RELEASE_URL` | 新版本跳轉地址 |
| `APP_POSTS_PER_PAGE` | 首頁文章分頁數量 |
| `DB_HOST` | 資料庫主機 |
| `DB_PORT` | 資料庫連接埠 |
| `DB_DATABASE` | 資料庫名稱 |
| `DB_USERNAME` | 資料庫使用者名稱 |
| `DB_PASSWORD` | 資料庫密碼 |
| `DB_CHARSET` | 資料庫字元集 |
| `MAIL_MAILER` | 郵件驅動 |
| `MAIL_HOST` | SMTP 伺服器 |
| `MAIL_PORT` | SMTP 連接埠 |
| `MAIL_USERNAME` | SMTP 使用者名稱 |
| `MAIL_PASSWORD` | SMTP 密碼 |
| `MAIL_ENCRYPTION` | SMTP 加密方式 |
| `MAIL_FROM_ADDRESS` | 寄件信箱 |
| `MAIL_FROM_NAME` | 寄件人名稱 |
| `LOG_CHANNEL` | 日誌通道 |
| `LOG_LEVEL` | 日誌等級 |
| `LOG_PATH` | 日誌檔案路徑 |
| `LOG_MAX_FILES` | 日誌保留檔案數 |
| `LOG_REQUESTS` | 是否記錄請求日誌 |

實際部署時不要提交 `.env`，只提交 `.env.example` 作為模板。

## 目錄結構

```text
Z-Blog/
├── app/
│   ├── Core/                 # 應用核心：設定、路由、HTTP、日誌、安全、資料庫、視圖
│   ├── Models/               # 資料模型
│   └── Modules/              # 業務模組：文章、分類、公告、留言、設定、個人資料等
├── config/                   # 應用、資料庫、郵件、日誌、安全、會話、上傳、更新和模組設定
├── database/
│   └── migrations/           # 資料庫遷移檔案
├── public/                   # Web 根目錄
│   ├── assets/               # CSS、JavaScript、圖片、字體等靜態資源
│   ├── uploads/              # 使用者上傳目錄
│   └── index.php             # 入口檔案
├── resources/
│   ├── assets/               # 前端原始檔
│   └── views/                # 頁面模板和元件
├── routes/                   # 全域路由入口，主要業務路由由模組註冊
├── storage/                  # 快取、日誌等執行時檔案
├── vendor/                   # Composer 依賴
├── .env.example              # 環境變數模板
├── composer.json             # Composer 設定
├── composer.lock             # 依賴鎖定檔案
├── package.json              # 前端建置腳本
└── install.php               # 安裝向導
```

## 模組結構

業務程式碼集中在 `app/Modules`。一個完整模組通常包含：

```text
ModuleName/
├── Controllers/        # 請求入口
├── Services/           # 業務邏輯
├── Repositories/       # 資料存取封裝
├── Requests/           # 表單校驗
└── routes.php          # 模組路由
```

不是每個模組都必須包含所有目錄，小模組可以只保留 `routes.php` 和 `Services`。模組是否啟用由 `config/modules.php` 控制。

## 部署說明

1. 上傳專案到伺服器。
2. 如果發布包沒有包含 `vendor/`，執行 `composer install --no-dev --optimize-autoloader`。
3. 複製 `.env.example` 為 `.env` 並填寫設定。
4. 將站點執行目錄設定為 `public/`。
5. 設定偽靜態，將請求重寫到 `public/index.php`。
6. 確保 `storage/` 和 `public/uploads/` 可寫。
7. 訪問 `/install` 完成安裝。

Nginx 可使用快速開始中的 `try_files` 設定。Apache 環境請自行新增等效重寫規則，將不存在的檔案請求轉發到 `public/index.php`。

## 前端資源

專案預設提交編譯後的 CSS 和 JavaScript，普通部署不需要建置前端資源。如果修改 `resources/assets/css/tailwind.css`，可以使用：

```bash
npm install
npm run build:tailwind
```

開發時監聽 Tailwind：

```bash
npm run watch:tailwind
```

## 更新機制

後台首頁會自動檢查目前版本。檢測到新版本時，管理員可以在提示彈窗中查看提示，並前往 GitHub Release 下載新版。

更新檢測相關欄位：

- 目前版本讀取 `.env` 中的 `APP_VERSION`。
- 檢測介面讀取 `.env` 中的 `APP_UPDATE_CHECK_URL`。
- 跳轉地址優先使用遠端返回的 `release_url`，也可以透過 `APP_UPDATE_RELEASE_URL` 設定預設地址。
- 檢測介面會傳送 `action=check_update`、目前版本和請求時間。
- 發現新版本後會再次傳送 `action=get_update_notes` 取得更新說明。
- 專案不會自動覆蓋伺服器檔案，更新包下載和覆蓋由管理員自行處理。

## 安全建議

- 不要提交 `.env`、日誌檔案、快取檔案和真實上傳檔案。
- 生產環境關閉 `APP_DEBUG`。
- 資料庫帳號建議使用最小權限。
- 後台帳號請使用強密碼。
- 定期備份資料庫和 `public/uploads/`。
- 發布新版本前確認 `.env.example` 保持最新。

## 貢獻

歡迎提交 Issue、建議和 Pull Request。提交程式碼前建議先確認：

- 程式碼風格與專案現有結構保持一致。
- 設定項同步更新 `.env.example`。
- 涉及資料庫或安裝流程的改動需要同步安裝向導。
- 涉及後台操作的改動需要考慮活動記錄。

## 授權

Z-Blog 基於 [MIT License](LICENSE) 開源。
