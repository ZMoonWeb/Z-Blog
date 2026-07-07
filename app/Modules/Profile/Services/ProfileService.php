<?php

declare(strict_types=1);

namespace App\Modules\Profile\Services;

use App\Core\Database;
use App\Core\VisitorIdentifier;
use App\Models\Admin;
use App\Models\Like;
use App\Models\Post;
use App\Models\PostInteractionLog;
use App\Models\SiteContent;
use App\Modules\Media\Services\UploadService;

class ProfileService
{
    public function __construct(private ?UploadService $uploads = null)
    {
        $this->uploads ??= new UploadService();
    }

    public function defaultView(): string
    {
        return 'me/index';
    }

    public function adminProfileData(?array $admin, ?array $flash): array
    {
        SiteContent::seedDefaults();

        return [
            'admin' => $admin,
            'settings' => SiteContent::settings(),
            'copyButtons' => SiteContent::copyButtons(),
            'flash' => $flash,
        ];
    }

    public function profileData(): array
    {
        SiteContent::seedDefaults();

        $settings = SiteContent::settings();

        $setting = static function (string $key, string $default = '') use ($settings): string {
            $value = trim((string) ($settings[$key] ?? ''));
            return $value !== '' ? $value : $default;
        };

        $profileCover = $setting('profile_home_cover', $setting('profile_cover', '/assets/img/backgrounds/sidebar-profile-cover.png'));
        $profileAvatar = $setting('profile_avatar', '/assets/img/ZMoon.png');
        $profileName = $setting('profile_name', 'Z-Blog');
        $profileText = $setting('profile_motto', $setting('profile_text', '把日常里的灵感，慢慢写成光。'));

        $statCards = SiteContent::aboutStatCardsWithValues(SiteContent::aboutMetricValues());

        $result = Post::publishedPaginated(1, 9);
        $posts = $result['data'];

        $copyButtons = SiteContent::copyButtons();
        $announcement = SiteContent::sidebarAnnouncement($settings);

        $visitorHashes = VisitorIdentifier::hashes();
        $likedPostIds = Like::likedPostIdsForHashes($visitorHashes, array_column($posts, 'id'));

        $this->recordVisitorPageView('me', 'list', null);

        return [
            'title' => '作者主页',
            'siteSettings' => $settings,
            'profileCover' => $profileCover,
            'profileAvatar' => $profileAvatar,
            'profileName' => $profileName,
            'profileText' => $profileText,
            'statCards' => $statCards,
            'posts' => $posts,
            'pagination' => $result['pagination'],
            'copyButtons' => $copyButtons,
            'announcement' => $announcement,
            'likedPostIds' => $likedPostIds,
        ];
    }

    public function adminUpdateData(array $post, array $admin): array
    {
        SiteContent::seedDefaults();
        $currentSettings = SiteContent::settings();

        $currentAvatar = trim((string) ($currentSettings['profile_avatar'] ?? SiteContent::DEFAULT_AVATAR));
        if ($currentAvatar === '') {
            $currentAvatar = SiteContent::DEFAULT_AVATAR;
        }

        $currentHomeCover = trim((string) ($currentSettings['profile_home_cover'] ?? ''));
        if ($currentHomeCover === '') {
            $currentHomeCover = trim((string) ($currentSettings['profile_cover'] ?? SiteContent::DEFAULT_PROFILE_COVER));
        }
        if ($currentHomeCover === '') {
            $currentHomeCover = SiteContent::DEFAULT_PROFILE_COVER;
        }

        $currentMotto = trim((string) ($currentSettings['profile_motto'] ?? ''));
        if ($currentMotto === '') {
            $currentMotto = trim((string) ($currentSettings['profile_text'] ?? ''));
        }

        $hasProfileDisplayFields = array_key_exists('profile_motto', $post)
            || array_key_exists('copy_button_label', $post)
            || array_key_exists('copy_button_value', $post);
        $profileMotto = $hasProfileDisplayFields ? trim((string) ($post['profile_motto'] ?? '')) : $currentMotto;
        $currentCopyButtonsLines = SiteContent::copyButtonsToLines();

        return [
            'admin_id' => (int) ($admin['id'] ?? 0),
            'username' => trim((string) ($post['username'] ?? '')),
            'current_password' => (string) ($post['current_password'] ?? ''),
            'new_password' => (string) ($post['new_password'] ?? ''),
            'confirm_password' => (string) ($post['confirm_password'] ?? ''),
            'current_avatar' => $currentAvatar,
            'profile_avatar' => $currentAvatar,
            'current_home_cover' => $currentHomeCover,
            'profile_home_cover' => $currentHomeCover,
            'current_motto' => $currentMotto,
            'profile_motto' => $profileMotto,
            'has_profile_display_fields' => $hasProfileDisplayFields,
            'current_copy_buttons_lines' => $currentCopyButtonsLines,
            'copy_buttons_lines' => $hasProfileDisplayFields ? $this->buildCopyButtonsLinesFromRequest($post) : $currentCopyButtonsLines,
        ];
    }

    public function resolveAdminProfileUploads(array $data): array
    {
        $data['profile_avatar'] = $this->uploads->resolveUploadedSettingImage(
            'profile_avatar_file',
            (string) $data['current_avatar'],
            '个人头像',
            'profile-avatar'
        );
        $data['profile_home_cover'] = $this->uploads->resolveUploadedSettingImage(
            'profile_home_cover_file',
            (string) $data['current_home_cover'],
            '个人主页背景图',
            'profile-home-cover'
        );

        return $data;
    }

