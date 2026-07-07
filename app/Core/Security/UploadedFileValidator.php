<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Core\Config;

class UploadedFileValidator
{
    public function validate(array $file): array
    {
        $errors = [];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = '上传文件无效';
            return $errors;
        }

        $maxSize = (int) Config::get('upload.max_size', 2 * 1024 * 1024);
        if ((int) ($file['size'] ?? 0) > $maxSize) {
            $errors[] = '上传文件超过大小限制';
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowedExtensions = (array) Config::get('upload.allowed_extensions', ['jpg', 'jpeg', 'png', 'webp', 'gif']);
        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            $errors[] = '上传文件类型不允许';
        }

        $mime = $this->detectMime((string) ($file['tmp_name'] ?? ''));
        $allowedMimes = (array) Config::get('upload.allowed_mimes', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
        if ($mime !== '' && !in_array($mime, $allowedMimes, true)) {
            $errors[] = '上传文件 MIME 类型不允许';
        }

        return $errors;
    }

    private function detectMime(string $path): string
    {
        if ($path === '' || !is_file($path) || !class_exists(\finfo::class)) {
            return '';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        return is_string($mime) ? $mime : '';
    }
}
