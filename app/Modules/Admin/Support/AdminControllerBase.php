<?php

declare(strict_types=1);

namespace App\Modules\Admin\Support;

use App\Core\Controller;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\SiteContent;

abstract class AdminControllerBase extends Controller
{
    private const ADMIN_LIST_PER_PAGE = 10;
    private const ADMIN_SESSION_TTL = 86400;
    private const ADMIN_SESSION_REGENERATE_INTERVAL = 900;

    protected function requireLogin(): void
    {
        $this->startSession();

        $invalidReason = null;
        if (!$this->isLoggedIn($invalidReason)) {
            if ($invalidReason === 'data') {
                $this->handleAdminDataAnomaly();
            }

            $this->clearAdminSession();
            $this->redirect('/admin/login');
        }
    }

    protected function paginateAdminList(array $items, string $basePath, int $perPage = self::ADMIN_LIST_PER_PAGE): array
    {
        $perPage = max(1, $perPage);
        $total = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($this->currentAdminPage(), $lastPage);
        $offset = ($page - 1) * $perPage;
        $query = $_GET;
        unset($query['page']);

        return [
            'items' => array_slice($items, $offset, $perPage),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'has_previous' => $page > 1,
                'has_next' => $page < $lastPage,
                'previous_url' => $this->adminPageUrl($basePath, max(1, $page - 1), $query),
                'next_url' => $this->adminPageUrl($basePath, min($lastPage, $page + 1), $query),
                'base_path' => $basePath,
                'query' => $query,
            ],
        ];
    }

    protected function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['flash'] = [
            'message' => $message,
            'type' => $type,
        ];
    }

    protected function pullFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        return is_array($flash) ? $flash : null;
    }

    protected function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

        return str_contains($accept, 'application/json')
            || $requestedWith === 'xmlhttprequest'
            || str_starts_with($requestPath, '/admin/api/');
    }

    protected function jsonValidationFailure(string $message, array $errors, array $extra = []): void
    {
        $firstError = $this->firstValidationError($errors);
        $payload = array_merge([
            'ok' => false,
            'type' => 'error',
            'message' => $firstError !== '' ? $firstError : $message,
            'errors' => $errors,
        ], $extra);

        $this->sendJsonResponse($payload, 422);
    }

    protected function redirect(string $url, int $statusCode = 302): void
    {
        if ($this->wantsJson()) {
            $flash = $this->pullFlash();
            $type = ((string) ($flash['type'] ?? 'success')) === 'error' ? 'error' : 'success';
            $message = trim((string) ($flash['message'] ?? ''));
            $isLoginRedirect = str_starts_with($url, '/admin/login');

            if ($isLoginRedirect) {
                $this->sendJsonResponse([
                    'ok' => false,
                    'type' => 'error',
                    'message' => $message !== '' ? $message : '请重新登录',
                    'redirect' => $url,
                    'login_url' => $url,
                ], 401);
                exit;
            }

            if ($message === '') {
                $message = $type === 'error' ? '操作失败' : '操作成功';
            }

            $this->sendJsonResponse([
                'ok' => $type !== 'error',
                'type' => $type,
                'message' => $message,
                'redirect' => $url,
            ], $type === 'error' ? 400 : 200);
            exit;
        }

        header('Location: ' . $url, true, $statusCode);
        exit;
    }

    protected function render(string $view, array $data = [], int $statusCode = 200): void
    {
        extract($data, EXTR_SKIP);

        $viewFile = dirname(__DIR__, 4) . '/resources/views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(500);
            echo 'View not found';
            return;
        }

        if ($statusCode !== 200) {
            http_response_code($statusCode);
        }

        require $viewFile;
    }

    protected function sendJsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    protected function firstValidationError(array $errors): string
    {
        foreach ($errors as $error) {
            if (is_array($error)) {
                $nested = $this->firstValidationError($error);
                if ($nested !== '') {
                    return $nested;
                }
                continue;
            }

            $text = trim((string) $error);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    protected function currentAdmin(): ?array
    {
        return isset($_SESSION['admin']) && is_array($_SESSION['admin']) ? $_SESSION['admin'] : null;
    }

    protected function recordAdminActivity(string $action, ?array $admin, string $username, string $status, string $message, array $metadata = []): void
    {
        try {
            AdminActivityLog::record($action, [
                'admin_id' => is_array($admin) ? (int) ($admin['id'] ?? 0) : null,
                'username' => $username !== '' ? $username : (is_array($admin) ? (string) ($admin['username'] ?? '') : ''),
                'status' => $status,
                'ip_address' => $this->clientIp(),
                'user_agent' => $this->userAgent(),
                'message' => $message,
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            // 审计记录失败不应阻断后台主流程。
        }
    }

    protected function recordValidationFailedActivity(string $action, string $message, array $errors, array $metadata = []): void
    {
        $normalizedErrors = [];
        $errorIndex = 1;
        foreach ($errors as $field => $error) {
            $label = is_int($field) ? ('错误 ' . $errorIndex) : (string) $field;
            $normalizedErrors[$label] = $this->auditValue($error);
            $errorIndex++;
        }

        $this->recordAdminActivity($action, $this->currentAdmin(), '', 'warning', $message, array_merge($metadata, [
            'errors' => $normalizedErrors,
        ]));
    }

    protected function auditChanges(array $before, array $after, array $fields): array
    {
        $changes = [];
        foreach ($fields as $field => $label) {
            $old = $this->auditValue($before[$field] ?? null);
            $new = $this->auditValue($after[$field] ?? null);
            if ($old === $new) {
                continue;
            }

            $changes[(string) $field] = [
                'label' => (string) $label,
                'old' => $old,
                'new' => $new,
            ];
        }

        return $changes;
    }

    protected function auditSnapshot(array $data, array $fields): array
    {
        $snapshot = [];
        foreach ($fields as $field => $label) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $snapshot[(string) $field] = [
                'label' => (string) $label,
                'value' => $this->auditValue($data[$field]),
            ];
        }

        return $snapshot;
    }

    protected function auditChangeSummary(array $changes): string
    {
        if (empty($changes)) {
            return '无字段变化';
        }

        $labels = [];
        foreach ($changes as $change) {
            $label = trim((string) ($change['label'] ?? ''));
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        $labels = array_values(array_unique($labels));
        $shown = array_slice($labels, 0, 4);
        $summary = implode('、', $shown);
        if (count($labels) > count($shown)) {
            $summary .= '等 ' . count($labels) . ' 项';
        }

        return $summary !== '' ? $summary : count($changes) . ' 项';
    }

    protected function categoryAuditFields(): array
    {
        return [
            'name' => '分类名称',
            'slug' => '固定链接',
            'description' => '分类描述',
        ];
    }

    protected function announcementAuditFields(): array
    {
        return [
            'level' => '公告级别',
            'content' => '公告内容',
            'content_mode' => '内容格式',
            'is_active' => '显示状态',
        ];
    }

    protected function postAuditFields(): array
    {
        return [
            'title' => '文章标题',
            'slug' => '固定链接',
            'summary' => '摘要',
            'content' => '正文',
            'content_mode' => '正文格式',
            'cover_image' => '封面图',
            'category_id' => '分类 ID',
            'tags' => '标签',
            'status' => '发布状态',
            'published_at' => '发布时间',
        ];
    }

    protected function guestbookAuditFields(): array
    {
        return [
            'status' => '审核状态',
            'is_deleted' => '删除状态',
            'admin_reply' => '站长回复',
            'replied_at' => '回复时间',
        ];
    }

    protected function recordGuestbookActivity(string $action, array $before, array $after, string $message): void
    {
        $id = (int) ($after['id'] ?? $before['id'] ?? 0);
        $changes = $this->auditChanges($before, $after, $this->guestbookAuditFields());

        $this->recordAdminActivity($action, $this->currentAdmin(), '', 'success', $message, [
            'resource_type' => 'guestbook_message',
            'resource_id' => $id,
            'nickname' => (string) ($before['nickname'] ?? $after['nickname'] ?? ''),
            'changes' => $changes,
        ]);
    }

    protected function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.gc_maxlifetime', (string) self::ADMIN_SESSION_TTL);

            $params = session_get_cookie_params();
            session_set_cookie_params([
                'lifetime' => self::ADMIN_SESSION_TTL,
                'path' => $params['path'] ?: '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);

            session_start();
        }
    }

    private function isLoggedIn(?string &$invalidReason = null): bool
    {
        $invalidReason = null;

        if (!isset($_SESSION['admin']) || !is_array($_SESSION['admin'])) {
            return false;
        }

        $adminId = (int) ($_SESSION['admin']['id'] ?? 0);
        $sessionUsername = trim((string) ($_SESSION['admin']['username'] ?? ''));
        $expiresAt = (int) ($_SESSION['admin_expires_at'] ?? 0);

        if ($adminId <= 0) {
            $invalidReason = 'data';
            return false;
        }

        if ($expiresAt <= time()) {
            return false;
        }

        $admin = Admin::findById($adminId);
        if ($admin === null) {
            $invalidReason = 'data';
            return false;
        }

        $databaseUsername = (string) ($admin['username'] ?? '');
        if ($sessionUsername === '' || $databaseUsername === '' || $sessionUsername !== $databaseUsername) {
            $invalidReason = 'data';
            return false;
        }

        $_SESSION['admin'] = [
            'id' => (int) $admin['id'],
            'username' => $databaseUsername,
        ];

        $lastRegeneratedAt = (int) ($_SESSION['admin_last_regenerated_at'] ?? 0);
        if ($lastRegeneratedAt <= 0 || time() - $lastRegeneratedAt >= self::ADMIN_SESSION_REGENERATE_INTERVAL) {
            session_regenerate_id(true);
            $_SESSION['admin_last_regenerated_at'] = time();
        }

        return true;
    }

    private function handleAdminDataAnomaly(): void
    {
        $this->clearAdminSession();

        if ($this->wantsJson()) {
            $_SESSION['admin_login_notice'] = '数据异常，请重新登录';
            $this->rememberAdminLoginNoticeCookie('data');
            $this->sendJsonResponse([
                'ok' => false,
                'type' => 'error',
                'message' => '数据异常，请重新登录',
                'login_url' => '/admin/login?notice=data',
            ], 401);
            exit;
        }

        $this->render('admin/login', [
            'error' => '',
            'username' => '',
            'loginNotice' => '数据异常，请重新登录',
            'siteSettings' => SiteContent::settings(),
        ]);
        exit;
    }

    private function clearAdminSession(): void
    {
        unset(
            $_SESSION['admin'],
            $_SESSION['admin_login_at'],
            $_SESSION['admin_expires_at'],
            $_SESSION['admin_last_regenerated_at'],
            $_SESSION['admin_ip_address'],
            $_SESSION['admin_user_agent']
        );
    }

    private function rememberAdminLoginNoticeCookie(string $notice): void
    {
        $this->setAdminLoginNoticeCookie($notice, time() + 300);
    }

    private function setAdminLoginNoticeCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie('admin_login_notice', $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function currentAdminPage(): int
    {
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
        return is_int($page) && $page > 0 ? $page : 1;
    }

    private function adminPageUrl(string $basePath, int $page, array $query = []): string
    {
        $query['page'] = max(1, $page);
        return $basePath . '?' . http_build_query($query);
    }

    private function clientIp(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($ip === '' || strlen($ip) > 45) {
            return 'unknown';
        }

        return $ip;
    }

    private function userAgent(): string
    {
        return mb_substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255, 'UTF-8');
    }

    private function auditValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (mb_strlen($value, 'UTF-8') > 1200) {
            return mb_substr($value, 0, 1200, 'UTF-8') . '...';
        }

        return $value;
    }
}
