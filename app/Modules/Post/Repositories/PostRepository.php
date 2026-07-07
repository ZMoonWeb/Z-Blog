<?php

declare(strict_types=1);

namespace App\Modules\Post\Repositories;

use App\Models\Post;

class PostRepository
{
    public function all(): array
    {
        return Post::all();
    }

    public function find(int $id): ?array
    {
        return Post::find($id);
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        return Post::findPublishedBySlug($slug);
    }

    public function incrementViewCount(int $id): void
    {
        Post::incrementViewCount($id);
    }

    public function create(array $data): int
    {
        return Post::create($data);
    }

    public function update(int $id, array $data): void
    {
        Post::update($id, $data);
    }

    public function delete(int $id): bool
    {
        return Post::delete($id);
    }

    public function generateSlug(string $title, ?int $ignoreId = null): string
    {
        return Post::generateSlug($title, $ignoreId);
    }

    public function calculateHeat(int $viewCount, int $likeCount, int $commentCount, string $publishedAt): float
    {
        return Post::calculateHeat($viewCount, $likeCount, $commentCount, $publishedAt);
    }
}
