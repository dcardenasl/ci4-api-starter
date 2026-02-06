<?php

declare(strict_types=1);

namespace App\Documentation\Files;

use OpenApi\Attributes as OA;

/**
 * File Schema
 *
 * Reusable File object schema.
 */
#[OA\Schema(
    schema: 'File',
    title: 'File',
    description: 'File metadata',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'original_name', type: 'string', example: 'document.pdf'),
        new OA\Property(property: 'size', type: 'integer', example: 102400),
        new OA\Property(property: 'human_size', type: 'string', example: '100 KB'),
        new OA\Property(property: 'mime_type', type: 'string', example: 'application/pdf'),
        new OA\Property(property: 'url', type: 'string', example: 'https://example.com/files/1'),
        new OA\Property(property: 'uploaded_at', type: 'string', format: 'date-time', example: '2026-02-06T19:00:00Z'),
        new OA\Property(property: 'is_image', type: 'boolean', example: false),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'FileDownload',
    title: 'File Download',
    description: 'File download or storage metadata',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'original_name', type: 'string', example: 'document.pdf'),
        new OA\Property(property: 'url', type: 'string', example: 'https://example.com/files/1'),
        new OA\Property(property: 'path', type: 'string', example: '2026/02/06/document_abc.pdf'),
        new OA\Property(property: 'storage_driver', type: 'string', example: 'local'),
    ],
    type: 'object'
)]
class FileSchema
{
}
