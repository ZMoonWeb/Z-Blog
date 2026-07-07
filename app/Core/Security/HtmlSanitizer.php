<?php

declare(strict_types=1);

namespace App\Core\Security;

class HtmlSanitizer
{
    private const DEFAULT_ALLOWED_TAGS = [
        'a',
        'blockquote',
        'br',
        'code',
        'em',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'hr',
        'img',
        'li',
        'ol',
        'p',
        'pre',
        'span',
        'strong',
        'table',
        'tbody',
        'td',
        'th',
        'thead',
        'tr',
        'ul',
    ];

    public static function sanitize(string $html, ?array $allowedTags = null): string
    {
        $allowedTags = $allowedTags ?? self::DEFAULT_ALLOWED_TAGS;
        $allowed = '<' . implode('><', $allowedTags) . '>';

        $html = preg_replace('#<(script|style|iframe|object|embed|form)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = strip_tags($html, $allowed);
        $html = self::removeUnsafeAttributes($html);
        $html = self::sanitizeUrls($html);

        return $html;
    }

    public static function sanitizePostContent(string $html): string
    {
        $allowedTags = '<p><br><strong><b><em><i><u><s><a><img><ul><ol><li><blockquote><pre><code><h1><h2><h3><h4><h5><h6><hr><span>';
        $html = strip_tags($html, $allowedTags);

        $html = self::normalizeAnchors($html);
        $html = preg_replace_callback('/<img\b[^>]*>/i', static function (array $matches): string {
            $tag = $matches[0];
            if (!preg_match('/\ssrc=(["\'])(.*?)\1/i', $tag, $srcMatch)) {
                return '';
            }

            $src = trim(html_entity_decode($srcMatch[2], ENT_QUOTES, 'UTF-8'));
            if (!preg_match('#^(https?:)?//#i', $src) && !str_starts_with($src, '/')) {
                return '';
            }

            $alt = '';
            if (preg_match('/\salt=(["\'])(.*?)\1/i', $tag, $altMatch)) {
                $alt = trim(html_entity_decode($altMatch[2], ENT_QUOTES, 'UTF-8'));
            }

            return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '" loading="lazy">';
        }, $html) ?? '';

        return self::normalizeBasicTagsAndSpans($html);
    }

    public static function sanitizeSiteContent(string $html): string
    {
        $allowedTags = '<p><br><strong><b><em><i><u><s><a><ul><ol><li><blockquote><pre><code><h1><h2><h3><h4><h5><h6><hr><span>';
        $html = strip_tags($html, $allowedTags);
        $html = self::normalizeAnchors($html);

        return self::normalizeBasicTagsAndSpans($html);
    }

    private static function normalizeAnchors(string $html): string
    {
        return preg_replace_callback('/<a\b[^>]*>/i', static function (array $matches): string {
            $tag = $matches[0];
            if (!preg_match('/\shref=(["\'])(.*?)\1/i', $tag, $hrefMatch)) {
                return '<a>';
            }

            $href = trim(html_entity_decode($hrefMatch[2], ENT_QUOTES, 'UTF-8'));
            if (!preg_match('#^(https?:)?//#i', $href) && !str_starts_with($href, '/')) {
                return '<a>';
            }

            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="nofollow noopener noreferrer">';
        }, $html) ?? '';
    }

    private static function normalizeBasicTagsAndSpans(string $html): string
    {
        $html = preg_replace('/<(p|br|strong|b|em|i|u|s|ul|ol|li|blockquote|pre|code|h[1-6]|hr)\b[^>]*>/i', '<$1>', $html) ?? '';

        return preg_replace_callback('/<span\b[^>]*>/i', static function (array $matches): string {
            $tag = $matches[0];
            if (!preg_match('/\sstyle=(["\'])(.*?)\1/i', $tag, $styleMatch)) {
                return '<span>';
            }

            $style = trim(html_entity_decode($styleMatch[2], ENT_QUOTES, 'UTF-8'));
            if (!preg_match('/font-size\s*:\s*(1[2-9]|[2-4][0-9])px\s*;?/i', $style, $fontMatch)) {
                return '<span>';
            }

            return '<span style="font-size: ' . (int) $fontMatch[1] . 'px;">';
        }, $html) ?? '';
    }

    private static function removeUnsafeAttributes(string $html): string
    {
        $html = preg_replace('/\s+on[a-z]+\s*=\s*(["\']).*?\1/isu', '', $html) ?? '';
        $html = preg_replace('/\s+on[a-z]+\s*=\s*[^\s>]+/isu', '', $html) ?? '';

        return preg_replace_callback('/<span\b[^>]*>/i', static function (array $matches): string {
            $tag = $matches[0];
            if (!preg_match('/\sstyle=(["\'])(.*?)\1/i', $tag, $styleMatch)) {
                return '<span>';
            }

            $style = trim(html_entity_decode($styleMatch[2], ENT_QUOTES, 'UTF-8'));
            if (!preg_match('/font-size\s*:\s*(1[2-9]|[2-4][0-9])px\s*;?/i', $style, $fontMatch)) {
                return '<span>';
            }

            return '<span style="font-size: ' . (int) $fontMatch[1] . 'px;">';
        }, $html) ?? '';
    }

    private static function sanitizeUrls(string $html): string
    {
        return preg_replace_callback('/\s(href|src)\s*=\s*(["\'])(.*?)\2/isu', static function (array $matches): string {
            $attribute = strtolower($matches[1]);
            $quote = $matches[2];
            $url = html_entity_decode(trim($matches[3]), ENT_QUOTES, 'UTF-8');

            if (!self::isSafeUrl($url)) {
                return '';
            }

            return ' ' . $attribute . '=' . $quote . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . $quote;
        }, $html) ?? '';
    }

    private static function isSafeUrl(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto'], true);
    }
}