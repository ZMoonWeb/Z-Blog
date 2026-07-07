<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Repositories;

use App\Models\SiteContent;

class AnnouncementRepository
{
    public function seedDefaults(): void
    {
        SiteContent::seedDefaults();
    }

    public function all(): array
    {
        return SiteContent::allAnnouncements();
    }

    public function find(int $id): ?array
    {
        return SiteContent::findAnnouncement($id);
    }

    public function create(string $level, string $content, string $mode, bool $active = true): int
    {
        return SiteContent::createAnnouncement($level, $content, $mode, $active);
    }

    public function update(int $id, string $level, string $content, string $mode, bool $active = true): void
    {
        SiteContent::updateAnnouncementById($id, $level, $content, $mode, $active);
    }

    public function delete(int $id): bool
    {
        return SiteContent::deleteAnnouncement($id);
    }
}
