<?php

declare(strict_types=1);

namespace App\Modules\Category\Services;

use App\Modules\Category\Repositories\CategoryRepository;

class CategoryService
{
    public function __construct(private ?CategoryRepository $categories = null)
    {
        $this->categories ??= new CategoryRepository();
    }

    public function all(): array
    {
        return $this->categories->all();
    }

    public function allWithPostCount(): array
    {
        return $this->categories->allWithPostCount();
    }

    public function find(int $id): ?array
    {
        return $this->categories->find($id);
    }

    public function create(array $category): array
    {
        $slug = $this->categories->generateSlug((string) $category['name']);
        $id = $this->categories->create(
            (string) $category['name'],
            $slug,
            $category['description'] !== '' ? (string) $category['description'] : null
        );

        return [
            'id' => $id,
            'slug' => $slug,
        ];
    }

    public function update(int $id, array $category): array
    {
        $slug = $this->categories->generateSlug((string) $category['name'], $id);
        $this->categories->update(
            $id,
            (string) $category['name'],
            $slug,
            $category['description'] !== '' ? (string) $category['description'] : null
        );

        return $this->categories->find($id) ?? array_merge($category, ['slug' => $slug]);
    }

    public function delete(int $id): bool
    {
        return $this->categories->delete($id);
    }

    public function defaultGroupId(?int $excludeId = null): ?int
    {
        return $this->categories->defaultGroupId($excludeId);
    }

    public function defaultData(): array
    {
        return [
            'id' => null,
            'name' => '',
            'description' => '',
        ];
    }

    public function dataFromRequest(array $data): array
    {
        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
        ];
    }
}
