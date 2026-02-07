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
    required: ['id', 'email', 'role', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(
            property: 'id',
            type: 'integer',
            description: 'Unique user identifier',
            example: 1
        ),
        new OA\Property(
            property: 'first_name',
            type: 'string',
            description: 'First name',
            example: 'Alex',
            nullable: true
        ),
        new OA\Property(
            property: 'last_name',
            type: 'string',
            description: 'Last name',
            example: 'Doe',
            nullable: true
        ),
        new OA\Property(
            property: 'email',
            type: 'string',
            format: 'email',
            description: 'User email address',
            example: 'test@example.com'
        ),
        new OA\Property(
            property: 'avatar_url',
            type: 'string',
            description: 'Avatar URL',
            example: 'https://example.com/avatar.png',
            nullable: true
        ),
        new OA\Property(
            property: 'oauth_provider',
            type: 'string',
            description: 'OAuth provider (google, github)',
            example: 'google',
            nullable: true
        ),
        new OA\Property(
            property: 'oauth_provider_id',
            type: 'string',
            description: 'OAuth provider user id',
            example: '1234567890',
            nullable: true
        ),
        new OA\Property(
            property: 'role',
            type: 'string',
            description: 'User role (user, admin, etc.)',
            example: 'user'
        ),
        new OA\Property(
            property: 'status',
            type: 'string',
            description: 'Account status (pending_approval, active)',
            example: 'pending_approval'
        ),
        new OA\Property(
            property: 'email_verified_at',
            type: 'string',
            format: 'date-time',
            description: 'Email verification timestamp',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'approved_at',
            type: 'string',
            format: 'date-time',
            description: 'Approval timestamp',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'approved_by',
            type: 'integer',
            description: 'Admin user id who approved',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'invited_at',
            type: 'string',
            format: 'date-time',
            description: 'Invitation timestamp',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'invited_by',
            type: 'integer',
            description: 'Admin user id who invited',
            nullable: true,
            example: null
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
