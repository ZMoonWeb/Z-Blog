<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\VisitorIdentifier;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\PostInteractionLog;
use App\Models\SiteContent;
use League\CommonMark\CommonMarkConverter;

class PostController
{
    public function show(string $slug): void
    {
        SiteContent::seedDefaults();

        $post = Post::findPublishedBySlug($slug);

        if ($post === null) {
            http_response_code(404);
            $cssVersion = @filemtime(dirname(__DIR__, 2) . '/public/assets/css/common/error.css') ?: time();
            echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - 文章不存在</title>
    <link rel="icon" href="/assets/img/ZMoon.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/common/error.css?v={$cssVersion}">
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <p>你访问的文章不存在或已被移除。</p>
        <a href="/">返回首页</a>
    </div>
</body>
</html>
HTML;
            return;
        }

        $postId = (int) $post['id'];
        $visitorIdentity = VisitorIdentifier::likeIdentity();

        Post::incrementViewCount($postId);
        PostInteractionLog::record('viewed', [
            'post_id' => $postId,
            'visitor_hash' => $visitorIdentity['primary_hash'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        $post['html'] = $this->renderPostContent((string) $post['content'], (string) ($post['content_mode'] ?? 'markdown'));

        $viewCount = (int) ($post['view_count'] ?? 0);
        $likeCount = (int) ($post['like_count'] ?? 0);
        $commentCount = (int) ($post['comment_count'] ?? 0);
        $publishedAt = (string) ($post['published_at'] ?? $post['created_at'] ?? date('Y-m-d H:i:s'));

        $post['heat'] = Post::calculateHeat($viewCount, $likeCount, $commentCount, $publishedAt);

        $visitorHashes = $visitorIdentity['all_hashes'] ?? [];
        $settings = SiteContent::settings();
        $announcement = SiteContent::sidebarAnnouncement($settings);

        $this->render('post/show', [
            'title' => $post['title'],
            'post' => $post,
            'siteSettings' => $settings,
            'copyButtons' => SiteContent::copyButtons(),
            'announcement' => $announcement,
            'comments' => Comment::approvedByPostId((int) $post['id']),
            'commentCount' => $commentCount,
            'likeCount' => $likeCount,
            'liked' => Like::existsForHashes((int) $post['id'], $visitorHashes),
            'commentError' => $_SESSION['comment_error'] ?? '',
            'commentOld' => $_SESSION['comment_old'] ?? [],
            'commentSuccess' => $_SESSION['comment_success'] ?? '',
        ]);

        unset($_SESSION['comment_error'], $_SESSION['comment_old'], $_SESSION['comment_success']);
    }

    public function like(string $slug): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($this->wantsJson()) {
                $this->json(['error' => 'Method not allowed'], 405);
                return;
            }

            $this->redirect('/post/' . rawurlencode($slug));
            return;
        }

        $post = Post::findPublishedBySlug($slug);
        if ($post === null) {
            if ($this->wantsJson()) {
                $this->json(['error' => '文章不存在'], 404);
            } else {
                http_response_code(404);
                echo '文章不存在';
            }
            return;
        }

        $postId = (int) $post['id'];
        $redirectTo = $this->safeRedirectPath((string) ($_POST['redirect_to'] ?? ''));
        $visitorIdentity = VisitorIdentifier::likeIdentity();
        $liked = Like::toggleForVisitor(
            $postId,
            $visitorIdentity['primary_hash'],
            $visitorIdentity['alias_hashes'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );
        $likeCount = Like::countByPostId($postId);

        if ($this->wantsJson()) {
            $this->json([
                'liked' => $liked,
                'likeCount' => $likeCount,
            ]);
            return;
        }

        $this->redirect($redirectTo !== '' ? $redirectTo : '/post/' . rawurlencode($slug) . '#article-actions');
    }

    public function comment(string $slug): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($this->wantsJson()) {
                $this->json(['success' => false, 'message' => 'Method not allowed'], 405);
                return;
            }

            $this->redirect('/post/' . rawurlencode($slug));
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $post = Post::findPublishedBySlug($slug);
        if ($post === null) {
            if ($this->wantsJson()) {
                $this->json(['success' => false, 'message' => '文章不存在'], 404);
                return;
            }

            http_response_code(404);
            echo '文章不存在';
            return;
        }

        $content = trim((string) ($_POST['content'] ?? ''));

        $_SESSION['comment_old'] = [
            'content' => $content,
        ];

        if ($content === '' || mb_strlen($content) > 1000) {
            if ($this->wantsJson()) {
                $this->json(['success' => false, 'message' => '请输入 1-1000 个字符的评论内容'], 422);
                return;
            }

            $_SESSION['comment_error'] = '请输入 1-1000 个字符的评论内容';
            $this->redirect('/post/' . rawurlencode($slug) . '#comments');
            return;
        }

        $visitorIdentity = VisitorIdentifier::likeIdentity();
        $visitorHash = (string) ($visitorIdentity['primary_hash'] ?? '');
        $shortHash = $visitorHash !== '' ? substr($visitorHash, 0, 12) : '';
        $remoteIp = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $authorName = $shortHash !== '' ? '访客 ' . $shortHash : ($remoteIp !== '' ? '访客 ' . $remoteIp : '未知访客');

        $commentId = Comment::create([
            'post_id' => (int) $post['id'],
            'author_name' => $authorName,
            'content' => $content,
            'status' => 1,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'visitor_hash' => $visitorHash,
        ]);

        if ($this->wantsJson()) {
            unset($_SESSION['comment_old']);
            $createdAt = (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))->format('Y-m-d H:i');
            $this->json([
                'success' => true,
                'message' => '评论发送成功',
                'commentCount' => Comment::countApprovedByPostId((int) $post['id']),
                'comment' => [
                    'id' => $commentId,
                    'author_name' => $authorName,
                    'content' => $content,
                    'created_at' => $createdAt,
                ],
            ]);
            return;
        }

        unset($_SESSION['comment_old']);
        $_SESSION['comment_success'] = '评论发送成功';

        $this->redirect('/post/' . rawurlencode($slug) . '#comments');
    }

    private function renderPostContent(string $content, string $mode): string
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

    private function sanitizeHtml(string $html): string
    {
        $allowedTags = '<p><br><strong><b><em><i><u><s><a><img><ul><ol><li><blockquote><pre><code><h1><h2><h3><h4><h5><h6><hr><span>';
        $html = strip_tags($html, $allowedTags);

        $html = preg_replace_callback('/<a\b[^>]*>/i', static function (array $matches): string {
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

        $html = preg_replace('/<(p|br|strong|b|em|i|u|s|ul|ol|li|blockquote|pre|code|h[1-6]|hr)\b[^>]*>/i', '<$1>', $html) ?? '';

        $html = preg_replace_callback('/<span\b[^>]*>/i', static function (array $matches): string {
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

        return $html;
    }

    private function wantsJson(): bool
    {
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

        return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    private function safeRedirectPath(string $url): string
    {
        $url = trim($url);

        if ($url === '' || !str_starts_with($url, '/') || str_starts_with($url, '//') || preg_match('/[\r\n]/', $url)) {
            return '';
        }

        return $url;
    }

    private function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        $viewFile = dirname(__DIR__, 2) . '/resources/views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(500);
            echo 'View not found';
            return;
        }

        require $viewFile;
    }
}
