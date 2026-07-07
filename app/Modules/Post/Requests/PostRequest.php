<?php

declare(strict_types=1);

namespace App\Modules\Post\Requests;

use App\Core\Validator;
use App\Modules\Category\Repositories\CategoryRepository;

class PostRequest
{
    public function __construct(private ?CategoryRepository $categories = null)
    {
        $this->categories ??= new CategoryRepository();
    }

    public function validate(array $data): array
    {
        $validator = new Validator();
        $validator
            ->required('title', $data['title'] ?? '', '标题不能为空')
            ->max('title', $data['title'] ?? '', 255, '标题不能超过 255 个字符')
            ->max('summary', $data['summary'] ?? '', 500, '摘要不能超过 500 个字符')
            ->max('cover_image', $data['cover_image'] ?? '', 255, '封面图地址不能超过 255 个字符')
            ->max('tags', $data['tags'] ?? '', 500, '标签不能超过 500 个字符')
            ->required('content', $data['content'] ?? '', '内容不能为空')
            ->in('content_mode', (string) ($data['content_mode'] ?? 'markdown'), ['text', 'markdown', 'html'], '编辑模式不正确')
            ->in('status', (int) ($data['status'] ?? 1), [0, 1], '文章状态不正确');

        $categoryId = $this->normalizeCategoryId($data['category_id'] ?? null);
        if ($categoryId === 0 || ($categoryId !== null && $this->categories->find($categoryId) === null)) {
            $errors = $validator->errors();
            $errors['category_id'] = '请选择有效的分类';
            return $errors;
        }

        return $validator->errors();
    }

    private function normalizeCategoryId(mixed $categoryId): ?int
    {
        if ($categoryId === '' || $categoryId === null) {
            return null;
        }

        return ctype_digit((string) $categoryId) ? (int) $categoryId : 0;
    }
}
