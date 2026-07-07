<?php

declare(strict_types=1);

namespace App\Modules\SiteSetting\Services;

use App\Core\Config;
use App\Modules\Media\Services\UploadService;
use App\Modules\SiteSetting\Repositories\SiteSettingRepository;

class SiteSettingService
{
    public function __construct(
        private ?SiteSettingRepository $settings = null,
        private ?UploadService $uploads = null
    ) {
        $this->settings ??= new SiteSettingRepository();
        $this->uploads ??= new UploadService();
    }

    public function all(): array
    {
        return $this->settings->all();
    }

    public function seedDefaults(): void
    {
        $this->settings->seedDefaults();
    }

    public function settingsPageData(?array $admin, ?array $flash): array
    {
        $this->settings->seedDefaults();

        $settings = $this->settings->all();
        $announcementMode = (string) ($settings['sidebar_announcement_mode'] ?? 'text');
        $announcement = [
            'content' => (string) ($settings['sidebar_announcement_content'] ?? ''),
            'content_mode' => in_array($announcementMode, ['text', 'markdown', 'html'], true) ? $announcementMode : 'text',
        ];

        return [
            'admin' => $admin,
            'settings' => $settings,
            'announcement' => $announcement,
            'heroSlides' => $this->settings->heroSlides(),
            'flash' => $flash,
        ];
    }

    public function backendPageData(?array $admin, ?array $flash): array
    {
        $this->settings->seedDefaults();

        return [
            'admin' => $admin,
            'siteSettings' => $this->settings->all(),
            'blogVersion' => $this->currentBlogVersion(),
            'updateCheckUrlConfigured' => trim((string) Config::get('app.update_check_url', '')) !== '',
            'sessionInfo' => [
                'login_at' => isset($_SESSION['admin_login_at']) ? (int) $_SESSION['admin_login_at'] : 0,
                'expires_at' => isset($_SESSION['admin_expires_at']) ? (int) $_SESSION['admin_expires_at'] : 0,
                'ip_address' => (string) ($_SESSION['admin_ip_address'] ?? ''),
                'user_agent' => (string) ($_SESSION['admin_user_agent'] ?? ''),
            ],
            'flash' => $flash,
        ];
    }

    public function updateScope(string $scope, array $data, array $currentSettings): void
    {
        if ($scope === 'basic') {
            $this->settings->update([
                'site_title' => trim((string) ($data['site_title'] ?? '')),
                'profile_name' => trim((string) ($data['profile_name'] ?? '')),
                'site_logo' => $this->uploads->resolveUploadedSettingImage('site_logo_file', (string) ($currentSettings['site_logo'] ?? ''), '顶栏图标', 'site-logo'),
                'site_avatar' => $this->uploads->resolveUploadedSettingImage('site_avatar_file', (string) ($currentSettings['site_avatar'] ?? ''), '顶栏头像', 'site-avatar'),
                'profile_avatar' => $this->uploads->resolveUploadedSettingImage('profile_avatar_file', (string) ($currentSettings['profile_avatar'] ?? ''), '侧栏头像', 'profile-avatar'),
                'profile_cover' => $this->uploads->resolveUploadedSettingImage('profile_cover_file', (string) ($currentSettings['profile_cover'] ?? ''), '侧栏背景图', 'profile-cover'),
            ]);
            return;
        }

        if ($scope === 'home') {
            $this->settings->updateHeroSlidesFromLines($this->buildHeroSlidesLinesFromRequest($data));
            return;
        }

        if ($scope === 'announcement') {
            $this->settings->updateSidebarAnnouncement(
                (string) ($data['announcement_content'] ?? ''),
                (string) ($data['announcement_mode'] ?? 'text')
            );
            return;
        }

        if ($scope === 'about') {
            $this->settings->update([
                'about_title' => trim((string) ($data['about_title'] ?? '')),
                'about_subtitle' => trim((string) ($data['about_subtitle'] ?? '')),
                'about_content' => (string) ($data['about_content'] ?? ''),
                'about_mode' => trim((string) ($data['about_mode'] ?? 'markdown')),
                'about_skills' => (string) ($data['about_skills'] ?? ''),
                'about_links' => $this->buildAboutLinksLinesFromRequest($data),
                'about_avatar' => $this->uploads->resolveUploadedSettingImage('about_avatar_file', (string) ($currentSettings['about_avatar'] ?? ''), '关于页头像', 'about-avatar'),
                'about_cover' => $this->uploads->resolveUploadedSettingImage('about_cover_file', (string) ($currentSettings['about_cover'] ?? ''), '关于页横幅', 'about-cover'),
            ]);
            return;
        }

        if ($scope === 'guestbook') {
            $this->settings->update([
                'guestbook_title' => trim((string) ($data['guestbook_title'] ?? '')),
                'guestbook_subtitle' => trim((string) ($data['guestbook_subtitle'] ?? '')),
                'guestbook_notice' => trim((string) ($data['guestbook_notice'] ?? '')),
            ]);
            return;
        }

        if ($scope === 'footer') {
            $this->settings->update([
                'footer_brand' => trim((string) ($data['footer_brand'] ?? '')),
                'footer_text' => trim((string) ($data['footer_text'] ?? '')),
                'footer_link_text' => trim((string) ($data['footer_link_text'] ?? '')),
                'footer_link_url' => trim((string) ($data['footer_link_url'] ?? '')),
                'footer_powered' => trim((string) ($data['footer_powered'] ?? '')),
                'footer_logo' => $this->uploads->resolveUploadedSettingImage('footer_logo_file', (string) ($currentSettings['footer_logo'] ?? ''), '底栏 Logo', 'footer-logo'),
            ]);
            return;
        }

        throw new \RuntimeException('未知的设置分区。');
    }

