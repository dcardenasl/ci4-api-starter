<?php

declare(strict_types=1);

namespace App\DTO\Response\Files;

use App\Interfaces\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * File Response DTO
 *
 * Standardized output for file metadata.
 */
#[OA\Schema(
    schema: 'FileResponse',
    title: 'File Response',
    description: 'File metadata and access URL',
    required: ['id', 'original_name', 'mime_type', 'size', 'url']
)]
readonly class FileResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Unique file identifier', example: 1)]
        public int $id,
        #[OA\Property(property: 'original_name', description: 'Original filename', example: 'document.pdf')]
        public string $original_name,
        #[OA\Property(description: 'Stored filename', example: 'abc123_document.pdf')]
        public string $filename,
        #[OA\Property(property: 'mime_type', description: 'MIME type', example: 'application/pdf')]
        public string $mime_type,
        #[OA\Property(property: 'size', description: 'File size in bytes', example: 102400)]
        public int $file_size,
        #[OA\Property(property: 'human_size', description: 'Human readable file size', example: '100 KB')]
        public string $human_size,
        #[OA\Property(property: 'is_image', description: 'Whether the file is an image', example: false)]
        public bool $is_image,
        #[OA\Property(description: 'Public or temporary access URL', example: 'https://example.com/storage/file.pdf')]
        public string $url,
        #[OA\Property(property: 'uploaded_at', description: 'Upload timestamp', example: '2026-02-26 12:00:00', nullable: true)]
        public ?string $created_at = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        $created_at = $data['created_at'] ?? $data['uploaded_at'] ?? null;
        if ($created_at instanceof \DateTimeInterface) {
            $created_at = $created_at->format('Y-m-d H:i:s');
        }

        // Handle case where we receive an entity array or raw data
        $size = (int) ($data['file_size'] ?? $data['size'] ?? 0);
        $is_image = isset($data['mime_type']) && str_starts_with((string)$data['mime_type'], 'image/');

        return new self(
            id: (int) ($data['id'] ?? 0),
            original_name: (string) ($data['original_name'] ?? ''),
            filename: (string) ($data['filename'] ?? $data['stored_name'] ?? ''),
            mime_type: (string) ($data['mime_type'] ?? ''),
            file_size: $size,
            human_size: (string) ($data['human_size'] ?? self::calculateHumanSize($size)),
            is_image: (bool) ($data['is_image'] ?? $is_image),
            url: (string) ($data['url'] ?? ''),
            created_at: $created_at ? (string) $created_at : null
        );
    }

    private static function calculateHumanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'size' => $this->file_size,
            'human_size' => $this->human_size,
            'is_image' => $this->is_image,
            'url' => $this->url,
            'uploaded_at' => $this->created_at,
        ];
    }
}
