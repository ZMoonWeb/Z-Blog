<?php

declare(strict_types=1);

namespace App\Modules\Post\Controllers;

use App\Core\Controller;
use App\Modules\Post\Services\CommentService;
use App\Modules\Post\Services\PostInteractionService;
use App\Modules\Post\Services\PostPageService;

class PostController extends Controller
{
    public function __construct(
        private ?PostPageService $pages = null,
        private ?PostInteractionService $interactions = null,
        private ?CommentService $comments = null
    ) {
        parent::__construct();
        $this->pages ??= new PostPageService();
        $this->interactions ??= new PostInteractionService();
        $this->comments ??= new CommentService();
    }

    public function show(string $slug): void
    {
        $data = $this->pages->showData($slug);
        if ($data === null) {
            $this->renderMissingPost();
            return;
        }

        $this->render('post/show', array_merge($data, [
            'commentError' => $_SESSION['comment_error'] ?? '',
            'commentOld' => $_SESSION['comment_old'] ?? [],
            'commentSuccess' => $_SESSION['comment_success'] ?? '',
        ]));

        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['comment_error'], $_SESSION['comment_old'], $_SESSION['comment_success']);
        }
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

        $post = $this->pages->findPublishedBySlug($slug);
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
        $visitorIdentity = \App\Core\VisitorIdentifier::likeIdentity();
        $liked = $this->interactions->toggleLike($postId, $visitorIdentity);
        $likeCount = $this->interactions->likeCount($postId);

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

        $this->startSession();

        $post = $this->pages->findPublishedBySlug($slug);
        if ($post === null) {
            if ($this->wantsJson()) {
                $this->json(['success' => false, 'message' => '文章不存在'], 404);
                return;
            }

            http_response_code(404);
            echo '文章不存在';
            return;
        }

        $content = $this->comments->normalizeContent((string) ($_POST['content'] ?? ''));

        $_SESSION['comment_old'] = [
            'content' => $content,
        ];

        $error = $this->comments->validateContent($content);
        if ($error !== null) {
            if ($this->wantsJson()) {
                $this->json(['success' => false, 'message' => $error], 422);
                return;
            }

            $_SESSION['comment_error'] = $error;
            $this->redirect('/post/' . rawurlencode($slug) . '#comments');
            return;
        }

        $comment = $this->comments->createPublicComment((int) $post['id'], $content);

        if ($this->wantsJson()) {
            unset($_SESSION['comment_old']);
            $this->json([
                'success' => true,
                'message' => '评论发送成功',
                'commentCount' => $this->comments->countApprovedByPostId((int) $post['id']),
                'comment' => $comment,
            ]);
            return;
        }

        unset($_SESSION['comment_old']);
        $_SESSION['comment_success'] = '评论发送成功';

        $this->redirect('/post/' . rawurlencode($slug) . '#comments');
    }

    private function renderMissingPost(): void
    {
        http_response_code(404);
        $cssVersion = @filemtime(dirname(__DIR__, 4) . '/public/assets/css/common/error.css') ?: time();
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
    }

    private function safeRedirectPath(string $url): string
    {
        $url = trim($url);

        if ($url === '' || !str_starts_with($url, '/') || str_starts_with($url, '//') || preg_match('/[\r\n]/', $url)) {
            return '';
        }

        return $url;
    }
}
