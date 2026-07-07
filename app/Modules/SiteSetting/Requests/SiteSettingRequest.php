<?php

declare(strict_types=1);

namespace App\Modules\SiteSetting\Requests;

class SiteSettingRequest
{
    public function validate(array $data): array
    {
        $errors = [];
        $scope = (string) ($data['settings_scope'] ?? '');

        if (!in_array($scope, ['basic', 'home', 'announcement', 'about', 'guestbook', 'footer'], true)) {
            $errors['settings_scope'] = '未知的设置分区。';
        }

        return $errors;
    }
}