    public function heroSlides(): array
    {
        return $this->settings->heroSlides();
    }

    public function heroSlidesToLines(): string
    {
        return $this->settings->heroSlidesToLines();
    }

    public function currentBlogVersion(): string
    {
        $version = trim((string) Config::get('app.version', '1.0.2'));
        return $version !== '' ? $version : '1.0.2';
    }

    private function buildHeroSlidesLinesFromRequest(array $data): string
    {
        $titles = isset($data['hero_slide_title']) && is_array($data['hero_slide_title']) ? $data['hero_slide_title'] : [];
        $links = isset($data['hero_slide_link']) && is_array($data['hero_slide_link']) ? $data['hero_slide_link'] : [];
        $existingImages = isset($data['hero_slide_existing']) && is_array($data['hero_slide_existing']) ? $data['hero_slide_existing'] : [];
        $uploadedImages = $this->uploads->normalizeUploadedFilesArray('hero_slide_image');

        $total = max(count($titles), count($links), count($existingImages), count($uploadedImages));
        $lines = [];

        for ($index = 0; $index < $total; $index++) {
            $title = trim((string) ($titles[$index] ?? ''));
            if ($title === '') {
                continue;
            }

            $image = $this->uploads->resolveUploadedImageValue(
                $uploadedImages[$index] ?? ['error' => UPLOAD_ERR_NO_FILE],
                trim((string) ($existingImages[$index] ?? '')),
                '第 ' . ($index + 1) . ' 个轮播图图片',
                'hero-slide-' . ($index + 1)
            );

            if ($image === '') {
                throw new \RuntimeException('第 ' . ($index + 1) . ' 个轮播图请上传图片。');
            }

            $link = trim((string) ($links[$index] ?? ''));
            $lines[] = implode('|', [
                $image,
                $link !== '' ? $link : '/',
                $title,
            ]);
        }

        return implode("\n", $lines);
    }

    private function buildAboutLinksLinesFromRequest(array $data): string
    {
        $labels = isset($data['about_link_label']) && is_array($data['about_link_label']) ? $data['about_link_label'] : [];
        $urls = isset($data['about_link_url']) && is_array($data['about_link_url']) ? $data['about_link_url'] : [];
        $total = max(count($labels), count($urls));
        $lines = [];

        for ($index = 0; $index < $total; $index++) {
            $label = trim((string) ($labels[$index] ?? ''));
            $url = trim((string) ($urls[$index] ?? ''));

            if ($label === '' || $url === '') {
                continue;
            }

            $lines[] = implode('|', [
                $label,
                $this->aboutLinkIcon($label),
                $url,
            ]);
        }

        return implode("\n", $lines);
    }

    private function aboutLinkIcon(string $label): string
    {
        return match ($label) {
            'GitHub' => 'fa-brands fa-github',
            'Gitee' => 'fa-solid fa-code-branch',
            'QQ' => 'fa-brands fa-qq',
            '邮箱' => 'fa-solid fa-envelope',
            '微信' => 'fa-brands fa-weixin',
            default => 'fa-solid fa-link',
        };
    }
}
