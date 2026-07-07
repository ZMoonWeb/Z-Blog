<?php

declare(strict_types=1);

namespace App\Modules\Profile\Controllers;

use App\Modules\Admin\Support\AdminControllerBase;
use App\Modules\Profile\Requests\ProfileRequest;
use App\Modules\Profile\Services\ProfileService;

class AdminProfileController extends AdminControllerBase
{
    public function __construct(
        private ?ProfileService $profiles = null,
        private ?ProfileRequest $profileRequest = null
    ) {
        parent::__construct();
        $this->profiles ??= new ProfileService();
        $this->profileRequest ??= new ProfileRequest();
    }

    public function show(): void
    {
        $this->requireLogin();

        $this->render('admin/profile', $this->profiles->adminProfileData($_SESSION['admin'] ?? null, $this->pullFlash()));
    }

    public function update(): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/profile');
            return;
        }

        $admin = $_SESSION['admin'] ?? null;
        if (!is_array($admin)) {
            $this->redirect('/admin/login');
            return;
        }

        $profile = $this->profiles->adminUpdateData($_POST, $admin);
        $errors = $this->profileRequest->validate($profile);

        try {
            $profile = $this->profiles->resolveAdminProfileUploads($profile);
        } catch (\RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        }

        if (!empty($errors)) {
            $this->recordValidationFailedActivity(
                'profile_update_failed',
                '更新个人资料失败：表单校验未通过。',
                $errors,
                $this->profiles->adminUpdateFailureMetadata($profile)
            );

            if ($this->wantsJson()) {
                $this->jsonValidationFailure('个人资料更新失败，请检查表单内容', $errors);
                return;
            }

            $_SESSION['profile_errors'] = $errors;
            $_SESSION['profile_old'] = $_POST;
            $this->redirect('/admin/profile');
            return;
        }

        $result = $this->profiles->updateAdminProfile($profile, $admin);
        $changes = $this->auditChanges($result['before'], $result['after'], [
            'username' => '管理员用户名',
            'profile_avatar' => '个人头像',
            'profile_home_cover' => '个人主页背景图',
            'profile_motto' => '个人座右铭',
            'copy_buttons' => '侧栏复制内容',
        ]);
        if ((string) ($profile['new_password'] ?? '') !== '') {
            $changes['password'] = [
                'label' => '登录密码',
                'old' => '未展示',
                'new' => '已重置',
            ];
        }

        $adminId = (int) ($profile['admin_id'] ?? 0);
        $this->recordAdminActivity('profile_update', ['id' => $adminId, 'username' => (string) $result['username']], '', 'success', '更新个人资料（' . $this->auditChangeSummary($changes) . '）。', [
            'resource_type' => 'admin_profile',
            'resource_id' => $adminId,
            'changes' => $changes,
        ]);

        if ($this->wantsJson()) {
            $this->sendJsonResponse([
                'ok' => true,
                'type' => 'success',
                'message' => '个人资料更新成功',
                'profile' => [
                    'username' => (string) $result['username'],
                    'avatar' => (string) $result['profile_avatar'],
                ],
                'settings' => [
                    'profile_avatar' => (string) $result['profile_avatar'],
                    'site_avatar' => (string) $result['profile_avatar'],
                    'profile_home_cover' => (string) $result['profile_home_cover'],
                    'profile_motto' => (string) $result['profile_motto'],
                    'profile_text' => (string) $result['profile_motto'],
                ],
                'copyButtons' => $result['copyButtons'],
            ]);
            return;
        }

        $this->flash('个人资料更新成功');
        $this->redirect('/admin/profile');
    }
}
