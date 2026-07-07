<?php

declare(strict_types=1);

namespace App\Modules\Guestbook\Repositories;

use App\Models\GuestbookMessage;

class GuestbookRepository
{
    public function createTable(): void
    {
        GuestbookMessage::createTable();
    }

    public function all(): array
    {
        return GuestbookMessage::all();
    }

    public function approvedPaginated(int $page = 1, int $perPage = 20): array
    {
        return GuestbookMessage::approvedPaginated($page, $perPage);
    }

    public function find(int $id): ?array
    {
        return GuestbookMessage::find($id);
    }

    public function create(array $data): int
    {
        return GuestbookMessage::create($data);
    }

    public function updateStatus(int $id, int $status): void
    {
        GuestbookMessage::updateStatus($id, $status);
    }

    public function updateReply(int $id, string $reply): void
    {
        GuestbookMessage::updateReply($id, $reply);
    }

    public function delete(int $id): bool
    {
        return GuestbookMessage::delete($id);
    }

    public function restore(int $id): bool
    {
        return GuestbookMessage::restore($id);
    }

    public function countAll(): int
    {
        return GuestbookMessage::countAll();
    }

    public function countByStatus(int $status): int
    {
        return GuestbookMessage::countByStatus($status);
    }

    public function countReplied(): int
    {
        return GuestbookMessage::countReplied();
    }

    public function countUnreplied(): int
    {
        return GuestbookMessage::countUnreplied();
    }

    public function countDeleted(): int
    {
        return GuestbookMessage::countDeleted();
    }

    public function countRepliedApproved(): int
    {
        return GuestbookMessage::countRepliedApproved();
    }
}
