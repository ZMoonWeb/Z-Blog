<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Services;

use App\Modules\Announcement\Repositories\AnnouncementRepository;

class AnnouncementService
{
    public function __construct(private ?AnnouncementRepository $announcements = null)
    {
        $this->announcements ??= new AnnouncementRepository();
    }

    public function all(): array
    {
        return $this->announcements->all();
    }

    public function seedDefaults(): void
    {
        $this->announcements->seedDefaults();
    }

    public function find(int $id): ?array
    {
        return $this->announcements->find($id);
    }

    public function create(array $announcement): int
    {
        return $this->announcements->create(
            (string) $announcement['level'],
            (string) $announcement['content'],
            (string) $announcement['content_mode'],
            (bool) $announcement['is_active']
        );
    }

    public function update(int $id, array $announcement): array
    {
        $this->announcements->update(
            $id,
            (string) $announcement['level'],
            (string) $announcement['content'],
            (string) $announcement['content_mode'],
            (bool) $announcement['is_active']
        );

        return $this->announcements->find($id) ?? $announcement;
    }

    public function delete(int $id): bool
    {
        return $this->announcements->delete($id);
    }

    public function defaultData(): array
    {
        return [
            'level' => 'normal',
            'content' => '',
            'content_mode' => 'text',
            'is_active' => 1,
        ];
    }

    public function dataFromRequest(array $data): array
    {
        return [
            'level' => trim((string) ($data['level'] ?? 'normal')),
            'content' => (string) ($data['content'] ?? ''),
            'content_mode' => (string) ($data['content_mode'] ?? 'text'),
            'is_active' => isset($data['is_active']) ? 1 : 0,
        ];
    }
}