    public function adminUpdateFailureMetadata(array $data): array
    {
        return [
            'resource_type' => 'admin_profile',
            'resource_id' => (int) ($data['admin_id'] ?? 0),
            'attempted_username' => (string) ($data['username'] ?? ''),
            'password_change_requested' => (string) ($data['new_password'] ?? '') !== '',
            'avatar_change_requested' => $this->hasUploadedFile('profile_avatar_file'),
            'home_cover_change_requested' => $this->hasUploadedFile('profile_home_cover_file'),
            'display_change_requested' => (bool) ($data['has_profile_display_fields'] ?? false),
        ];
    }

    public function updateAdminProfile(array $data, array $admin): array
    {
        $adminId = (int) ($data['admin_id'] ?? 0);
        $username = (string) ($data['username'] ?? '');
        $profileAvatar = (string) ($data['profile_avatar'] ?? '');
        $profileHomeCover = (string) ($data['profile_home_cover'] ?? '');
        $profileMotto = (string) ($data['profile_motto'] ?? '');
        $copyButtonsLines = (string) ($data['copy_buttons_lines'] ?? '');
        $hasProfileDisplayFields = (bool) ($data['has_profile_display_fields'] ?? false);
        $newPassword = (string) ($data['new_password'] ?? '');

        $existingAdmin = Admin::findById($adminId) ?? $admin;
        $profileBefore = [
            'username' => (string) ($existingAdmin['username'] ?? ''),
            'profile_avatar' => (string) ($data['current_avatar'] ?? ''),
            'profile_home_cover' => (string) ($data['current_home_cover'] ?? ''),
            'profile_motto' => (string) ($data['current_motto'] ?? ''),
            'copy_buttons' => (string) ($data['current_copy_buttons_lines'] ?? ''),
        ];
        $now = (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))->format('Y-m-d H:i:s');

        Database::query(
            "UPDATE admin SET username = ?, updated_at = ? WHERE id = ?",
            [$username, $now, $adminId]
        );

        if ($newPassword !== '') {
            Admin::updatePasswordHash($adminId, Admin::hashPassword($newPassword));
        }

        $settingsToUpdate = [];
        if ($profileAvatar !== (string) ($data['current_avatar'] ?? '')) {
            $settingsToUpdate['profile_avatar'] = $profileAvatar;
            $settingsToUpdate['site_avatar'] = $profileAvatar;
        }
        if ($profileHomeCover !== (string) ($data['current_home_cover'] ?? '')) {
            $settingsToUpdate['profile_home_cover'] = $profileHomeCover;
        }
        if ($hasProfileDisplayFields) {
            $settingsToUpdate['profile_motto'] = $profileMotto;
            $settingsToUpdate['profile_text'] = $profileMotto;
        }
        if (!empty($settingsToUpdate)) {
            SiteContent::updateSettings($settingsToUpdate);
        }
        if ($hasProfileDisplayFields) {
            SiteContent::updateCopyButtonsFromLines($copyButtonsLines);
        }

        $_SESSION['admin']['username'] = $username;

        $updatedCopyButtonsLines = $hasProfileDisplayFields ? SiteContent::copyButtonsToLines() : (string) ($data['current_copy_buttons_lines'] ?? '');

        return [
            'before' => $profileBefore,
            'after' => [
                'username' => $username,
                'profile_avatar' => $profileAvatar,
                'profile_home_cover' => $profileHomeCover,
                'profile_motto' => $profileMotto,
                'copy_buttons' => $updatedCopyButtonsLines,
            ],
            'username' => $username,
            'profile_avatar' => $profileAvatar,
            'profile_home_cover' => $profileHomeCover,
            'profile_motto' => $profileMotto,
            'copyButtons' => SiteContent::copyButtons(),
        ];
    }

    private function buildCopyButtonsLinesFromRequest(array $post): string
    {
        $labels = isset($post['copy_button_label']) && is_array($post['copy_button_label']) ? $post['copy_button_label'] : [];
        $values = isset($post['copy_button_value']) && is_array($post['copy_button_value']) ? $post['copy_button_value'] : [];
        $total = max(count($labels), count($values));
        $lines = [];

        for ($index = 0; $index < $total; $index++) {
            $label = trim((string) ($labels[$index] ?? ''));
            $copyValue = trim((string) ($values[$index] ?? ''));

            if ($label === '' || $copyValue === '') {
                continue;
            }

            $lines[] = $label . '|' . $copyValue;
        }

        return implode("\n", $lines);
    }

    private function hasUploadedFile(string $field): bool
    {
        return isset($_FILES[$field])
            && is_array($_FILES[$field])
            && (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    private function recordVisitorPageView(string $panel, string $view = 'list', ?int $sourceId = null): void
    {
        $visitorIdentity = VisitorIdentifier::likeIdentity();

        PostInteractionLog::record('page_view', [
            'visitor_hash' => $visitorIdentity['primary_hash'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'content_excerpt' => 'page:' . $panel,
            'source_type' => 'page',
            'source_id' => $sourceId,
        ]);
    }
}
