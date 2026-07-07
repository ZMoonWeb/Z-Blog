<?php

declare(strict_types=1);

namespace App\Modules\Guestbook\Services;

use App\Core\Database;
use App\Core\VisitorIdentifier;
use App\Models\GuestbookMessage;
use App\Models\PostInteractionLog;
use App\Modules\Guestbook\Repositories\GuestbookRepository;

class GuestbookService
{
    public function __construct(private ?GuestbookRepository $messages = null)
    {
        $this->messages ??= new GuestbookRepository();
    }

    public function all(): array
    {
        return $this->messages->all();
    }

    public function createTable(): void
    {
        $this->messages->createTable();
    }

    public function find(int $id): ?array
    {
        return $this->messages->find($id);
    }

    public function visibleDetail(int $id): ?array
    {
        $message = $this->messages->find($id);
        if (
            $message !== null
            && (int) ($message['status'] ?? GuestbookMessage::STATUS_HIDDEN) === GuestbookMessage::STATUS_APPROVED
            && (int) ($message['is_deleted'] ?? 0) === 0
        ) {
            return $message;
        }

        return null;
    }

    public function publicData(): array
    {
        $this->messages->createTable();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->messages->approvedPaginated($page, 15);

        return [
            'messages' => $result['data'],
            'pagination' => $result['pagination'],
            'stats' => $this->publicStats(),
            'trends' => $this->publicTrends(),
        ];
    }

    public function publicStats(): array
    {
        return [
            'total' => $this->messages->countByStatus(GuestbookMessage::STATUS_APPROVED),
            'admin' => $this->messages->countRepliedApproved(),
            'recent' => (int) Database::query(
                "SELECT COUNT(*) FROM guestbook_messages WHERE status = ? AND is_deleted = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [GuestbookMessage::STATUS_APPROVED]
            )->fetchColumn(),
        ];
    }

    public function publicTrends(): array
    {
        $trendDays = 30;
        $trendStart = (new \DateTimeImmutable('today', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))
            ->modify('-' . ($trendDays - 1) . ' days')
            ->setTime(0, 0);

        $createdStmt = Database::query(
            "SELECT DATE(created_at) AS day,
                COUNT(*) AS created_count,
                COALESCE(SUM(CASE WHEN admin_reply IS NULL OR TRIM(admin_reply) = '' THEN 1 ELSE 0 END), 0) AS pending_count
             FROM guestbook_messages
             WHERE status = ? AND is_deleted = 0 AND created_at >= ?
             GROUP BY DATE(created_at)
             ORDER BY day ASC",
            [GuestbookMessage::STATUS_APPROVED, $trendStart->format('Y-m-d H:i:s')]
        );

        $createdByDay = [];
        foreach ($createdStmt->fetchAll() as $row) {
            $day = substr((string) ($row['day'] ?? ''), 0, 10);
            if ($day === '') {
                continue;
            }

            $createdByDay[$day] = [
                'created' => (int) ($row['created_count'] ?? 0),
                'pending' => (int) ($row['pending_count'] ?? 0),
            ];
        }

        $repliedStmt = Database::query(
            "SELECT DATE(replied_at) AS day, COUNT(*) AS replied_count
             FROM guestbook_messages
             WHERE status = ?
                AND is_deleted = 0
                AND admin_reply IS NOT NULL
                AND TRIM(admin_reply) <> ''
                AND replied_at >= ?
             GROUP BY DATE(replied_at)
             ORDER BY day ASC",
            [GuestbookMessage::STATUS_APPROVED, $trendStart->format('Y-m-d H:i:s')]
        );

        $repliedByDay = [];
        foreach ($repliedStmt->fetchAll() as $row) {
            $day = substr((string) ($row['day'] ?? ''), 0, 10);
            if ($day !== '') {
                $repliedByDay[$day] = (int) ($row['replied_count'] ?? 0);
            }
        }

        $runningTotal = (int) Database::query(
            "SELECT COUNT(*) FROM guestbook_messages WHERE status = ? AND is_deleted = 0 AND created_at < ?",
            [GuestbookMessage::STATUS_APPROVED, $trendStart->format('Y-m-d H:i:s')]
        )->fetchColumn();

        $trends = [
            'total' => [],
            'recent' => [],
            'replied' => [],
            'pending' => [],
        ];

        for ($i = 0; $i < $trendDays; $i++) {
            $day = $trendStart->modify('+' . $i . ' days')->format('Y-m-d');
            $created = (int) ($createdByDay[$day]['created'] ?? 0);
            $pending = (int) ($createdByDay[$day]['pending'] ?? 0);
            $replied = (int) ($repliedByDay[$day] ?? 0);

            $runningTotal += $created;

            $trends['total'][] = $runningTotal;
            $trends['recent'][] = $created;
            $trends['replied'][] = $replied;
            $trends['pending'][] = $pending;
        }

        return $trends;
    }

    public function adminStats(): array
    {
        return [
            'total' => $this->messages->countAll(),
            'replied' => $this->messages->countReplied(),
            'unreplied' => $this->messages->countUnreplied(),
            'hidden' => $this->messages->countByStatus(GuestbookMessage::STATUS_HIDDEN),
            'deleted' => $this->messages->countDeleted(),
        ];
    }

    public function createPublicMessage(string $content): array
    {
        $visitorIdentity = VisitorIdentifier::likeIdentity();
        $visitorHash = (string) ($visitorIdentity['primary_hash'] ?? '');
        $shortHash = $visitorHash !== '' ? substr($visitorHash, 0, 12) : '';
        $remoteIp = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $nickname = $shortHash !== '' ? '访客 ' . $shortHash : ($remoteIp !== '' ? '访客 ' . $remoteIp : '未知访客');
        $isAdmin = isset($_SESSION['admin']) && is_array($_SESSION['admin']) ? 1 : 0;

        $messageId = $this->messages->create([
            'nickname' => $nickname,
            'content' => $content,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'status' => GuestbookMessage::STATUS_APPROVED,
            'is_admin' => $isAdmin,
        ]);

        PostInteractionLog::record('guestbook_post', [
            'actor_name' => $nickname,
            'visitor_hash' => $visitorHash,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'content_excerpt' => $content,
            'source_type' => 'guestbook',
            'source_id' => $messageId,
        ]);

        return [
            'id' => $messageId,
            'nickname' => $nickname,
        ];
    }

    public function updateStatus(int $id, int $status): void
    {
        $this->messages->updateStatus($id, $status);
    }

    public function updateReply(int $id, string $reply): void
    {
        $this->messages->updateReply($id, $reply);
    }

    public function delete(int $id): bool
    {
        return $this->messages->delete($id);
    }

    public function restore(int $id): bool
    {
        return $this->messages->restore($id);
    }
}
