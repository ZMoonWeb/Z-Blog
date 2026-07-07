<?php

declare(strict_types=1);

namespace App\Modules\Content\Services;

use App\Core\Security\HtmlSanitizer;

class ContentRenderer
{
    public function postHtml(string $html): string
    {
        return HtmlSanitizer::sanitizePostContent($html);
    }

    public function siteHtml(string $html): string
    {
        return HtmlSanitizer::sanitizeSiteContent($html);
    }
}
