<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Security\HtmlSanitizer;
use League\CommonMark\CommonMarkConverter;

class SiteContent
{
    public const DEFAULT_AVATAR = '/assets/img/ZMoon.png';
    public const DEFAULT_PROFILE_COVER = '/assets/img/backgrounds/sidebar-profile-cover.png';
    public const DEFAULT_HERO_COVER = '/assets/img/backgrounds/install-desktop.jpeg';

    private const DEFAULT_SIDEBAR_ANNOUNCEMENT = '项目已开源至[ZMoonWeb/Z-Blog](https://github.com/ZMoonWeb/Z-Blog)';
    private const LEGACY_DEFAULT_ANNOUNCEMENT = "本站基于 Z-Blog 开发，源码已开源。点我直达：https://github.com\n\n有问题欢迎通过邮箱或其他方式联系我。\n\n本站提供优质广告位，覆盖技术、生活等多领域受众，欢迎私信联系。";
    private const LEGACY_DEFAULT_ABOUT_LINKS = "GitHub|fa-brands fa-github|https://github.com\n邮箱|fa-solid fa-envelope|mailto:hello@example.com\nQQ群|fa-brands fa-qq|https://qm.qq.com/q/PE4qEHoF8W";

    public static function createTables(): void
    {
        Database::query("CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        Database::query("CREATE TABLE IF NOT EXISTS sidebar_copy_buttons (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            label VARCHAR(100) NOT NULL,
            copy_value TEXT NOT NULL,
            icon_svg MEDIUMTEXT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_active_sort (is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        Database::query("CREATE TABLE IF NOT EXISTS announcements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            level VARCHAR(20) NOT NULL DEFAULT 'normal',
            content MEDIUMTEXT NOT NULL,
            content_mode VARCHAR(20) NOT NULL DEFAULT 'text',
            is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_active_id (is_active, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::migrateAnnouncementsTable();

        Database::query("CREATE TABLE IF NOT EXISTS hero_slides (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            image_url VARCHAR(500) NOT NULL,
            link_url VARCHAR(500) NOT NULL DEFAULT '/',
            title VARCHAR(255) NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_active_sort (is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        Database::query("CREATE TABLE IF NOT EXISTS about_stat_cards (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            metric_key VARCHAR(50) NOT NULL,
            label VARCHAR(100) NOT NULL,
            description VARCHAR(255) NOT NULL DEFAULT '',
            icon_class VARCHAR(120) NOT NULL DEFAULT 'fa-solid fa-chart-simple',
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_active_sort (is_active, sort_order),
            INDEX idx_metric_key (metric_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        Database::query("CREATE TABLE IF NOT EXISTS about_feature_cards (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(120) NOT NULL,
            description VARCHAR(255) NOT NULL DEFAULT '',
            icon_class VARCHAR(120) NOT NULL DEFAULT 'fa-solid fa-sparkles',
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_active_sort (is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function seedDefaults(): void
    {
        self::createTables();

        $defaults = [
            'site_title' => 'Z-Blog',
            'site_logo' => '/assets/img/ZMoon.png',
            'site_avatar' => self::DEFAULT_AVATAR,
            'profile_avatar' => self::DEFAULT_AVATAR,
            'profile_cover' => self::DEFAULT_PROFILE_COVER,
            'profile_home_cover' => self::DEFAULT_PROFILE_COVER,
            'profile_name' => 'Z-Blog',
            'profile_motto' => '把日常里的灵感，慢慢写成光。分享技术、生活与正在成长的想法。',
            'profile_text' => '把日常里的灵感，慢慢写成光。分享技术、生活与正在成长的想法。',
            'footer_logo' => self::DEFAULT_AVATAR,
            'footer_brand' => 'Z-Blog',
            'footer_text' => '© 2026 筑梦科技 · 记录想法，沉淀内容',
            'footer_link_text' => '💬 QQ交流群',
            'footer_link_url' => 'https://qm.qq.com/q/PE4qEHoF8W',
            'footer_powered' => 'Powered by PHP · Theme inspired by clean card design',
            'about_title' => '关于本站',
            'about_subtitle' => '关于这里的故事，关于写作的小角落',
            'about_avatar' => self::DEFAULT_AVATAR,
            'about_cover' => self::DEFAULT_PROFILE_COVER,
            'about_content' => "## 你好，我是 Z-Blog\n\n这是一个用 PHP 从零搭建的极简博客系统，专注于把内容、互动和阅读体验都做轻一点。\n\n### 这里你能看到\n\n- **技术文章**：编程语言、框架、工具的实战与思考\n- **生活随笔**：旅行、阅读、电影里的小灵感\n- **项目记录**：从想法到上线的全过程\n\n### 联系方式\n\n如果你有任何建议，欢迎通过留言板给我留言，或者通过邮箱与我交流。",
            'about_mode' => 'markdown',
            'about_skills' => "PHP\nJavaScript\nMySQL\nVue\nLinux\n云原生",
            'about_links' => '',
            'guestbook_title' => '留言板',
            'guestbook_subtitle' => '在这里，留下你想说的任何一句话',
            'guestbook_notice' => '请文明留言，垃圾信息会被自动隐藏。留言默认公开显示。',
        ];

        foreach ($defaults as $key => $value) {
            self::setDefault($key, $value);
        }

        Database::query("DELETE FROM site_settings WHERE setting_key = 'nav_user_url'");

        self::repairDefaultSiteLogo();
        self::repairDefaultBackgroundImages();
        self::removeLegacyDefaultButtons();
        self::repairDefaultButtonIcons();
        self::removeLegacyDefaultAnnouncements();
        self::repairLegacyDefaultAboutLinks();
        self::seedDefaultSidebarAnnouncement();
        self::seedDefaultSlides();
        self::trimLegacyDefaultSlides();
        self::seedDefaultAboutStatCards();
        self::seedDefaultAboutFeatureCards();
        self::repairDefaultSlideImages();
    }

    public static function settings(): array
    {
        self::createTables();

        $stmt = Database::query("SELECT setting_key, setting_value FROM site_settings");
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }

        return $settings;
    }

    public static function updateSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            self::set((string) $key, (string) $value);
        }
    }

    public static function sidebarAnnouncement(?array $settings = null): ?array
    {
        self::createTables();

        $settings ??= self::settings();
        $content = trim((string) ($settings['sidebar_announcement_content'] ?? ''));
        if ($content === '') {
            return null;
        }

        $mode = (string) ($settings['sidebar_announcement_mode'] ?? 'text');
        $mode = in_array($mode, ['text', 'markdown', 'html'], true) ? $mode : 'text';

        return [
            'content' => $content,
            'content_mode' => $mode,
            'html' => self::renderRichContent($content, $mode),
        ];
    }

    public static function updateSidebarAnnouncement(string $content, string $mode): void
    {
        $mode = in_array($mode, ['text', 'markdown', 'html'], true) ? $mode : 'text';

        self::updateSettings([
            'sidebar_announcement_content' => $content,
            'sidebar_announcement_mode' => $mode,
        ]);
    }

    public static function copyButtons(): array
    {
        self::createTables();

        $stmt = Database::query(
            "SELECT * FROM sidebar_copy_buttons
            WHERE is_active = 1
            ORDER BY sort_order ASC, id ASC"
        );

        return $stmt->fetchAll();
    }

    public static function updateCopyButtonsFromLines(string $lines): void
    {
        self::createTables();

        Database::query("DELETE FROM sidebar_copy_buttons");

        $order = 1;
        foreach (preg_split('/\R/u', $lines) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 2));
            $label = $parts[0] ?? '';
            $copyValue = $parts[1] ?? '';

            if ($label === '' || $copyValue === '') {
                continue;
            }

            self::createCopyButton($label, $copyValue, self::defaultButtonIconSvg($label), $order);
            $order++;
        }
    }

    public static function copyButtonsToLines(): string
    {
        $lines = [];
        foreach (self::copyButtons() as $button) {
            $lines[] = implode('|', [
                (string) $button['label'],
                (string) $button['copy_value'],
            ]);
        }

        return implode("\n", $lines);
    }

    public static function activeAnnouncement(): ?array
    {
        self::createTables();

        $stmt = Database::query(
            "SELECT * FROM announcements
            WHERE is_active = 1
            ORDER BY id DESC
            LIMIT 1"
        );

        $announcement = $stmt->fetch();
        if (!$announcement) {
            return null;
        }

        $announcement['html'] = self::renderRichContent(
            (string) $announcement['content'],
            (string) ($announcement['content_mode'] ?? 'text')
        );

        return $announcement;
    }

    public static function allActiveAnnouncements(): array
    {
        self::createTables();

        $stmt = Database::query(
            "SELECT * FROM announcements
            WHERE is_active = 1
            ORDER BY id DESC"
        );

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['html'] = self::renderRichContent(
                (string) $row['content'],
                (string) ($row['content_mode'] ?? 'text')
            );
        }
        unset($row);

        return $rows;
    }

    public static function allAnnouncements(): array
    {
        self::createTables();

        $stmt = Database::query(
            "SELECT * FROM announcements
            ORDER BY is_active DESC, id DESC"
        );

        return $stmt->fetchAll();
    }

    public static function findAnnouncement(int $id): ?array
    {
        self::createTables();

        $stmt = Database::query("SELECT * FROM announcements WHERE id = ? LIMIT 1", [$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function createAnnouncement(string $level, string $content, string $mode, bool $active = true): int
    {
        self::createTables();

        $level = self::normalizeAnnouncementLevel($level);
        $mode = in_array($mode, ['text', 'markdown', 'html'], true) ? $mode : 'text';
        $now = self::now();

        Database::query(
            "INSERT INTO announcements (level, content, content_mode, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$level, $content, $mode, $active ? 1 : 0, $now, $now]
        );

        return (int) Database::getInstance()->lastInsertId();
    }

    public static function updateAnnouncementById(int $id, string $level, string $content, string $mode, bool $active = true): void
    {
        self::createTables();

        $level = self::normalizeAnnouncementLevel($level);
        $mode = in_array($mode, ['text', 'markdown', 'html'], true) ? $mode : 'text';

        Database::query(
            "UPDATE announcements
             SET level = ?, content = ?, content_mode = ?, is_active = ?, updated_at = ?
             WHERE id = ?",
            [$level, $content, $mode, $active ? 1 : 0, self::now(), $id]
        );
    }

    public static function deleteAnnouncement(int $id): bool
    {
        $stmt = Database::query("DELETE FROM announcements WHERE id = ?", [$id]);
        return $stmt->rowCount() > 0;
    }

    public static function updateAnnouncement(string $level, string $content, string $mode): void
    {
        self::createTables();

        $level = self::normalizeAnnouncementLevel($level);
        $mode = in_array($mode, ['text', 'markdown', 'html'], true) ? $mode : 'text';
        $now = self::now();

        $stmt = Database::query("SELECT id FROM announcements ORDER BY id DESC LIMIT 1");
        $id = $stmt->fetchColumn();

        if ($id) {
            Database::query(
                "UPDATE announcements SET level = ?, content = ?, content_mode = ?, is_active = 1, updated_at = ? WHERE id = ?",
                [$level, $content, $mode, $now, (int) $id]
            );
            return;
        }

        Database::query(
            "INSERT INTO announcements (level, content, content_mode, is_active, created_at, updated_at)
            VALUES (?, ?, ?, 1, ?, ?)",
            [$level, $content, $mode, $now, $now]
        );
    }

    public static function heroSlides(): array
    {
        self::createTables();

        $stmt = Database::query(
            "SELECT * FROM hero_slides
            WHERE is_active = 1
            ORDER BY sort_order ASC, id ASC"
        );

        return $stmt->fetchAll();
    }

    public static function updateHeroSlidesFromLines(string $lines): void
    {
        self::createTables();

        $order = 1;
        $nextSlides = [];
        foreach (preg_split('/\R/u', $lines) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 3));
            $imageUrl = $parts[0] ?? '';
            $linkUrl = $parts[1] ?? '/';
            $title = $parts[2] ?? '';

            if ($imageUrl === '' || $title === '') {
                continue;
            }

            $nextSlides[] = [$imageUrl, $linkUrl !== '' ? $linkUrl : '/', $title, $order];
            $order++;
        }

        if ($nextSlides === []) {
            throw new \RuntimeException('轮播图至少保留 1 个，请填写标题并上传图片。');
        }

        self::set('hero_slides_initialized', '1');
        Database::query("DELETE FROM hero_slides");

        foreach ($nextSlides as $slide) {
            self::createHeroSlide($slide[0], $slide[1], $slide[2], $slide[3]);
        }
    }

    public static function heroSlidesToLines(): string
    {
        $lines = [];
        foreach (self::heroSlides() as $slide) {
            $lines[] = implode('|', [
                (string) $slide['image_url'],
                (string) $slide['link_url'],
                (string) $slide['title'],
            ]);
        }

        return implode("\n", $lines);
    }

    public static function aboutMetricValues(): array
    {
        return [
            'posts' => (int) Database::query("SELECT COUNT(*) FROM posts WHERE status = 1")->fetchColumn(),
            'views' => (int) Database::query("SELECT COALESCE(SUM(view_count), 0) FROM posts WHERE status = 1")->fetchColumn(),
            'likes' => (int) Database::query("SELECT COUNT(*) FROM post_likes")->fetchColumn(),
            'comments' => (int) Database::query("SELECT COUNT(*) FROM post_comments WHERE status = 1")->fetchColumn(),
        ];
    }

    public static function aboutStatCards(): array
    {
        self::createTables();

        $stmt = Database::query(
            "SELECT * FROM about_stat_cards
            WHERE is_active = 1
            ORDER BY sort_order ASC, id ASC"
        );

        return $stmt->fetchAll();
    }

    public static function aboutStatCardsWithValues(?array $values = null): array
    {
        $values ??= self::aboutMetricValues();
        $cards = self::aboutStatCards();

        foreach ($cards as &$card) {
            $metricKey = (string) ($card['metric_key'] ?? '');
            $card['value'] = (int) ($values[$metricKey] ?? 0);
        }
        unset($card);

        return $cards;
    }

    public static function updateAboutStatCardsFromLines(string $lines): void
    {
        self::createTables();

        Database::query("DELETE FROM about_stat_cards");

        $order = 1;
        foreach (preg_split('/\R/u', $lines) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 4));
            $metricKey = $parts[0] ?? '';
            $label = $parts[1] ?? '';
            $description = $parts[2] ?? '';
            $iconClass = $parts[3] ?? 'fa-solid fa-chart-simple';

            if ($metricKey === '' || $label === '') {
                continue;
            }

            self::createAboutStatCard($metricKey, $label, $description, $iconClass, $order);
            $order++;
        }
    }

    public static function aboutStatCardsToLines(): string
    {
        $lines = [];
        foreach (self::aboutStatCards() as $card) {
            $lines[] = implode('|', [
                (string) $card['metric_key'],
                (string) $card['label'],
                (string) $card['description'],
                (string) $card['icon_class'],
            ]);
        }

        return implode("\n", $lines);
    }

    public static function aboutFeatureCards(): array
    {
        self::createTables();

        $stmt = Database::query(
            "SELECT * FROM about_feature_cards
            WHERE is_active = 1
            ORDER BY sort_order ASC, id ASC"
        );

        return $stmt->fetchAll();
    }

    public static function updateAboutFeatureCardsFromLines(string $lines): void
    {
        self::createTables();

        Database::query("DELETE FROM about_feature_cards");

        $order = 1;
        foreach (preg_split('/\R/u', $lines) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 3));
            $title = $parts[0] ?? '';
            $description = $parts[1] ?? '';
            $iconClass = $parts[2] ?? 'fa-solid fa-sparkles';

            if ($title === '') {
                continue;
            }

            self::createAboutFeatureCard($title, $description, $iconClass, $order);
            $order++;
        }
    }

    public static function aboutFeatureCardsToLines(): string
    {
        $lines = [];
        foreach (self::aboutFeatureCards() as $card) {
            $lines[] = implode('|', [
                (string) $card['title'],
                (string) $card['description'],
                (string) $card['icon_class'],
            ]);
        }

        return implode("\n", $lines);
    }

    public static function renderRichContent(string $content, string $mode): string
    {
        $mode = in_array($mode, ['text', 'markdown', 'html'], true) ? $mode : 'markdown';

        if ($mode === 'text') {
            return nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
        }

        if ($mode === 'markdown') {
            $converter = new CommonMarkConverter([
                'html_input' => 'allow',
                'allow_unsafe_links' => false,
            ]);
            $content = (string) $converter->convert($content);
        }

        return self::sanitizeHtml($content);
    }

    private static function setDefault(string $key, string $value): void
    {
        $stmt = Database::query("SELECT COUNT(*) FROM site_settings WHERE setting_key = ?", [$key]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        self::set($key, $value);
    }

    private static function set(string $key, string $value): void
    {
        $now = self::now();

        Database::query(
            "INSERT INTO site_settings (setting_key, setting_value, created_at, updated_at)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)",
            [$key, $value, $now, $now]
        );
    }

    private static function repairDefaultSiteLogo(): void
    {
        Database::query(
            "UPDATE site_settings
            SET setting_value = ?, updated_at = ?
            WHERE setting_key = 'site_logo'
            AND setting_value = ''",
            ['/assets/img/ZMoon.png', self::now()]
        );
    }

    private static function repairDefaultBackgroundImages(): void
    {
        Database::query(
            "UPDATE site_settings
            SET setting_value = ?, updated_at = ?
            WHERE setting_key IN ('profile_cover', 'profile_home_cover', 'about_cover')
            AND setting_value IN ('', 'https://cdn.zmoon.top/img/bg1.webp', 'https://cdn.zmoon.top/img/bg1.png')",
            [self::DEFAULT_PROFILE_COVER, self::now()]
        );
    }

    private static function removeLegacyDefaultButtons(): void
    {
        $rows = Database::query(
            "SELECT label, copy_value FROM sidebar_copy_buttons ORDER BY sort_order ASC, id ASC"
        )->fetchAll();

        $legacy = [
            'GitHub' => 'https://github.com/z-blog-demo',
            'Gitee' => 'https://gitee.com/z-blog-demo',
            'QQ' => 'zblog-demo@qq.com',
            '邮箱' => 'hello@example.com',
            '微信' => '微信号：zblog-demo',
        ];

        if (count($rows) !== count($legacy)) {
            return;
        }

        $actual = [];
        foreach ($rows as $row) {
            $actual[(string) ($row['label'] ?? '')] = (string) ($row['copy_value'] ?? '');
        }

        if ($actual != $legacy) {
            return;
        }

        Database::query("DELETE FROM sidebar_copy_buttons");
    }

    private static function repairDefaultButtonIcons(): void
    {
        $defaultLabels = ['GitHub', 'Gitee', 'QQ', '邮箱', '微信'];

        foreach ($defaultLabels as $label) {
            $iconSvg = self::defaultButtonIconSvg($label);

            Database::query(
                "UPDATE sidebar_copy_buttons
                SET icon_svg = ?, updated_at = ?
                WHERE label = ?",
                [$iconSvg, self::now(), $label]
            );
        }
    }

    private static function createCopyButton(string $label, string $copyValue, ?string $iconSvg, int $order): void
    {
        $now = self::now();

        Database::query(
            "INSERT INTO sidebar_copy_buttons (label, copy_value, icon_svg, sort_order, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, ?, ?)",
            [$label, $copyValue, $iconSvg, $order, $now, $now]
        );
    }

    private static function removeLegacyDefaultAnnouncements(): void
    {
        Database::query(
            "DELETE FROM announcements WHERE content = ?",
            [self::LEGACY_DEFAULT_ANNOUNCEMENT]
        );
    }

    private static function repairLegacyDefaultAboutLinks(): void
    {
        Database::query(
            "UPDATE site_settings
             SET setting_value = '', updated_at = ?
             WHERE setting_key = 'about_links' AND setting_value = ?",
            [self::now(), self::LEGACY_DEFAULT_ABOUT_LINKS]
        );
    }

    private static function seedDefaultSidebarAnnouncement(): void
    {
        $stmt = Database::query(
            "SELECT setting_value FROM site_settings WHERE setting_key = 'sidebar_announcement_content' LIMIT 1"
        );
        $current = $stmt->fetchColumn();

        if ($current !== false) {
            $content = trim((string) $current);
            if (in_array($content, [self::LEGACY_DEFAULT_ANNOUNCEMENT, '欢迎来到本站。'], true)) {
                self::updateSidebarAnnouncement(self::DEFAULT_SIDEBAR_ANNOUNCEMENT, 'markdown');
                return;
            }

            if ($content === self::DEFAULT_SIDEBAR_ANNOUNCEMENT) {
                self::set('sidebar_announcement_mode', 'markdown');
                return;
            }

            self::setDefault('sidebar_announcement_mode', 'text');
            return;
        }

        self::setDefault('sidebar_announcement_content', self::DEFAULT_SIDEBAR_ANNOUNCEMENT);
        self::setDefault('sidebar_announcement_mode', 'markdown');
    }

    private static function seedDefaultSlides(): void
    {
        $initialized = Database::query(
            "SELECT setting_value FROM site_settings WHERE setting_key = 'hero_slides_initialized' LIMIT 1"
        )->fetchColumn();
        if ((string) $initialized === '1') {
            return;
        }

        $stmt = Database::query("SELECT COUNT(*) FROM hero_slides");
        if ((int) $stmt->fetchColumn() > 0) {
            self::set('hero_slides_initialized', '1');
            return;
        }

        $slides = [
            [self::DEFAULT_HERO_COVER, '/', '【诚挚邀约】你的每一个建议，都是这款博客系统的成长动力'],
            [self::DEFAULT_HERO_COVER, '/', '个人开发的管理系统 Neat-Admin'],
        ];

        foreach ($slides as $index => $slide) {
            self::createHeroSlide($slide[0], $slide[1], $slide[2], $index + 1);
        }

        self::set('hero_slides_initialized', '1');
    }

    private static function createHeroSlide(string $imageUrl, string $linkUrl, string $title, int $order): void
    {
        $now = self::now();

        Database::query(
            "INSERT INTO hero_slides (image_url, link_url, title, sort_order, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, ?, ?)",
            [$imageUrl, $linkUrl, $title, $order, $now, $now]
        );
    }

    private static function seedDefaultAboutStatCards(): void
    {
        $stmt = Database::query("SELECT COUNT(*) FROM about_stat_cards");
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $cards = [
            ['posts', '文章沉淀', '已发布的内容数量', 'fa-regular fa-file-lines'],
            ['views', '阅读轨迹', '全站累计浏览量', 'fa-regular fa-eye'],
            ['likes', '喜欢反馈', '收到的点赞数量', 'fa-regular fa-heart'],
            ['comments', '交流回声', '已通过的评论数量', 'fa-regular fa-comments'],
        ];

        foreach ($cards as $index => $card) {
            self::createAboutStatCard($card[0], $card[1], $card[2], $card[3], $index + 1);
        }
    }

    private static function createAboutStatCard(string $metricKey, string $label, string $description, string $iconClass, int $order): void
    {
        $now = self::now();

        Database::query(
            "INSERT INTO about_stat_cards (metric_key, label, description, icon_class, sort_order, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 1, ?, ?)",
            [$metricKey, $label, $description, $iconClass !== '' ? $iconClass : 'fa-solid fa-chart-simple', $order, $now, $now]
        );
    }

    private static function seedDefaultAboutFeatureCards(): void
    {
        $stmt = Database::query("SELECT COUNT(*) FROM about_feature_cards");
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $cards = [
            ['内容沉淀', '记录技术实践、产品思考和生活里的灵感，让每一次输入都能慢慢变成可回看、可复用的内容。', 'fa-solid fa-feather-pointed'],
            ['持续迭代', '把博客当作一个长期维护的小产品，围绕阅读体验、互动反馈和管理效率持续打磨。', 'fa-solid fa-wand-magic-sparkles'],
            ['轻量互动', '用评论、点赞和留言板保留真实交流，让这里不只是展示页，也是一处能回应的空间。', 'fa-regular fa-message'],
        ];

        foreach ($cards as $index => $card) {
            self::createAboutFeatureCard($card[0], $card[1], $card[2], $index + 1);
        }
    }

    private static function createAboutFeatureCard(string $title, string $description, string $iconClass, int $order): void
    {
        $now = self::now();

        Database::query(
            "INSERT INTO about_feature_cards (title, description, icon_class, sort_order, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, ?, ?)",
            [$title, $description, $iconClass !== '' ? $iconClass : 'fa-solid fa-sparkles', $order, $now, $now]
        );
    }

    private static function repairDefaultSlideImages(): void
    {
        $defaultTitles = [
            '【诚挚邀约】你的每一个建议，都是这款博客系统的成长动力',
            '个人开发的管理系统 Neat-Admin',
            'vue3.0 中使用 svg 图标',
            '基于 websocket 的 web 聊天室系统',
            '微信公众号关键词自动回复',
        ];

        foreach ($defaultTitles as $title) {
            Database::query(
                "UPDATE hero_slides
                SET image_url = ?, updated_at = ?
                WHERE title = ?",
                [self::DEFAULT_HERO_COVER, self::now(), $title]
            );
        }
    }

    private static function trimLegacyDefaultSlides(): void
    {
        $defaultTitles = [
            '【诚挚邀约】你的每一个建议，都是这款博客系统的成长动力',
            '个人开发的管理系统 Neat-Admin',
            'vue3.0 中使用 svg 图标',
            '基于 websocket 的 web 聊天室系统',
            '微信公众号关键词自动回复',
        ];

        $placeholders = implode(',', array_fill(0, count($defaultTitles), '?'));
        $stmt = Database::query(
            "SELECT id, title FROM hero_slides
            WHERE title IN ($placeholders)
            ORDER BY sort_order ASC, id ASC",
            $defaultTitles
        );
        $defaultRows = $stmt->fetchAll();

        if (count($defaultRows) !== 5) {
            return;
        }

        $total = (int) Database::query("SELECT COUNT(*) FROM hero_slides")->fetchColumn();
        if ($total !== 5) {
            return;
        }

        Database::query(
            "DELETE FROM hero_slides
            WHERE title IN (?, ?, ?)",
            [
                'vue3.0 中使用 svg 图标',
                '基于 websocket 的 web 聊天室系统',
                '微信公众号关键词自动回复',
            ]
        );
    }

    private static function migrateAnnouncementsTable(): void
    {
        $levelColumn = Database::query("SHOW COLUMNS FROM announcements LIKE 'level'")->fetch();
        if (!$levelColumn) {
            Database::query("ALTER TABLE announcements ADD level VARCHAR(20) NOT NULL DEFAULT 'normal' AFTER id");
        }

        $contentModeColumn = Database::query("SHOW COLUMNS FROM announcements LIKE 'content_mode'")->fetch();
        if (!$contentModeColumn) {
            Database::query("ALTER TABLE announcements ADD content_mode VARCHAR(20) NOT NULL DEFAULT 'text' AFTER content");
        } else {
            Database::query("UPDATE announcements SET content_mode = 'text' WHERE content_mode IS NULL OR TRIM(content_mode) = ''");
            Database::query("UPDATE announcements SET content_mode = LOWER(TRIM(content_mode)) WHERE LOWER(TRIM(content_mode)) IN ('text', 'markdown', 'html')");
            Database::query("UPDATE announcements SET content_mode = 'text' WHERE content_mode NOT IN ('text', 'markdown', 'html')");
            Database::query("ALTER TABLE announcements MODIFY content_mode VARCHAR(20) NOT NULL DEFAULT 'text'");
        }

        Database::query("UPDATE announcements SET level = 'normal' WHERE level IS NULL OR TRIM(level) = ''");
        Database::query("UPDATE announcements SET level = 'urgent' WHERE level IN ('urgent', 'critical', 'danger', 'error', '紧急', '严重', '故障', '停机', '安全', '警告')");
        Database::query("UPDATE announcements SET level = 'important' WHERE level IN ('important', 'warning', 'warn', 'reminder', '重要', '提醒', '维护', '升级', '注意')");
        Database::query("UPDATE announcements SET level = 'archived' WHERE level IN ('archived', 'closed', 'done', '已结束', '结束', '归档', '历史', '关闭', '完成')");
        Database::query("UPDATE announcements SET level = 'normal' WHERE level NOT IN ('normal', 'important', 'urgent', 'archived')");
    }

    private static function normalizeAnnouncementLevel(string $level): string
    {
        $key = mb_strtolower(trim($level), 'UTF-8');
        $aliases = [
            'normal' => 'normal',
            'info' => 'normal',
            'notice' => 'normal',
            '普通' => 'normal',
            'important' => 'important',
            'warning' => 'important',
            'warn' => 'important',
            'reminder' => 'important',
            '重要' => 'important',
            '提醒' => 'important',
            '维护' => 'important',
            '升级' => 'important',
            '注意' => 'important',
            'urgent' => 'urgent',
            'critical' => 'urgent',
            'danger' => 'urgent',
            'error' => 'urgent',
            '紧急' => 'urgent',
            '严重' => 'urgent',
            '故障' => 'urgent',
            '停机' => 'urgent',
            '安全' => 'urgent',
            '警告' => 'urgent',
            'archived' => 'archived',
            'closed' => 'archived',
            'done' => 'archived',
            '已结束' => 'archived',
            '结束' => 'archived',
            '归档' => 'archived',
            '历史' => 'archived',
            '关闭' => 'archived',
            '完成' => 'archived',
        ];

        return $aliases[$key] ?? 'normal';
    }

    private static function defaultButtonIconSvg(string $label): string
    {
        return match ($label) {
            'GitHub' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2C6.48 2 2 6.58 2 12.26c0 4.53 2.87 8.37 6.84 9.73.5.1.68-.22.68-.49v-1.73c-2.78.62-3.37-1.37-3.37-1.37-.45-1.19-1.11-1.5-1.11-1.5-.91-.64.07-.63.07-.63 1 .07 1.53 1.06 1.53 1.06.9 1.57 2.36 1.12 2.93.85.09-.67.35-1.12.63-1.38-2.22-.26-4.55-1.14-4.55-5.06 0-1.12.39-2.03 1.03-2.75-.1-.26-.45-1.31.1-2.71 0 0 .84-.28 2.75 1.05A9.29 9.29 0 0 1 12 7c.85 0 1.71.12 2.51.34 1.91-1.33 2.75-1.05 2.75-1.05.55 1.4.2 2.45.1 2.71.64.72 1.03 1.63 1.03 2.75 0 3.93-2.34 4.8-4.57 5.05.36.32.68.95.68 1.91v2.83c0 .27.18.59.69.49A10.16 10.16 0 0 0 22 12.26C22 6.58 17.52 2 12 2Z"></path></svg>',
            'Gitee' => '<svg t="1779002958566" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="2521" width="200" height="200" aria-hidden="true"><path d="M512 1024C229.2224 1024 0 794.7776 0 512S229.2224 0 512 0s512 229.2224 512 512-229.2224 512-512 512z m259.1488-568.8832H480.4096a25.2928 25.2928 0 0 0-25.2928 25.2928l-0.0256 63.2064c0 13.952 11.3152 25.2928 25.2672 25.2928h177.024c13.9776 0 25.2928 11.3152 25.2928 25.2672v12.6464a75.8528 75.8528 0 0 1-75.8528 75.8528H366.592a25.2928 25.2928 0 0 1-25.2672-25.2928v-240.1792a75.8528 75.8528 0 0 1 75.8272-75.8528h353.9456a25.2928 25.2928 0 0 0 25.2672-25.2928l0.0768-63.2064a25.2928 25.2928 0 0 0-25.2672-25.2928H417.152a189.6192 189.6192 0 0 0-189.6192 189.6448v353.9456c0 13.9776 11.3152 25.2928 25.2928 25.2928h372.9408a170.6496 170.6496 0 0 0 170.6496-170.6496v-145.408a25.2928 25.2928 0 0 0-25.2928-25.2672z" fill="#C71D23" p-id="2522"></path></svg>',
            'QQ' => '<svg t="1779002974473" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="3504" width="200" height="200" aria-hidden="true"><path d="M148.859845 404.057356c-5.11465 15.34395 0 20.4586 0 76.719751 0 15.34395-61.375801 76.719751-86.949052 143.210202-25.57325 66.490451-25.57325 138.095552 10.2293 163.668803 35.802551 30.6879 71.605101-92.063701 76.719752-71.605101 0 5.11465 5.11465 15.34395 5.11465 25.57325 15.34395 35.802551 35.802551 71.605101 61.375801 102.293002 5.11465 5.11465-35.802551 20.4586-61.375801 61.3758-25.57325 40.917201 10.2293 117.636952 132.980902 117.636952 158.554152 0 199.471353-56.261151 199.471353-56.261151h51.1465c10.2293 0 86.949051 66.490451 194.356703 56.261151 184.127403-20.4586 158.554152-81.834401 143.210202-122.751602-15.34395-40.917201-66.490451-61.375801-66.490451-61.375801 46.031851-51.146501 51.146501-76.719751 66.490451-122.751601 5.11465-20.4586 51.146501 102.293002 81.834402 71.605101 15.34395-10.2293 40.917201-61.375801 15.34395-163.668803s-81.834401-127.866252-81.834401-143.210202V404.057356c-10.2293-35.802551-30.6879-25.57325-30.687901-35.802551 0-204.586003-153.439502-368.254805-342.681555-368.254805S174.433095 163.668802 174.433095 368.254805c0 15.34395-15.34395 5.11465-25.57325 35.802551z m0 0" fill="#4A9AFD" p-id="3505"></path></svg>',
            '邮箱' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm0 3.2v.25l8 4.8 8-4.8V8.2l-8 4.8-8-4.8Z"></path></svg>',
            '微信' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.4 4.2c-4.1 0-7.4 2.76-7.4 6.16 0 1.96 1.1 3.63 2.87 4.8l-.72 2.16 2.51-1.26c.87.25 1.76.38 2.74.38.35 0 .69-.02 1.02-.06a5.43 5.43 0 0 1-.28-1.72c0-3.04 2.9-5.5 6.47-5.5.07 0 .14 0 .21.02-.62-2.82-3.67-4.98-7.42-4.98Zm-2.4 3.2a.9.9 0 1 1 0 1.8.9.9 0 0 1 0-1.8Zm4.8 0a.9.9 0 1 1 0 1.8.9.9 0 0 1 0-1.8Zm4.82 3.05c-2.96 0-5.36 1.9-5.36 4.25s2.4 4.25 5.36 4.25c.66 0 1.28-.1 1.88-.28l2.05 1.03-.58-1.76C21.19 17.13 22 15.98 22 14.7c0-2.35-2.4-4.25-5.38-4.25Zm-1.76 2.46a.76.76 0 1 1 0 1.52.76.76 0 0 1 0-1.52Zm3.52 0a.76.76 0 1 1 0 1.52.76.76 0 0 1 0-1.52Z"></path></svg>',
            default => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm-3 11.5c.3-1.75 1.55-3 3-3s2.7 1.25 3 3H9Z"></path></svg>',
        };
    }

    private static function sanitizeHtml(string $html): string
    {
        return HtmlSanitizer::sanitizeSiteContent($html);
    }

    private static function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))->format('Y-m-d H:i:s');
    }
}
