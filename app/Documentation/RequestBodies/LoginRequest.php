<?php

declare(strict_types=1);

namespace App\Documentation\RequestBodies;

use OpenApi\Attributes as OA;

/**
 * Login Request Body
 *
 * Request schema for user authentication.
 * Accepts username or email with password.
 */
#[OA\RequestBody(
    request: 'LoginRequest',
    description: 'User login credentials',
    required: true,
    content: new OA\JsonContent(
        required: ['username', 'password'],
        properties: [
            new OA\Property(
                property: 'username',
                type: 'string',
                description: 'Username or email',
                example: 'testuser'
            ),
            new OA\Property(
                property: 'password',
                type: 'string',
                format: 'password',
                description: 'User password',
                example: 'testpass123'
            ),
        ]
    )
)]
class LoginRequest
{
}
