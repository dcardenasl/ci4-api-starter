<?php

declare(strict_types=1);

namespace App\DTO\Response\Files;

use App\Interfaces\DataTransferObjectInterface;

/**
 * File Download Response DTO
 *
 * Encapsulates file metadata required for direct download or URL redirect.
 */
readonly class FileDownloadResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        public int $id,
        public string $originalName,
        public string $url,
        public string $path,
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
            'original_name' => $this->originalName,
            'url' => $this->url,
            'path' => $this->path,
            'storage_driver' => $this->storageDriver,
        ];
    }
}
