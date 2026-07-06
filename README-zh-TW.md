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
  <a href="https://github.com/ZMoonWeb/Z-Blog/blob/main/LICENSE"><img alt="License" src="https://img.shields.io/github/license/ZMoonWeb/Z-Blog?style=flat-square&label=license&labelColor=475569&color=4ade80"></a>&nbsp;&nbsp;&nbsp;
  <a href="https://github.com/ZMoonWeb/Z-Blog/stargazers"><img alt="Stars" src="https://img.shields.io/github/stars/ZMoonWeb/Z-Blog?style=flat-square&label=stars&labelColor=475569&color=facc15"></a>&nbsp;&nbsp;&nbsp;
  <a href="https://github.com/ZMoonWeb/Z-Blog/network/members"><img alt="Forks" src="https://img.shields.io/github/forks/ZMoonWeb/Z-Blog?style=flat-square&label=forks&labelColor=475569&color=c084fc"></a>&nbsp;&nbsp;&nbsp;
  <a href="https://github.com/ZMoonWeb/Z-Blog/issues"><img alt="Issues" src="https://img.shields.io/github/issues/ZMoonWeb/Z-Blog?style=flat-square&label=issues&labelColor=475569&color=fb7185"></a>&nbsp;&nbsp;&nbsp;
  <img alt="Top language" src="https://img.shields.io/github/languages/top/ZMoonWeb/Z-Blog?style=flat-square&label=language&labelColor=475569&color=a78bfa">&nbsp;&nbsp;&nbsp;
  <img alt="Last commit" src="https://img.shields.io/github/last-commit/ZMoonWeb/Z-Blog?style=flat-square&label=last%20commit&labelColor=475569&color=2dd4bf">&nbsp;&nbsp;&nbsp;
  <img alt="Repo size" src="https://img.shields.io/github/repo-size/ZMoonWeb/Z-Blog?style=flat-square&label=repo%20size&labelColor=475569&color=22d3ee">
</p>

## 簡介

Z-Blog 是一個從零搭建的 PHP 部落格系統，定位是輕量、現代、易部署。它包含前台閱讀體驗、後台內容管理、留言互動、公告管理、活動審計、站點個人化配置和版本更新檢測，適合個人部落格、作品記錄、專案日誌和小型內容站點。

專案不依賴大型框架，核心結構清晰，使用原生 PHP MVC 組織程式碼，並透過 Composer 管理必要依賴。

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

## 功能特色

- 文章發布、編輯、刪除、封面圖、分類管理。
- 首頁、熱門、公告、留言板、關於頁、個人主頁和文章詳情頁。
- Markdown 渲染支援，基於 `league/commonmark`。
- 按讚、評論、留言等互動能力。
- 後台儀表板、文章管理、分類管理、公告管理、留言管理。
- 管理員活動記錄，後台關鍵操作會被記錄。
- 前台設定、後台設定和個人資料分區管理。
- 圖片上傳、頭像配置、站點 Logo、側欄背景、個人主頁背景等個人化配置。
- 安裝精靈、環境檢測、資料庫連線檢測。
- 日誌與快取目錄，便於部署後排查問題。
- 後台自動檢測 GitHub 新版本，並提示前往下載。
- 明暗主題適配，內建 MiSans 字體資源。

## 技術棧

| 類型 | 說明 |
| --- | --- |
| 後端 | PHP 8.1+ |
| 資料庫 | MySQL / MariaDB |
| 依賴管理 | Composer |
| 配置載入 | `vlucas/phpdotenv` |
| Markdown | `league/commonmark` |
| 前端 | 原生 HTML / CSS / JavaScript |
| 字體 | MiSans |

## 環境需求

- PHP `>= 8.1`
- MySQL 或 MariaDB
- Composer
- PHP 擴充：`pdo`、`pdo_mysql`、`mbstring`
- Web 伺服器：Nginx、Apache、寶塔面板等均可

生產環境請將站點執行目錄指向 `public/`。

## 快速開始

下載最新版發布包：

```text
https://github.com/ZMoonWeb/Z-Blog/releases/latest
```

將 ZIP 壓縮包上傳到伺服器站點目錄並解壓縮。

配置站點網域，並將網站執行目錄設定為：

```text
public
```

Nginx 偽靜態配置：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

建立配置檔案：

```bash
cp .env.example .env
```

編輯 `.env`，至少填寫資料庫、站點地址、郵件和時區相關配置。

瀏覽器訪問：

```text
https://你的域名/install
```

按照安裝精靈完成環境檢測、資料庫連線和管理員帳號建立。

## 關鍵配置

| 配置項 | 說明 |
| --- | --- |
| `APP_NAME` | 應用名稱 |
| `APP_VERSION` | 當前 Blog 版本號 |
| `APP_ENV` | 執行環境 |
| `APP_DEBUG` | 是否開啟除錯 |
| `APP_URL` | 站點地址 |
| `APP_TIMEZONE` | 預設時區 |
| `APP_UPDATE_CHECK_URL` | 更新檢測介面地址 |
| `APP_UPDATE_RELEASE_URL` | 新版本跳轉地址 |
| `DB_HOST` | 資料庫主機 |
| `DB_DATABASE` | 資料庫名稱 |
| `DB_USERNAME` | 資料庫使用者名稱 |
| `DB_PASSWORD` | 資料庫密碼 |
| `MAIL_HOST` | SMTP 伺服器 |
| `MAIL_USERNAME` | SMTP 使用者名稱 |
| `MAIL_PASSWORD` | SMTP 密碼 |
| `LOG_PATH` | 日誌檔案路徑 |
| `LOG_REQUESTS` | 是否記錄請求日誌 |

實際部署時不要提交 `.env`，只提交 `.env.example` 作為模板。

## 目錄結構

```text
Z-Blog/
├── app/                 # 應用核心、控制器、模型
├── config/              # 應用、資料庫、日誌、郵件配置
├── public/              # Web 根目錄
│   ├── assets/          # 靜態資源
│   ├── uploads/         # 使用者上傳目錄
│   └── index.php        # 入口檔案
├── resources/views/     # 頁面模板
├── storage/             # 快取、日誌等執行時檔案
├── vendor/              # Composer 依賴
├── .env.example         # 環境變數模板
├── composer.json        # Composer 配置
├── composer.lock        # 依賴鎖定檔案
└── install.php          # 安裝精靈
```

## 部署說明

1. 上傳專案到伺服器。
2. 執行 `composer install --no-dev --optimize-autoloader`。
3. 複製 `.env.example` 為 `.env` 並填寫配置。
4. 將站點執行目錄設定為 `public/`。
5. 確保 `storage/` 和 `public/uploads/` 可寫。
6. 訪問 `/install` 完成安裝。

如果使用 Apache，專案已包含 `public/.htaccess`。如果使用 Nginx，請將請求重寫到 `public/index.php`。

## 更新機制

後台首頁會自動檢查目前版本。檢測到新版本時，管理員可以在提示彈窗中前往 GitHub 下載新版。

更新檢測相關欄位：

- 目前版本讀取 `.env` 中的 `APP_VERSION`。
- 檢測介面讀取 `.env` 中的 `APP_UPDATE_CHECK_URL`。
- 跳轉地址優先使用遠端返回的 `release_url`，也可以透過 `APP_UPDATE_RELEASE_URL` 配置預設地址。

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
- 配置項同步更新 `.env.example`。
- 涉及資料庫或安裝流程的改動需要同步安裝精靈。
- 涉及後台操作的改動需要考慮活動記錄。

## 授權

Z-Blog 基於 [MIT License](LICENSE) 開源。
