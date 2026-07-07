<?php

declare(strict_types=1);

namespace App\Modules\Post\Services;

use App\Core\VisitorIdentifier;
use App\Models\Comment;

class CommentService
{
    public function normalizeContent(string $content): string
    {
        return trim($content);
    }

    public function validateContent(string $content): ?string
    {
        if ($content === '' || mb_strlen($content) > 1000) {
            return '请输入 1-1000 个字符的评论内容';
        }

        return null;
    }

    public function approvedByPostId(int $postId): array
    {
        return Comment::approvedByPostId($postId);
    }

    public function countApprovedByPostId(int $postId): int
    {
        return Comment::countApprovedByPostId($postId);
    }

    public function createPublicComment(int $postId, string $content): array
    {
        $visitorIdentity = VisitorIdentifier::likeIdentity();
        $visitorHash = (string) ($visitorIdentity['primary_hash'] ?? '');
        $shortHash = $visitorHash !== '' ? substr($visitorHash, 0, 12) : '';
        $remoteIp = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $authorName = $shortHash !== '' ? '访客 ' . $shortHash : ($remoteIp !== '' ? '访客 ' . $remoteIp : '未知访客');

        $commentId = Comment::create([
            'post_id' => $postId,
            'author_name' => $authorName,
            'content' => $content,
            'status' => 1,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'visitor_hash' => $visitorHash,
        ]);

        $createdAt = (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))->format('Y-m-d H:i');

        return [
            'id' => $commentId,
            'author_name' => $authorName,
            'content' => $content,
            'created_at' => $createdAt,
        ];
    }
}
