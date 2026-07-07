<?php

declare(strict_types=1);

namespace App\Modules\Post\Services;

use App\Core\Security\HtmlSanitizer;
use League\CommonMark\CommonMarkConverter;

class PostContentRenderer
{
    public function render(string $content, string $mode): string
    {
        $mode = in_array($mode, ['text', 'markdown', 'html'], true) ? $mode : 'markdown';

        if ($mode === 'text') {
            return $this->renderTextContent($content);
        }

        if ($mode === 'html') {
            return $this->sanitizeHtml($content);
        }

        return $this->renderMarkdownContent($content);
    }

    public function sanitizeHtml(string $html): string
    {
        return HtmlSanitizer::sanitizePostContent($html);
    }

    private function renderMarkdownContent(string $content): string
    {
        $converter = new CommonMarkConverter([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);

        $html = (string) $converter->convert($content);
        $html = preg_replace('/\[大字\]([\s\S]+?)\[\/大字\]/u', '<span style="font-size: 20px;">$1</span>', $html) ?? $html;

        return $this->sanitizeHtml($html);
    }

    private function renderTextContent(string $content): string
    {
        $lines = preg_split('/\R/u', $content) ?: [];
        $html = [];
        $listType = null;
        $codeLines = [];
        $inCodeBlock = false;
        $headingMap = ['一' => 1, '二' => 2, '三' => 3, '四' => 4, '五' => 5, '六' => 6];

        $closeList = static function () use (&$html, &$listType): void {
            if ($listType !== null) {
                $html[] = '</' . $listType . '>';
                $listType = null;
            }
        };

        $closeCode = static function () use (&$html, &$codeLines, &$inCodeBlock): void {
            if (!$inCodeBlock) {
                return;
            }

            $html[] = '<pre><code>' . htmlspecialchars(implode("\n", $codeLines), ENT_QUOTES, 'UTF-8') . '</code></pre>';
            $codeLines = [];
            $inCodeBlock = false;
        };

        foreach ($lines as $rawLine) {
            $rawLine = (string) $rawLine;
            $line = trim($rawLine);

            if ($line === '```') {
                if ($inCodeBlock) {
                    $closeCode();
                } else {
                    $closeList();
                    $inCodeBlock = true;
                    $codeLines = [];
                }
                continue;
            }

            if ($line === '【代码】') {
                if (!$inCodeBlock) {
                    $closeList();
                    $inCodeBlock = true;
                    $codeLines = [];
                }
                continue;
            }

            if ($line === '【/代码】') {
                if ($inCodeBlock) {
                    $closeCode();
                }
                continue;
            }

            if ($inCodeBlock) {
                $codeLines[] = $rawLine;
                continue;
            }

            if ($line === '') {
                $closeList();
                continue;
            }

            if (preg_match('/^【(?:(一|二|三|四|五|六)号)?标题】(.+)$/u', $line, $match)) {
                $closeList();
                $level = $headingMap[$match[1] ?? ''] ?? 2;
                $html[] = '<h' . $level . '>' . $this->renderTextInline($match[2]) . '</h' . $level . '>';
                continue;
            }

            if (preg_match('/^【图片：(.+?)】\s*(\S+)$/u', $line, $match) || preg_match('/^\[图片：(.+?)\]\s+(\S+)$/u', $line, $match)) {
                $closeList();
                $src = trim($match[2]);
                if ($this->isSafeRichUrl($src)) {
                    $html[] = '<p><img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars(trim($match[1]), ENT_QUOTES, 'UTF-8') . '" loading="lazy"></p>';
                }
                continue;
            }

            if (preg_match('/^【引用】(.+)$/u', $line, $match) || preg_match('/^>\s*(.+)$/u', $line, $match)) {
                $closeList();
                $html[] = '<blockquote>' . $this->renderTextInline($match[1]) . '</blockquote>';
                continue;
            }

            if (preg_match('/^【列表】(.+)$/u', $line, $match) || preg_match('/^-\s+(.+)$/u', $line, $match)) {
                if ($listType !== 'ul') {
                    $closeList();
                    $html[] = '<ul>';
                    $listType = 'ul';
                }
                $html[] = '<li>' . $this->renderTextInline($match[1]) . '</li>';
                continue;
            }

            if (preg_match('/^【编号】(.+)$/u', $line, $match) || preg_match('/^\d+\.\s+(.+)$/u', $line, $match)) {
                if ($listType !== 'ol') {
                    $closeList();
                    $html[] = '<ol>';
                    $listType = 'ol';
                }
                $html[] = '<li>' . $this->renderTextInline($match[1]) . '</li>';
                continue;
            }

            if (preg_match('/^---+$/u', $line)) {
                $closeList();
                $html[] = '<hr>';
                continue;
            }

            $closeList();
            $html[] = '<p>' . $this->renderTextInline($line) . '</p>';
        }

        $closeCode();
        $closeList();

        return $this->sanitizeHtml(implode("\n", $html));
    }

    private function renderTextInline(string $text): string
    {
        $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $html = preg_replace('/【大字】(.+?)【\/大字】/u', '<span style="font-size: 20px;">$1</span>', $html) ?? $html;
        $html = preg_replace('/【加粗】(.+?)【\/加粗】/u', '<strong>$1</strong>', $html) ?? $html;
        $html = preg_replace('/【斜体】(.+?)【\/斜体】/u', '<em>$1</em>', $html) ?? $html;
        $html = preg_replace('/\[大字\](.+?)\[\/大字\]/u', '<span style="font-size: 20px;">$1</span>', $html) ?? $html;
        $html = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $html) ?? $html;
        $html = preg_replace('/_(.+?)_/u', '<em>$1</em>', $html) ?? $html;
        $html = preg_replace('/`(.+?)`/u', '<code>$1</code>', $html) ?? $html;

        $html = preg_replace_callback('/【链接：(.+?)】((?:https?:)?\/\/[^\s<]+|\/[^\s<]+)/u', function (array $matches): string {
            $href = html_entity_decode($matches[2], ENT_QUOTES, 'UTF-8');
            if (!$this->isSafeRichUrl($href)) {
                return $matches[0];
            }

            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="nofollow noopener noreferrer">' . $matches[1] . '</a>';
        }, $html) ?? $html;

        $html = preg_replace_callback('/\[([^\[\]]+)\]\(((?:https?:)?\/\/[^)\s]+|\/[^)\s]+)\)/u', function (array $matches): string {
            $href = html_entity_decode($matches[2], ENT_QUOTES, 'UTF-8');
            if (!$this->isSafeRichUrl($href)) {
                return $matches[0];
            }

            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="nofollow noopener noreferrer">' . $matches[1] . '</a>';
        }, $html) ?? $html;

        $html = preg_replace_callback('/([^<>\s（）]+)（((?:https?:)?\/\/[^）\s]+|\/[^）\s]+)）/u', function (array $matches): string {
            $href = html_entity_decode($matches[2], ENT_QUOTES, 'UTF-8');
            if (!$this->isSafeRichUrl($href)) {
                return $matches[0];
            }

            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="nofollow noopener noreferrer">' . $matches[1] . '</a>';
        }, $html) ?? $html;

        $html = preg_replace_callback('/(?<!["\'>])\bhttps?:\/\/[^\s<]+/i', function (array $matches): string {
            $href = html_entity_decode($matches[0], ENT_QUOTES, 'UTF-8');
            if (!$this->isSafeRichUrl($href)) {
                return $matches[0];
            }

            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="nofollow noopener noreferrer">' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '</a>';
        }, $html) ?? $html;

        return $html;
    }

    private function isSafeRichUrl(string $url): bool
    {
        return preg_match('#^(https?:)?//#i', $url) === 1 || str_starts_with($url, '/');
    }
}
