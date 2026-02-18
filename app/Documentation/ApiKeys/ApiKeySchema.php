<?php

declare(strict_types=1);

namespace App\Documentation\ApiKeys;

use OpenApi\Attributes as OA;

/**
 * API Key Schema
 *
 * Reusable API Key object schema used across all api-key-related endpoints.
 */
#[OA\Schema(
    schema: 'ApiKey',
    title: 'ApiKey',
    description: 'API key object. The raw key value is only returned once at creation.',
    required: ['id', 'name', 'key_prefix', 'is_active', 'rate_limit_requests', 'rate_limit_window', 'user_rate_limit', 'ip_rate_limit', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(
            property: 'id',
            type: 'integer',
            description: 'Unique API key identifier',
            example: 1
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            description: 'Human-readable label for the API key',
            example: 'My Mobile App'
        ),
        new OA\Property(
            property: 'key_prefix',
            type: 'string',
            description: 'First 12 characters of the raw key (safe to display)',
            example: 'apk_a3f9c2b1'
        ),
        new OA\Property(
            property: 'key',
            type: 'string',
            description: 'Full raw API key — only present in the creation response',
            example: 'apk_a3f9c2b1d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3',
            nullable: true
        ),
        new OA\Property(
            property: 'is_active',
            type: 'boolean',
            description: 'Whether the key is currently active',
            example: true
        ),
        new OA\Property(
            property: 'rate_limit_requests',
            type: 'integer',
            description: 'Maximum requests per window (global key budget)',
            example: 600
        ),
        new OA\Property(
            property: 'rate_limit_window',
            type: 'integer',
            description: 'Rate limit window in seconds',
            example: 60
        ),
        new OA\Property(
            property: 'user_rate_limit',
            type: 'integer',
            description: 'Per-user request limit within the window when JWT is present',
            example: 60
        ),
        new OA\Property(
            property: 'ip_rate_limit',
            type: 'integer',
            description: 'Per-IP defensive limit within the window when no JWT is present',
            example: 200
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            description: 'Creation timestamp',
            example: '2026-02-18T00:00:00Z'
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            description: 'Last update timestamp',
            example: '2026-02-18T00:00:00Z'
        ),
    ],
    type: 'object'
)]
class ApiKeySchema
{
}
