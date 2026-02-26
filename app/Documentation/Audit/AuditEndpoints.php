<?php

declare(strict_types=1);

namespace App\Documentation\Audit;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/audit',
    tags: ['Audit'],
    summary: 'List audit logs',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'page',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'integer', minimum: 1)
        ),
        new OA\Parameter(
            name: 'limit',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'integer', minimum: 1)
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Paginated audit logs',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AuditResponse')),
                    new OA\Property(property: 'meta', type: 'object'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
    ]
)]
#[OA\Get(
    path: '/api/v1/audit/{id}',
    tags: ['Audit'],
    summary: 'Get audit log by ID',
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
            description: 'Audit log entry',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/AuditResponse'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 404, description: 'Audit log not found'),
    ]
)]
#[OA\Get(
    path: '/api/v1/audit/entity/{type}/{id}',
    tags: ['Audit'],
    summary: 'Get audit logs for an entity',
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'type',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string')
        ),
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
            description: 'Audit logs for entity',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AuditResponse')),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 404, description: 'Entity not found'),
    ]
)]
class AuditEndpoints
{
}
