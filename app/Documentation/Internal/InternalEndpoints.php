<?php

declare(strict_types=1);

namespace App\Documentation\Internal;

use OpenApi\Attributes as OA;

/**
 * Internal M2M Endpoints
 *
 * Endpoints under `api/v1/internal/*` are for trusted Domain apps only —
 * authenticated by X-App-Key (appKeyRequired filter), never by a user JWT.
 * They are reference examples of the "internal M2M endpoint" pattern:
 * a Domain app calls back into the Hub for something the Hub already owns.
 */
#[OA\Schema(
    schema: 'InternalFileMeta',
    title: 'Internal File Meta',
    description: 'Public metadata for a single file, as resolved by the internal batch-meta endpoint',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 42),
        new OA\Property(property: 'url', type: 'string', nullable: true, example: 'https://cdn.example.com/files/42/original.jpg'),
        new OA\Property(
            property: 'variants',
            type: 'object',
            additionalProperties: true,
            example: ['thumb' => ['url' => 'https://cdn.example.com/files/42/thumb.jpg', 'width' => 150, 'height' => 150]]
        ),
    ],
    type: 'object'
)]
#[OA\Post(
    path: '/api/v1/internal/email/queue',
    tags: ['Internal'],
    summary: 'Queue an email on behalf of a trusted Domain app',
    description: 'Lets a trusted Domain app queue an email through the Hub\'s existing EmailService, so the Hub remains the single email sender and Domain apps need no mailer configuration of their own.',
    security: [['appKeyAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/InternalEmailQueueRequest')
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Email accepted and queued',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        properties: [
                            new OA\Property(property: 'job_id', type: 'integer', example: 128),
                        ],
                        type: 'object'
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, description: 'Missing X-App-Key header', ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 403, description: 'X-App-Key is invalid or inactive'),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
        new OA\Response(response: 429, description: 'Rate limit exceeded'),
    ]
)]
#[OA\Get(
    path: '/api/v1/internal/files/batch-meta',
    tags: ['Internal'],
    summary: 'Batch-resolve public file metadata',
    description: 'Resolves up to 200 file IDs to their public URL and variant map in one call, without requiring a user JWT. Unknown or soft-deleted IDs are silently omitted from the response; IDs beyond the first 200 are dropped rather than erroring.',
    security: [['appKeyAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'ids',
            in: 'query',
            required: true,
            description: 'File IDs to resolve. Repeat as ids[]=1&ids[]=2 or send a comma-separated string.',
            schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'integer'), maxItems: 200)
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Metadata keyed by file ID (as a string key in the JSON object)',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        additionalProperties: new OA\AdditionalProperties(ref: '#/components/schemas/InternalFileMeta')
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, description: 'Missing X-App-Key header', ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 403, description: 'X-App-Key is invalid or inactive'),
        new OA\Response(response: 429, description: 'Rate limit exceeded'),
    ]
)]
class InternalEndpoints
{
}
