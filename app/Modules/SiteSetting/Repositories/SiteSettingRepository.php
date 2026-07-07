<?php

declare(strict_types=1);

namespace App\Modules\SiteSetting\Repositories;

use App\Models\SiteContent;

class SiteSettingRepository
{
    public function seedDefaults(): void
    {
        SiteContent::seedDefaults();
    }

    public function all(): array
    {
        return SiteContent::settings();
    }

    public function update(array $settings): void
    {
        SiteContent::updateSettings($settings);
    }

    public function heroSlides(): array
    {
        return SiteContent::heroSlides();
    }

    public function heroSlidesToLines(): string
    {
        return SiteContent::heroSlidesToLines();
    }

    public function updateHeroSlidesFromLines(string $lines): void
    {
        SiteContent::updateHeroSlidesFromLines($lines);
    }

    public function updateSidebarAnnouncement(string $content, string $mode): void
    {
        SiteContent::updateSidebarAnnouncement($content, $mode);
    }
}
