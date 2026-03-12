<?php

declare(strict_types=1);

namespace App\Documentation\Auth;

use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/api/v1/auth/login',
    tags: ['Authentication'],
    summary: 'Login with email',
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
                    new OA\Property(property: 'data', ref: '#/components/schemas/LoginResponse'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse'),
    ]
)]
#[OA\Post(
    path: '/api/v1/auth/google-login',
    tags: ['Authentication'],
    summary: 'Login with Google ID token',
    description: 'If the user already exists, Google login succeeds immediately. If the user is new, the account is created in pending_approval and requires admin approval.',
    requestBody: new OA\RequestBody(
        ref: '#/components/requestBodies/GoogleLoginRequest'
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Google login successful',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/LoginResponse'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(
            response: 202,
            description: 'Google login received, account pending admin approval',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        properties: [
                            new OA\Property(
                                property: 'user',
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 123),
                                    new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                                    new OA\Property(property: 'status', type: 'string', example: 'pending_approval'),
                                ]
                            ),
                        ]
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, ref: '#/components/responses/UnauthorizedResponse'),
        new OA\Response(response: 403, description: 'Account pending approval'),
        new OA\Response(response: 409, description: 'Account/provider conflict'),
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
                    new OA\Property(property: 'data', ref: '#/components/schemas/RegisterResponse'),
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
                    new OA\Property(property: 'data', ref: '#/components/schemas/UserResponse'),
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
