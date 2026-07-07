<?php

declare(strict_types=1);

namespace App\Modules\Post\Services;

use App\Models\Like;
use App\Modules\Interaction\Repositories\InteractionRepository;

class PostInteractionService
{
    public function __construct(private ?InteractionRepository $interactions = null)
    {
        $this->interactions ??= new InteractionRepository();
    }

    public function record(string $action, array $data): void
    {
        $this->interactions->record($action, $data);
    }

    public function recordView(int $postId, array $visitorIdentity): void
    {
        $this->record('viewed', [
            'post_id' => $postId,
            'visitor_hash' => $visitorIdentity['primary_hash'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }

    public function likedByHashes(int $postId, array $visitorHashes): bool
    {
        return Like::existsForHashes($postId, $visitorHashes);
    }

    public function toggleLike(int $postId, array $visitorIdentity): bool
    {
        return Like::toggleForVisitor(
            $postId,
            (string) ($visitorIdentity['primary_hash'] ?? ''),
            (array) ($visitorIdentity['alias_hashes'] ?? []),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );
    }

    public function likeCount(int $postId): int
    {
        return Like::countByPostId($postId);
    }
}
