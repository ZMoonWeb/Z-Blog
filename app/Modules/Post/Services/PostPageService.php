<?php

declare(strict_types=1);

namespace App\Modules\Post\Services;

use App\Core\VisitorIdentifier;
use App\Models\SiteContent;
use App\Modules\Post\Repositories\PostRepository;

class PostPageService
{
    public function __construct(
        private ?PostRepository $posts = null,
        private ?PostContentRenderer $renderer = null,
        private ?CommentService $comments = null,
        private ?PostInteractionService $interactions = null
    ) {
        $this->posts ??= new PostRepository();
        $this->renderer ??= new PostContentRenderer();
        $this->comments ??= new CommentService();
        $this->interactions ??= new PostInteractionService();
    }

    public function find(int $id): ?array
    {
        return $this->posts->find($id);
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->posts->findPublishedBySlug($slug);
    }

    public function showData(string $slug): ?array
    {
        SiteContent::seedDefaults();

        $post = $this->posts->findPublishedBySlug($slug);
        if ($post === null) {
            return null;
        }

        $postId = (int) $post['id'];
        $visitorIdentity = VisitorIdentifier::likeIdentity();

        $this->posts->incrementViewCount($postId);
        $this->interactions->recordView($postId, $visitorIdentity);

        $post['html'] = $this->renderer->render((string) $post['content'], (string) ($post['content_mode'] ?? 'markdown'));

        $viewCount = (int) ($post['view_count'] ?? 0);
        $likeCount = (int) ($post['like_count'] ?? 0);
        $commentCount = (int) ($post['comment_count'] ?? 0);
        $publishedAt = (string) ($post['published_at'] ?? $post['created_at'] ?? date('Y-m-d H:i:s'));

        $post['heat'] = $this->posts->calculateHeat($viewCount, $likeCount, $commentCount, $publishedAt);

        $visitorHashes = (array) ($visitorIdentity['all_hashes'] ?? []);
        $settings = SiteContent::settings();

        return [
            'title' => $post['title'],
            'post' => $post,
            'siteSettings' => $settings,
            'copyButtons' => SiteContent::copyButtons(),
            'announcement' => SiteContent::sidebarAnnouncement($settings),
            'comments' => $this->comments->approvedByPostId($postId),
            'commentCount' => $commentCount,
            'likeCount' => $likeCount,
            'liked' => $this->interactions->likedByHashes($postId, $visitorHashes),
        ];
    }
}
