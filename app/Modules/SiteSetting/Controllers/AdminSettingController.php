<?php

declare(strict_types=1);

namespace App\Modules\SiteSetting\Controllers;

use App\Models\SiteContent;
use App\Modules\Admin\Support\AdminControllerBase;
use App\Modules\SiteSetting\Requests\SiteSettingRequest;
use App\Modules\SiteSetting\Services\SiteSettingService;

class AdminSettingController extends AdminControllerBase
{
    public function __construct(
        private ?SiteSettingService $settings = null,
        private ?SiteSettingRequest $settingRequest = null
    ) {
        parent::__construct();
        $this->settings ??= new SiteSettingService();
        $this->settingRequest ??= new SiteSettingRequest();
    }

    public function index(): void
    {
        $this->requireLogin();

        $this->render('admin/settings', $this->settings->settingsPageData(
            $_SESSION['admin'] ?? null,
            $this->pullFlash()
        ));
    }

    public function update(): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/settings');
            return;
        }

        $this->settings->seedDefaults();
        $currentSettings = $this->settings->all();
        $scope = (string) ($_POST['settings_scope'] ?? '');
        $auditBefore = $this->settingsAuditSnapshot($scope, $currentSettings);
        $scopeLabel = $this->settingsScopeLabel($scope);

        try {
            $errors = $this->settingRequest->validate($_POST);
            if ($errors !== []) {
                throw new \RuntimeException((string) reset($errors));
            }

            $this->settings->updateScope($scope, $_POST, $currentSettings);
        } catch (\RuntimeException $exception) {
            $this->recordAdminActivity('settings_update_failed', $this->currentAdmin(), '', 'warning', '保存前台设置失败：' . $exception->getMessage(), [
                'resource_type' => 'settings',
                'scope' => $scope,
                'scope_label' => $scopeLabel,
                'error' => $exception->getMessage(),
            ]);

            if ($this->wantsJson()) {
                $this->sendJsonResponse([
                    'ok' => false,
                    'type' => 'error',
                    'message' => $exception->getMessage(),
                ], 422);
                return;
            }

            $this->flash($exception->getMessage(), 'error');
            $this->redirect('/admin/settings');
            return;
        }

        $updatedSettings = $this->settings->all();
        $auditAfter = $this->settingsAuditSnapshot($scope, $updatedSettings);
        $changes = $this->auditChanges($auditBefore, $auditAfter, $this->settingsAuditFields($scope));

        $this->recordAdminActivity('settings_update', $this->currentAdmin(), '', 'success', '更新前台设置：' . $scopeLabel . '（' . $this->auditChangeSummary($changes) . '）。', [
            'resource_type' => 'settings',
            'scope' => $scope,
            'scope_label' => $scopeLabel,
            'changes' => $changes,
        ]);

        if ($this->wantsJson()) {
            $this->sendJsonResponse([
                'ok' => true,
                'type' => 'success',
                'message' => '保存成功',
                'settings' => $updatedSettings,
                'heroSlides' => $this->settings->heroSlides(),
            ]);
            return;
        }

        $this->flash('前台设置已保存');
        $this->redirect('/admin/settings');
    }

    public function backend(): void
    {
        $this->requireLogin();

        $this->render('admin/backend-settings', $this->settings->backendPageData(
            $_SESSION['admin'] ?? null,
            $this->pullFlash()
        ));
    }

    private function settingsAuditFields(string $scope): array
    {
        return match ($scope) {
            'basic' => [
                'site_title' => '站点标题',
                'profile_name' => '侧栏名称',
                'site_logo' => '顶栏图标',
                'site_avatar' => '顶栏头像',
                'profile_avatar' => '侧栏头像',
                'profile_cover' => '侧栏背景图',
            ],
            'home' => [
                'hero_slides' => '首页轮播图',
            ],
            'announcement' => [
                'sidebar_announcement_content' => '侧栏公告内容',
                'sidebar_announcement_mode' => '侧栏公告格式',
            ],
            'about' => [
                'about_title' => '关于页标题',
                'about_subtitle' => '关于页副标题',
                'about_content' => '关于页内容',
                'about_mode' => '关于页格式',
                'about_skills' => '技能标签',
                'about_links' => '社交链接',
                'about_avatar' => '关于页头像',
                'about_cover' => '关于页横幅',
            ],
            'guestbook' => [
                'guestbook_title' => '留言板标题',
                'guestbook_subtitle' => '留言板副标题',
                'guestbook_notice' => '留言提示',
            ],
            'footer' => [
                'footer_brand' => '底栏品牌',
                'footer_text' => '底栏文案',
                'footer_link_text' => '底栏链接文字',
                'footer_link_url' => '底栏链接地址',
                'footer_powered' => '底栏技术文案',
                'footer_logo' => '底栏 Logo',
            ],
            default => [],
        };
    }

    private function settingsAuditSnapshot(string $scope, array $settings): array
    {
        $snapshot = [];
        foreach ($this->settingsAuditFields($scope) as $field => $_label) {
            $snapshot[$field] = match ($field) {
                'hero_slides' => SiteContent::heroSlidesToLines(),
                default => (string) ($settings[$field] ?? ''),
            };
        }

        return $snapshot;
    }

    private function settingsScopeLabel(string $scope): string
    {
        return match ($scope) {
            'basic' => '基础信息',
            'home' => '首页内容',
            'announcement' => '侧栏公告',
            'about' => '关于页面',
            'guestbook' => '留言板',
            'footer' => '底栏信息',
            default => '未知分区',
        };
    }
}
