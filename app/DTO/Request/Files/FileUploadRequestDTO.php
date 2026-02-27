<?php

declare(strict_types=1);

namespace App\DTO\Request\Files;

use App\DTO\Request\BaseRequestDTO;
use App\Exceptions\AuthenticationException;
use App\Exceptions\BadRequestException;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * File Upload Request DTO
 *
 * Validates the uploaded file object (Multipart or Base64) and user ownership.
 */
readonly class FileUploadRequestDTO extends BaseRequestDTO
{
    public UploadedFile|string $file;
    public int $userId;
    public ?string $filename;

    protected function rules(): array
    {
        return []; // Custom validation handled in map() due to complex file logic
    }

    protected function map(array $data): void
    {
        if (!isset($data['userId']) || !is_numeric($data['userId'])) {
            throw new AuthenticationException(lang('Auth.unauthorized'));
        }

        $this->userId = (int) $data['userId'];
        $this->filename = $data['filename'] ?? null;

        $fileData = $this->extractFileFromData($data);

        if ($fileData === null) {
            throw new BadRequestException(lang('Api.invalidRequest'), [
                'file' => lang('Files.upload.noFile')
            ]);
        }

        $this->file = $fileData;
    }

    private function extractFileFromData(array $data): UploadedFile|string|null
    {
        // 1. Check for standard UploadedFile object in 'file' key
        if (isset($data['file']) && $data['file'] instanceof UploadedFile) {
            return $data['file'];
        }

        // 2. Check for any UploadedFile in the data array (random key)
        foreach ($data as $value) {
            if ($value instanceof UploadedFile) {
                return $value;
            }
        }

        // 3. Check for Base64 string in 'file' key
        if (isset($data['file']) && is_string($data['file'])) {
            return $data['file'];
        }

        // 4. Look for base64 in other keys as fallback
        foreach ($data as $key => $value) {
            if (is_string($value) && !in_array($key, ['userId', 'userRole', 'filename'], true)) {
                if (str_starts_with($value, 'data:') || strlen($value) > 1000) {
                    return $value;
                }
            }
        }

        return null;
    }

    public function isBase64(): bool
    {
        return is_string($this->file);
    }

    public function toArray(): array
    {
        return [
            'file'     => $this->file,
            'userId'   => $this->userId,
            'filename' => $this->filename,
        ];
    }
}
