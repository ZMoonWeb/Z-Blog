<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Category
{
    public static function createTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            slug VARCHAR(50) NOT NULL UNIQUE,
            description VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        Database::query($sql);
    }

    public static function create(string $name, string $slug, ?string $description = null): int
    {
        Database::query(
            "INSERT INTO categories (name, slug, description, created_at) VALUES (?, ?, ?, ?)",
            [$name, $slug, $description, self::now()]
        );

        return (int) Database::getInstance()->lastInsertId();
    }

    public static function all(): array
    {
        $stmt = Database::query("SELECT * FROM categories ORDER BY name");
        return $stmt->fetchAll();
    }

    public static function allWithPostCount(): array
    {
        $stmt = Database::query(
            "SELECT categories.*, COUNT(posts.id) AS post_count
            FROM categories
            LEFT JOIN posts ON posts.category_id = categories.id
            GROUP BY categories.id
            ORDER BY categories.name"
        );

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::query("SELECT * FROM categories WHERE id = ? LIMIT 1", [$id]);
        $category = $stmt->fetch();

        return $category ?: null;
    }

    public static function defaultGroupId(?int $excludeId = null): ?int
    {
        $params = [];
        $excludeSql = '';

        if ($excludeId !== null) {
            $excludeSql = ' AND id <> ?';
            $params[] = $excludeId;
        }

        $stmt = Database::query(
            "SELECT id FROM categories WHERE slug = 'default-group'" . $excludeSql . " LIMIT 1",
            $params
        );
        $id = $stmt->fetchColumn();

        if ($id === false) {
            $params = ['默认分组'];
            $excludeSql = '';

            if ($excludeId !== null) {
                $excludeSql = ' AND id <> ?';
                $params[] = $excludeId;
            }

            $stmt = Database::query(
                "SELECT id FROM categories WHERE name = ?" . $excludeSql . " LIMIT 1",
                $params
            );
            $id = $stmt->fetchColumn();
        }

        return $id !== false ? (int) $id : null;
    }

    public static function update(int $id, string $name, string $slug, ?string $description = null): bool
    {
        $stmt = Database::query(
            "UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?",
            [$name, $slug, $description, $id]
        );

        return $stmt->rowCount() > 0;
    }

    public static function delete(int $id): bool
    {
        Database::query("UPDATE posts SET category_id = ? WHERE category_id = ?", [self::defaultGroupId($id), $id]);
        $stmt = Database::query("DELETE FROM categories WHERE id = ?", [$id]);

        return $stmt->rowCount() > 0;
    }

    public static function exists(int $id): bool
    {
        $stmt = Database::query("SELECT COUNT(*) FROM categories WHERE id = ?", [$id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        if ($ignoreId !== null) {
            $stmt = Database::query(
                "SELECT COUNT(*) FROM categories WHERE slug = ? AND id <> ?",
                [$slug, $ignoreId]
            );
        } else {
            $stmt = Database::query("SELECT COUNT(*) FROM categories WHERE slug = ?", [$slug]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = trim(strtolower($name));
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug) ?: '';
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'category-' . date('YmdHis');
        }

        $base = $slug;
        $index = 2;

        while (self::slugExists($slug, $ignoreId)) {
            $slug = $base . '-' . $index;
            $index++;
        }

        return $slug;
    }

    private static function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get() ?: 'Asia/Shanghai')))->format('Y-m-d H:i:s');
    }
}
