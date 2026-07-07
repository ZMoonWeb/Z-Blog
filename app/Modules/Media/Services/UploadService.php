<?php

declare(strict_types=1);

namespace App\Modules\Media\Services;

use App\Core\Security\UploadedFileValidator;

class UploadService
{
    public function __construct(private ?UploadedFileValidator $validator = null)
    {
        $this->validator ??= new UploadedFileValidator();
    }

    public function validateImage(array $file): array
    {
        return $this->validator->validate($file);
    }

    public function resolveUploadedSettingImage(string $field, string $currentValue, string $label, string $prefix): string
    {
        $file = $_FILES[$field] ?? null;
        if (!is_array($file)) {
            return $currentValue;
        }

        return $this->resolveUploadedImageValue($file, $currentValue, $label, $prefix);
    }

    public function resolveUploadedImageValue(array $file, string $currentValue, string $label, string $prefix): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return $currentValue;
        }

        return $this->storeUploadedImage($file, $label, $prefix);
    }

    /**
     * @return array<int, array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    public function normalizeUploadedFilesArray(string $field): array
    {
        $files = $_FILES[$field] ?? null;
        if (
            !is_array($files)
            || !isset($files['name'], $files['type'], $files['tmp_name'], $files['error'], $files['size'])
            || !is_array($files['name'])
        ) {
            return [];
        }

        $normalized = [];
        foreach (array_keys($files['name']) as $index) {
            $normalized[$index] = [
                'name' => (string) ($files['name'][$index] ?? ''),
                'type' => (string) ($files['type'][$index] ?? ''),
                'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
                'error' => (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($files['size'][$index] ?? 0),
            ];
        }

        return $normalized;
    }

    private function storeUploadedImage(array $file, string $label, string $prefix): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->uploadErrorMessage($error, $label));
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new \RuntimeException($label . ' 上传失败，请重新选择文件。');
        }

        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new \RuntimeException($label . ' 仅支持 jpg、jpeg、png、webp、gif 格式。');
        }

        $uploadErrors = $this->validateImage($file);
        if ($uploadErrors !== []) {
            throw new \RuntimeException($label . ' ' . $uploadErrors[0]);
        }

        if (@getimagesize($tmpName) === false) {
            throw new \RuntimeException($label . ' 不是有效的图片文件。');
        }

        $uploadDirectory = dirname(__DIR__, 4) . '/public/uploads';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
            throw new \RuntimeException('上传目录创建失败，请检查 /public/uploads 是否可写。');
        }

        if (!is_writable($uploadDirectory)) {
            @chmod($uploadDirectory, 0775);
        }

        if (!is_writable($uploadDirectory)) {
            throw new \RuntimeException('上传目录不可写，请将 /public/uploads 的属主设置为 PHP 运行用户，并赋予 775 或 755 写入权限。');
        }

        $safePrefix = preg_replace('/[^a-z0-9-]+/', '-', strtolower($prefix)) ?: 'image';
        $safePrefix = trim($safePrefix, '-') ?: 'image';
        $filename = $safePrefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(5)) . '.' . $extension;
        $targetPath = $uploadDirectory . '/' . $filename;

        if (!@move_uploaded_file($tmpName, $targetPath)) {
            throw new \RuntimeException($label . ' 保存失败，请检查 /public/uploads 是否允许 PHP 写入。');
        }

        @chmod($targetPath, 0644);

        return '/uploads/' . $filename;
    }

    private function uploadErrorMessage(int $error, string $label): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $label . ' 超出上传大小限制。',
            UPLOAD_ERR_PARTIAL => $label . ' 上传不完整，请重新上传。',
            UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录，无法上传 ' . $label . '。',
            UPLOAD_ERR_CANT_WRITE => $label . ' 写入失败，请检查目录权限。',
            UPLOAD_ERR_EXTENSION => $label . ' 被服务器扩展阻止上传。',
            default => $label . ' 上传失败，请重试。',
        };
    }
}
