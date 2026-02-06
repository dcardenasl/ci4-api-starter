<?php

declare(strict_types=1);

namespace App\Documentation\Users;

use OpenApi\Attributes as OA;

/**
 * User Schema
 *
 * Reusable User object schema used across all user-related endpoints.
 * Represents the complete user data model with all properties.
 */
#[OA\Schema(
    schema: 'User',
    title: 'User',
    description: 'User object with all properties',
    required: ['id', 'username', 'email', 'role', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(
            property: 'id',
            type: 'integer',
            description: 'Unique user identifier',
            example: 1
        ),
        new OA\Property(
            property: 'username',
            type: 'string',
            description: 'Username for login',
            example: 'testuser'
        ),
        new OA\Property(
            property: 'email',
            type: 'string',
            format: 'email',
            description: 'User email address',
            example: 'test@example.com'
        ),
        new OA\Property(
            property: 'role',
            type: 'string',
            description: 'User role (user, admin, etc.)',
            example: 'user'
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            description: 'User creation timestamp',
            example: '2026-01-28T12:00:00Z'
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            description: 'Last update timestamp',
            example: '2026-01-28T12:00:00Z'
        ),
        new OA\Property(
            property: 'deleted_at',
            type: 'string',
            format: 'date-time',
            description: 'Soft delete timestamp',
            nullable: true,
            example: null
        ),
    ],
    type: 'object'
)]
class UserSchema
{
}
