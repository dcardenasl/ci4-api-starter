<?php

declare(strict_types=1);

namespace App\Documentation\Auth;

use OpenApi\Attributes as OA;

/**
 * Register Request Body
 *
 * Request schema for new user registration.
 * Creates new user account with username, email, and password.
 */
#[OA\RequestBody(
    request: 'RegisterRequest',
    description: 'New user registration data',
    required: true,
    content: new OA\JsonContent(
        required: ['username', 'email', 'password'],
        properties: [
            new OA\Property(
                property: 'username',
                type: 'string',
                description: 'Unique username for the account',
                example: 'newuser'
            ),
            new OA\Property(
                property: 'email',
                type: 'string',
                format: 'email',
                description: 'User email address',
                example: 'newuser@example.com'
            ),
            new OA\Property(
                property: 'password',
                type: 'string',
                format: 'password',
                description: 'Password - Minimum 8 characters, must contain uppercase, lowercase, and number',
                example: 'Password123'
            ),
        ]
    )
)]
class RegisterRequest
{
}
