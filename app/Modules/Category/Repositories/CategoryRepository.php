<?php

declare(strict_types=1);

namespace App\Modules\Category\Repositories;

use App\Models\Category;

class CategoryRepository
{
    public function all(): array
    {
        return Category::all();
    }

    public function allWithPostCount(): array
    {
        return Category::allWithPostCount();
    }

    public function find(int $id): ?array
    {
        return Category::find($id);
    }

    public function create(string $name, string $slug, ?string $description = null): int
    {
        return Category::create($name, $slug, $description);
    }

    public function update(int $id, string $name, string $slug, ?string $description = null): bool
    {
        return Category::update($id, $name, $slug, $description);
    }

    public function delete(int $id): bool
    {
        return Category::delete($id);
    }

    public function defaultGroupId(?int $excludeId = null): ?int
    {
        return Category::defaultGroupId($excludeId);
    }

    public function generateSlug(string $name, ?int $ignoreId = null): string
    {
        return Category::generateSlug($name, $ignoreId);
    }
}
