<?php

declare(strict_types=1);

namespace App\DTO\Response\Files;

use App\Interfaces\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * File Download Response DTO
 *
 * Encapsulates file metadata required for direct download or URL redirect.
 */
#[OA\Schema(
    schema: 'FileDownloadResponse',
    title: 'File Download Response',
    description: 'File metadata for downloads and external storage',
    required: ['id', 'originalName', 'url', 'path', 'storageDriver']
)]
readonly class FileDownloadResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Unique file identifier', example: 1)]
        public int $id,
        #[OA\Property(property: 'originalName', description: 'Original filename', example: 'document.pdf')]
        public string $originalName,
        #[OA\Property(description: 'Public or temporary access URL', example: 'https://example.com/storage/document.pdf')]
        public string $url,
        #[OA\Property(description: 'Storage path', example: 'uploads/abc123_document.pdf')]
        public string $path,
        #[OA\Property(property: 'storageDriver', description: 'Storage driver name', example: 's3')]
        public string $storageDriver
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            originalName: (string) ($data['original_name'] ?? ''),
            url: (string) ($data['url'] ?? ''),
            path: (string) ($data['path'] ?? ''),
            storageDriver: (string) ($data['storage_driver'] ?? '')
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'originalName' => $this->originalName,
            'url' => $this->url,
            'path' => $this->path,
            'storageDriver' => $this->storageDriver,
        ];
    }
}
