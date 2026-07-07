<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Models\AdminActivityLog;
use App\Models\SiteContent;
use App\Modules\Admin\Support\AdminControllerBase;
use App\Modules\Auth\Services\AdminAuthService;
use App\Modules\Auth\Services\LoginAttemptService;

class AdminAuthController extends AdminControllerBase
{
    public function __construct(
        private ?AdminAuthService $auth = null,
        private ?LoginAttemptService $loginAttempts = null
    ) {
        parent::__construct();
        $this->auth ??= new AdminAuthService();
        $this->loginAttempts ??= new LoginAttemptService();
    }

    public function login(): void
    {
        $this->startSession();
        $this->loginAttempts->createTable();
        AdminActivityLog::createTable();

        $loginNotice = $this->auth->pullAdminLoginNotice();
        $loginNoticeTitle = '登录状态异常';
        $noticeCode = (string) ($_GET['notice'] ?? '');
        $noticeCookie = (string) ($_COOKIE['admin_login_notice'] ?? '');
        if ($loginNotice === null && ($noticeCode === 'data' || $noticeCookie === 'data')) {
            $loginNotice = '数据异常，请重新登录';
        }
        if ($noticeCookie !== '') {
            $this->auth->clearAdminLoginNoticeCookie();
        }

        $invalidReason = null;
        if ($this->auth->isLoggedIn($invalidReason)) {
            $this->redirect('/admin');
            return;
        }

        if ($invalidReason === 'client_changed') {
            $loginNotice = '登录环境已变化，请重新登录';
            $this->auth->clearAdminSession();
        } elseif ($invalidReason === 'data') {
            $loginNotice = '数据异常，请重新登录';
            $this->auth->clearAdminSession();
        }

        $error = '';
        $username = trim((string) ($_POST['username'] ?? ''));
        $ipAddress = $this->auth->clientIp();
        $userAgent = $this->auth->userAgent();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = (string) ($_POST['password'] ?? '');
            $lockStatus = $this->loginAttempts->status($ipAddress);

            if ($lockStatus['locked']) {
                http_response_code(429);
                $error = '当前 IP 登录失败次数过多，请等待 ' . $this->auth->formatLockRemaining($lockStatus['remaining_seconds']) . ' 后再试';
                $loginNoticeTitle = 'IP 已被锁定';
                $loginNotice = $error;
                $this->recordAdminActivity('login_blocked', null, $username, 'danger', '锁定期间继续尝试登录，已拒绝。', [
                    'remaining_seconds' => $lockStatus['remaining_seconds'],
                    'attempts' => $lockStatus['attempts'],
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);
            } elseif ($username === '' || $password === '') {
                $error = '请输入用户名和密码';
            } else {
                $admin = $this->auth->authenticate($username, $password);

                if ($admin !== null) {
                    $this->loginAttempts->clear($ipAddress);
                    $this->auth->storeAdminSession($admin, $ipAddress, $userAgent);

                    $this->recordAdminActivity('login_success', $admin, $username, 'success', '管理员登录成功。', [
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent,
                    ]);

                    $this->redirect('/admin');
                    return;
                }

                $failureStatus = $this->loginAttempts->recordFailure(
                    $ipAddress,
                    LoginAttemptService::ADMIN_LOGIN_MAX_ATTEMPTS,
                    LoginAttemptService::ADMIN_LOGIN_LOCK_SECONDS,
                    LoginAttemptService::ADMIN_LOGIN_WINDOW_SECONDS
                );

                if ($failureStatus['locked']) {
                    http_response_code(429);
                    $error = '用户名或密码错误，当前 IP 已锁定 10 分钟';
                    $loginNoticeTitle = 'IP 已被锁定';
                    $loginNotice = '连续 3 次登录失败，当前 IP 已被锁定 10 分钟。';
                    $this->recordAdminActivity('login_ip_locked', null, $username, 'danger', '连续 3 次登录失败，IP 已锁定 10 分钟。', [
                        'attempts' => $failureStatus['attempts'],
                        'remaining_seconds' => $failureStatus['remaining_seconds'],
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent,
                    ]);
                } else {
                    $remainingAttempts = max(0, LoginAttemptService::ADMIN_LOGIN_MAX_ATTEMPTS - $failureStatus['attempts']);
                    $error = '用户名或密码错误，还可尝试 ' . $remainingAttempts . ' 次';
                    $this->recordAdminActivity('login_failed', null, $username, 'warning', '管理员登录失败。', [
                        'attempts' => $failureStatus['attempts'],
                        'remaining_attempts' => $remainingAttempts,
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent,
                    ]);
                }
            }
        }

        $this->render('admin/login', [
            'error' => $error,
            'username' => $username,
            'loginNotice' => $loginNotice,
            'loginNoticeTitle' => $loginNoticeTitle,
            'siteSettings' => SiteContent::settings(),
        ]);
    }

    public function logout(): void
    {
        $this->startSession();

        $admin = $this->auth->currentAdmin();
        $this->recordAdminActivity('logout', $admin, (string) ($admin['username'] ?? ''), 'info', '管理员退出登录。');
        $this->auth->clearAdminSession();

        if ($this->wantsJson()) {
            $this->sendJsonResponse([
                'ok' => true,
                'type' => 'success',
                'message' => '已退出登录',
                'redirect' => '/admin/login',
            ]);
            return;
        }

        $this->redirect('/admin/login');
    }
}
