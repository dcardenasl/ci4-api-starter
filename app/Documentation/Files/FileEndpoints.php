<?php

declare(strict_types=1);

namespace App\Documentation\Files;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/files',
    tags: ['Files'],
    summary: 'List files for current user',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'page',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'integer', minimum: 1)
        ),
        new OA\Parameter(
            name: 'perPage',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'integer', minimum: 1)
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'File list',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/FileResponse')
                    ),
                    new OA\Property(
                        property: 'meta',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'total', type: 'integer', example: 45),
                            new OA\Property(property: 'perPage', type: 'integer', example: 20),
                            new OA\Property(property: 'page', type: 'integer', example: 1),
                            new OA\Property(property: 'lastPage', type: 'integer', example: 3),
                            new OA\Property(property: 'from', type: 'integer', example: 1),
                            new OA\Property(property: 'to', type: 'integer', example: 20),
                        ]
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
    ]
)]
#[OA\Post(
    path: '/api/v1/files/upload',
    tags: ['Files'],
    summary: 'Upload a file',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['file'],
                properties: [
                    new OA\Property(property: 'file', type: 'string', format: 'binary'),
                ],
                type: 'object'
            )
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'File uploaded',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        ref: '#/components/schemas/FileResponse'
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
    ]
)]
#[OA\Get(
    path: '/api/v1/files/{id}',
    tags: ['Files'],
    summary: 'Download or get file metadata',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'File metadata (or file download for local storage)',
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(property: 'status', type: 'string', example: 'success'),
                            new OA\Property(
                                property: 'data',
                                ref: '#/components/schemas/FileDownloadResponse'
                            ),
                        ],
                        type: 'object'
                    )
                ),
                new OA\MediaType(
                    mediaType: 'application/octet-stream',
                    schema: new OA\Schema(type: 'string', format: 'binary')
                ),
            ]
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 404, description: 'File not found'),
    ]
)]
#[OA\Delete(
    path: '/api/v1/files/{id}',
    tags: ['Files'],
    summary: 'Delete file',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer')
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'File deleted',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'message', type: 'string'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 404, description: 'File not found'),
    ]
)]
class FileEndpoints
{
}
