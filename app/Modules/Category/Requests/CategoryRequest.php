<?php

declare(strict_types=1);

namespace App\Modules\Category\Requests;

use App\Core\Validator;

class CategoryRequest
{
    public function validate(array $data, ?int $ignoreId = null): array
    {
        $validator = new Validator();
        $validator
            ->required('name', $data['name'] ?? '', '分类名称不能为空')
            ->max('name', $data['name'] ?? '', 50, '分类名称不能超过 50 个字符')
            ->max('description', $data['description'] ?? '', 255, '分类描述不能超过 255 个字符');

        return $validator->errors();
    }
}
