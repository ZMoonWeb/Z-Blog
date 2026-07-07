<?php

declare(strict_types=1);

namespace App\Modules\Update\Controllers;

use App\Modules\Admin\Support\AdminControllerBase;
use App\Modules\Update\Services\UpdateCheckService;

class AdminUpdateController extends AdminControllerBase
{
    public function __construct(private ?UpdateCheckService $updates = null)
    {
        parent::__construct();
        $this->updates ??= new UpdateCheckService();
    }

    public function check(): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJsonResponse([
                'success' => false,
                'type' => 'error',
                'message' => '请使用 POST 检查更新',
                'beijing_time' => $this->updates->beijingTime(),
            ], 405);
            return;
        }

        $currentVersion = $this->updates->currentBlogVersion();
        $updateUrl = $this->updates->updateUrl();

        if ($updateUrl === '') {
            $this->recordAdminActivity('update_check_failed', $this->currentAdmin(), '', 'warning', '检查更新失败：未配置更新检查地址。', [
                'resource_type' => 'system_update',
                'current_version' => $currentVersion,
            ]);
            $this->sendJsonResponse([
                'success' => false,
                'type' => 'error',
                'message' => '未配置更新检查地址，请设置 APP_UPDATE_CHECK_URL',
                'current_version' => $currentVersion,
                'beijing_time' => $this->updates->beijingTime(),
            ], 422);
            return;
        }

        if (!$this->updates->isValidUpdateCheckUrl($updateUrl)) {
            $this->recordAdminActivity('update_check_failed', $this->currentAdmin(), '', 'warning', '检查更新失败：更新检查地址不合法。', [
                'resource_type' => 'system_update',
                'current_version' => $currentVersion,
                'update_url' => $updateUrl,
            ]);
            $this->sendJsonResponse([
                'success' => false,
                'type' => 'error',
                'message' => '更新检查地址不合法或不在可信域名白名单',
                'current_version' => $currentVersion,
                'beijing_time' => $this->updates->beijingTime(),
            ], 422);
            return;
        }

        try {
            $remote = $this->updates->requestUpdateInfo($updateUrl, [
                'action' => 'check_update',
                'version' => $currentVersion,
                'request_time' => $this->updates->updateRequestTime(),
            ]);
            $latestVersion = trim((string) ($remote['latest_version'] ?? $remote['version'] ?? ''));
            if ($latestVersion === '') {
                throw new \RuntimeException('更新服务器返回缺少 latest_version');
            }

            $updateAvailable = $this->updates->resolveUpdateAvailable($remote, $currentVersion, $latestVersion);
            $releaseUrl = $this->updates->updateReleaseUrl($remote);
            $remoteMessage = trim((string) ($remote['message'] ?? ''));
            $message = $remoteMessage !== ''
                ? $remoteMessage
                : ($updateAvailable ? ('发现新版本 ' . $latestVersion) : '当前已是最新版本');

            $this->recordAdminActivity('update_check', $this->currentAdmin(), '', 'success', '检查系统更新：' . $message . '。', [
                'resource_type' => 'system_update',
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'update_available' => $updateAvailable,
            ]);

            $this->sendJsonResponse([
                'success' => true,
                'type' => $updateAvailable ? 'warning' : 'success',
                'message' => $message,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'update_available' => $updateAvailable,
                'release_url' => $releaseUrl,
                'beijing_time' => $this->updates->beijingTime(),
            ]);
        } catch (\Throwable $exception) {
            $this->recordAdminActivity('update_check_failed', $this->currentAdmin(), '', 'warning', '检查更新失败：' . $exception->getMessage(), [
                'resource_type' => 'system_update',
                'current_version' => $currentVersion,
                'error' => $exception->getMessage(),
            ]);

            $this->sendJsonResponse([
                'success' => false,
                'type' => 'error',
                'message' => '检查更新失败：' . $exception->getMessage(),
                'current_version' => $currentVersion,
                'beijing_time' => $this->updates->beijingTime(),
            ], 502);
        }
    }

    public function notes(): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJsonResponse([
                'success' => false,
                'type' => 'error',
                'message' => '请使用 POST 获取更新说明',
                'beijing_time' => $this->updates->beijingTime(),
            ], 405);
            return;
        }

        $targetVersion = trim((string) ($_POST['version'] ?? $_POST['latest_version'] ?? ''));
        $updateUrl = $this->updates->updateUrl();

        if (!$this->updates->isSafeUpdateVersion($targetVersion)) {
            $this->sendJsonResponse([
                'success' => false,
                'type' => 'error',
                'message' => '更新版本号不合法',
                'beijing_time' => $this->updates->beijingTime(),
            ], 422);
            return;
        }

        if ($updateUrl === '' || !$this->updates->isValidUpdateCheckUrl($updateUrl)) {
            $this->sendJsonResponse([
                'success' => false,
                'type' => 'error',
                'message' => $updateUrl === '' ? '未配置更新检查地址' : '更新检查地址不合法或不在可信域名白名单',
                'latest_version' => $targetVersion,
                'beijing_time' => $this->updates->beijingTime(),
            ], 422);
            return;
        }

        try {
            $remote = $this->updates->requestUpdateInfo($updateUrl, [
                'action' => 'get_update_notes',
                'version' => $targetVersion,
                'request_time' => $this->updates->updateRequestTime(),
            ]);
            $notes = $this->updates->normalizeUpdateNotes($remote);
            $message = trim((string) ($remote['message'] ?? ''));
            if ($message === '') {
                $message = '更新说明获取成功';
            }

            $this->recordAdminActivity('update_notes', $this->currentAdmin(), '', 'success', '获取更新说明：' . $targetVersion . '。', [
                'resource_type' => 'system_update',
                'latest_version' => $targetVersion,
            ]);

            $this->sendJsonResponse([
                'success' => true,
                'type' => 'success',
                'message' => $message,
                'latest_version' => $targetVersion,
                'update_notes' => $notes,
                'notes' => $notes,
                'release_url' => $this->updates->updateReleaseUrl($remote),
                'beijing_time' => $this->updates->beijingTime(),
            ]);
        } catch (\Throwable $exception) {
            $this->recordAdminActivity('update_notes_failed', $this->currentAdmin(), '', 'warning', '获取更新说明失败：' . $exception->getMessage(), [
                'resource_type' => 'system_update',
                'latest_version' => $targetVersion,
                'error' => $exception->getMessage(),
            ]);

            $this->sendJsonResponse([
                'success' => false,
                'type' => 'error',
                'message' => '获取更新说明失败：' . $exception->getMessage(),
                'latest_version' => $targetVersion,
                'beijing_time' => $this->updates->beijingTime(),
            ], 502);
        }
    }

    public function apply(): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJsonResponse([
                'success' => false,
                'type' => 'error',
                'message' => '请使用 POST 执行更新',
                'beijing_time' => $this->updates->beijingTime(),
            ], 405);
            return;
        }

        $this->sendJsonResponse([
            'success' => false,
            'type' => 'warning',
            'message' => '后台自动覆盖更新已关闭，请前往 GitHub 下载新版后手动更新。',
            'release_url' => $this->updates->updateReleaseUrl(),
            'beijing_time' => $this->updates->beijingTime(),
        ], 410);
    }
}
