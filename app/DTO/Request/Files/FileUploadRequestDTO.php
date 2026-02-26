<?php

declare(strict_types=1);

namespace App\DTO\Request\Files;

use App\Interfaces\DataTransferObjectInterface;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * File Upload Request DTO
 *
 * Validates the uploaded file object and user ownership.
 */
readonly class FileUploadRequestDTO implements DataTransferObjectInterface
{
    public \CodeIgniter\HTTP\Files\UploadedFile|string $file;
    public int $userId;
    public ?string $filename;

    public function __construct(array $data)
    {
        if (!isset($data['user_id']) || !is_numeric($data['user_id'])) {
            throw new \App\Exceptions\AuthenticationException(lang('Auth.unauthorized'));
        }

        $this->userId = (int) $data['user_id'];
        $this->filename = $data['filename'] ?? null;

        // 1. Check for standard UploadedFile object
        if (isset($data['file']) && $data['file'] instanceof \CodeIgniter\HTTP\Files\UploadedFile) {
            $this->file = $data['file'];
            return;
        }

        // 2. Check for any UploadedFile in the data (multipart with random key)
        foreach ($data as $value) {
            if ($value instanceof \CodeIgniter\HTTP\Files\UploadedFile) {
                $this->file = $value;
                return;
            }
        }

        // 3. Check for Base64 string
        if (isset($data['file']) && is_string($data['file'])) {
            $this->file = $data['file'];
            return;
        }

        // 4. Look for base64 in other keys if 'file' is not present (JSON fallback)
        foreach ($data as $key => $value) {
            if (is_string($value) && !in_array($key, ['user_id', 'user_role', 'filename'], true)) {
                if (str_starts_with($value, 'data:') || strlen($value) > 1000) {
                    $this->file = $value;
                    return;
                }
            }
        }

        throw new \App\Exceptions\BadRequestException(lang('Api.invalidRequest'), [
            'file' => lang('Files.upload.noFile')
        ]);
    }

    public function isBase64(): bool
    {
        return is_string($this->file);
    }

    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'user_id' => $this->userId,
            'filename' => $this->filename,
        ];
    }
}
