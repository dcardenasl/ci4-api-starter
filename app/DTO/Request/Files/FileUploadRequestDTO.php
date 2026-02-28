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
        log_message('debug', '[FileUploadRequestDTO] payload: ' . json_encode($this->preparePayloadForLog($data)));
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
        // 1. Prioritize 'file' key
        if (isset($data['file'])) {
            $file = $data['file'];
            if ($file instanceof UploadedFile) {
                return $file;
            }
            if (is_array($file) && $this->isFileArray($file)) {
                return $this->createUploadedFileFromArray($file);
            }
            if (is_string($file) && (str_starts_with($file, 'data:') || strlen($file) > 100)) {
                return $file;
            }
        }

        // 2. Look for any UploadedFile object in payload
        if (($file = $this->findUploadedFileInArray($data)) !== null) {
            return $file;
        }

        // 3. Fallback: Search for potential Base64 or large strings in other keys
        foreach ($data as $key => $value) {
            if (in_array($key, ['userId', 'userRole', 'filename', 'visibility'], true)) {
                continue;
            }

            if (is_string($value) && (str_starts_with($value, 'data:') || strlen($value) > 1000)) {
                return $value;
            }
            if (is_array($value) && $this->isFileArray($value)) {
                return $this->createUploadedFileFromArray($value);
            }
        }

        return null;
    }

    private function findUploadedFileInArray(array $data): ?UploadedFile
    {
        foreach ($data as $value) {
            if ($value instanceof UploadedFile) {
                return $value;
            }

            if (is_array($value)) {
                if ($this->isFileArray($value)) {
                    return $this->createUploadedFileFromArray($value);
                }

                $nested = $this->findUploadedFileInArray($value);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    private function isFileArray(array $value): bool
    {
        return isset($value['tmp_name'], $value['name']);
    }

    private function createUploadedFileFromArray(array $value): UploadedFile
    {
        return new UploadedFile(
            $value['tmp_name'],
            $value['name'],
            $value['type'] ?? null,
            isset($value['size']) ? (int) $value['size'] : null,
            isset($value['error']) ? (int) $value['error'] : null,
            $value['full_path'] ?? null
        );
    }

    private function preparePayloadForLog(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[$key] = $this->sanitizeValueForLog($value);
        }
        return $result;
    }

    private function sanitizeValueForLog(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return [
                'name' => $value->getName(),
                'size' => $value->getSize(),
                'mimeType' => $value->getMimeType(),
            ];
        }

        if (is_array($value)) {
            return $this->preparePayloadForLog($value);
        }

        return $value;
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
