<?php

declare(strict_types=1);

namespace App\Documentation\Auth;

use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: 'GoogleLoginRequest',
    description: 'Google login request with ID token',
    required: true,
    content: new OA\JsonContent(
        required: ['idToken'],
        properties: [
            new OA\Property(
                property: 'idToken',
                type: 'string',
                description: 'Google ID token from frontend OAuth flow'
            ),
            new OA\Property(
                property: 'clientBaseUrl',
                type: 'string',
                nullable: true,
                description: 'Optional frontend base URL used for email links'
            ),
        ]
    )
)]
class GoogleLoginRequest
{
}
