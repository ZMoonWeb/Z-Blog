<?php

declare(strict_types=1);

namespace App\Modules\Guestbook\Requests;

class GuestbookRequest
{
    public function validate(array $data, string $scope = 'message'): array
    {
        if ($scope === 'reply') {
            return $this->validateReply($data);
        }

        return $this->validateMessage($data);
    }

    private function validateMessage(array $data): array
    {
        $errors = [];
        $content = trim((string) ($data['content'] ?? ''));

        if ($content === '' || mb_strlen($content) > 1000) {
            return ['content' => '请输入 1-1000 个字符的留言内容'];
        }

        return $errors;
    }

    private function validateReply(array $data): array
    {
        $errors = [];
        $reply = trim((string) ($data['admin_reply'] ?? ''));

        if (mb_strlen($reply) > 1000) {
            return ['admin_reply' => '站长回复不能超过 1000 个字符'];
        }

        return $errors;
    }
}
