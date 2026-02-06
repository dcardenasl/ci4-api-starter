<?php

declare(strict_types=1);

namespace App\Documentation\Users;

use OpenApi\Attributes as OA;

/**
 * Create User Request Body
 *
 * Request schema for creating a new user via the admin endpoint.
 * Requires username and email (password is auto-generated or set separately).
 */
#[OA\RequestBody(
    request: 'CreateUserRequest',
    description: 'Data for creating a new user',
    required: true,
    content: new OA\JsonContent(
        required: ['username', 'email'],
        properties: [
            new OA\Property(
                property: 'username',
                type: 'string',
                description: 'Unique username',
                example: 'newuser'
            ),
            new OA\Property(
                property: 'email',
                type: 'string',
                format: 'email',
                description: 'User email address',
                example: 'newuser@example.com'
            ),
        ]
    )
)]
class CreateUserRequest
{
}
