<?php

declare(strict_types=1);

namespace App\Documentation\Audit;

use OpenApi\Attributes as OA;

/**
 * Audit Log Schema
 */
#[OA\Schema(
    schema: 'AuditLog',
    title: 'Audit Log',
    description: 'Audit log entry',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 10),
        new OA\Property(property: 'user_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'action', type: 'string', example: 'update'),
        new OA\Property(property: 'entity_type', type: 'string', example: 'user'),
        new OA\Property(property: 'entity_id', type: 'integer', nullable: true, example: 5),
        new OA\Property(property: 'old_values', type: 'object', nullable: true),
        new OA\Property(property: 'new_values', type: 'object', nullable: true),
        new OA\Property(property: 'ip_address', type: 'string', example: '127.0.0.1'),
        new OA\Property(property: 'user_agent', type: 'string', example: 'Mozilla/5.0'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-02-06T19:00:00Z'),
    ],
    type: 'object'
)]
class AuditLogSchema
{
}
