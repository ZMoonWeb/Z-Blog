<?php

declare(strict_types=1);

namespace App\Modules\About\Services;

use App\Models\SiteContent;

class AboutPageService
{
    public function panelName(): string
    {
        return 'about';
    }

    public function aboutData(array $settings): array
    {
        $aboutContent = (string) ($settings['about_content'] ?? '');
        $aboutMode = (string) ($settings['about_mode'] ?? 'markdown');

        $skills = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($settings['about_skills'] ?? '')) ?: [])));

        $links = [];
        foreach (preg_split('/\R/u', (string) ($settings['about_links'] ?? '')) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 3));
            if (count($parts) < 3) {
                continue;
            }

            [$label, $icon, $url] = $parts;
            if ($label === '' || $url === '') {
                continue;
            }

            $links[] = [
                'label' => $label,
                'icon' => $icon !== '' ? $icon : 'fa-solid fa-link',
                'url' => $url,
            ];
        }

        $stats = SiteContent::aboutMetricValues();

        return [
            'aboutHtml' => SiteContent::renderRichContent($aboutContent, $aboutMode),
            'skills' => $skills,
            'links' => $links,
            'stats' => $stats,
            'statCards' => SiteContent::aboutStatCardsWithValues($stats),
            'featureCards' => SiteContent::aboutFeatureCards(),
        ];
    }
}
