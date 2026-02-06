<?php

declare(strict_types=1);

namespace App\Documentation\Auth;

use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/api/v1/auth/login',
    tags: ['Authentication'],
    summary: 'Login with username or email',
    requestBody: new OA\RequestBody(
        ref: '#/components/requestBodies/LoginRequest'
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Login successful',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'access_token', type: 'string'),
                            new OA\Property(property: 'refresh_token', type: 'string'),
                            new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                            new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                        ]
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
    ]
)]
#[OA\Post(
    path: '/api/v1/auth/register',
    tags: ['Authentication'],
    summary: 'Register a new user',
    requestBody: new OA\RequestBody(
        ref: '#/components/requestBodies/RegisterRequest'
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Registration successful',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'access_token', type: 'string'),
                            new OA\Property(property: 'refresh_token', type: 'string'),
                            new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                            new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                            new OA\Property(property: 'message', type: 'string'),
                        ]
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
    ]
)]
#[OA\Get(
    path: '/api/v1/auth/me',
    tags: ['Authentication'],
    summary: 'Get current user profile',
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Current user profile',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
    ]
)]
class AuthEndpoints
{
}
