<?php

declare(strict_types=1);

namespace App\Documentation\Users;

use OpenApi\Attributes as OA;

/**
 * Create User Request Body
 *
 * Request schema for creating a new user via the admin endpoint.
 * Requires email and password.
 */
#[OA\RequestBody(
    request: 'CreateUserRequest',
    description: 'Data for creating a new user',
    required: true,
    content: new OA\JsonContent(
        required: ['email', 'password'],
        properties: [
            new OA\Property(
                property: 'first_name',
                type: 'string',
                description: 'First name',
                example: 'Alex'
            ),
            new OA\Property(
                property: 'last_name',
                type: 'string',
                description: 'Last name',
                example: 'Doe'
            ),
            new OA\Property(
                property: 'email',
                type: 'string',
                format: 'email',
                description: 'User email address',
                example: 'newuser@example.com'
            ),
            new OA\Property(
                property: 'role',
                type: 'string',
                description: 'User role',
                example: 'user'
            ),
            new OA\Property(
                property: 'password',
                type: 'string',
                format: 'password',
                description: 'User password',
                example: 'Password123'
            ),
        ]
    )
)]
class CreateUserRequest
{
}
