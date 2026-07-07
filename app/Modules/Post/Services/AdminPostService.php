<?php

declare(strict_types=1);

namespace App\Modules\Post\Services;

use App\Modules\Category\Repositories\CategoryRepository;
use App\Modules\Post\Repositories\PostRepository;

class AdminPostService
{
    public function __construct(
        private ?PostRepository $posts = null,
        private ?CategoryRepository $categories = null
    ) {
        $this->posts ??= new PostRepository();
        $this->categories ??= new CategoryRepository();
    }

    public function all(): array
    {
        return $this->posts->all();
    }

    public function categories(): array
    {
        return $this->categories->all();
    }

    public function find(int $id): ?array
    {
        return $this->posts->find($id);
    }

    public function create(array $post): array
    {
        $post['slug'] = $this->posts->generateSlug((string) $post['title']);
        $postId = $this->posts->create($post);

        return [
            'id' => $postId,
            'post' => $post,
        ];
    }

    public function update(int $id, array $post): array
    {
        $post['slug'] = $this->posts->generateSlug((string) $post['title'], $id);
        $this->posts->update($id, $post);

        return $this->posts->find($id) ?? $post;
    }

    public function delete(int $id): bool
    {
        return $this->posts->delete($id);
    }

    public function defaultData(): array
    {
        $defaultCategoryId = $this->categories->defaultGroupId();

        return [
            'title' => '',
            'summary' => '',
            'content' => '',
            'content_mode' => 'markdown',
            'cover_image' => '',
            'category_id' => $defaultCategoryId !== null ? (string) $defaultCategoryId : '',
            'tags' => '',
            'status' => 1,
        ];
    }

    public function dataFromRequest(array $data): array
    {
        $coverImage = trim((string) ($data['cover_image'] ?? ''));

        return [
            'title' => trim((string) ($data['title'] ?? '')),
            'summary' => trim((string) ($data['summary'] ?? '')),
            'content' => trim((string) ($data['content'] ?? '')),
            'content_mode' => $data['content_mode'] ?? 'markdown',
            'cover_image' => $coverImage !== '' ? $coverImage : '/assets/img/ZMoon.png',
            'category_id' => $data['category_id'] ?? '',
            'tags' => $this->normalizePostTags($data['tags'] ?? ''),
            'status' => (int) ($data['status'] ?? 1),
        ];
    }

    private function normalizePostTags(mixed $tags): string
    {
        $items = preg_split('/[,，\s]+/u', trim((string) $tags), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $normalized = [];

        foreach ($items as $item) {
            $tag = trim((string) $item);
            if ($tag === '') {
                continue;
            }

            $key = mb_strtolower($tag, 'UTF-8');
            if (!array_key_exists($key, $normalized)) {
                $normalized[$key] = $tag;
            }

            if (count($normalized) >= 3) {
                break;
            }
        }

        return implode(',', array_values($normalized));
    }
}
