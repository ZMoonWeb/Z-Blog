<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Requests;

use App\Core\Validator;

class AnnouncementRequest
{
    public function validate(array $data): array
    {
        $validator = new Validator();
        $validator
            ->required('level', $data['level'] ?? '', '公告级别不能为空')
            ->in('level', (string) ($data['level'] ?? 'normal'), ['normal', 'important', 'urgent', 'archived'], '公告级别不正确')
            ->required('content', $data['content'] ?? '', '公告内容不能为空')
            ->in('content_mode', (string) ($data['content_mode'] ?? 'text'), ['text', 'markdown', 'html'], '公告格式不正确');

        return $validator->errors();
    }
}
