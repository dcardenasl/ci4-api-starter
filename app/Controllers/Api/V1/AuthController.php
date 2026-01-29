<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Interfaces\UserServiceInterface;
use OpenApi\Attributes as OA;

/**
 * Authentication Controller
 *
 * Handles user authentication operations (login, register, current user).
 * Now extends ApiController for consistency with the rest of the API.
 */
class AuthController extends ApiController
{
    protected UserServiceInterface $userService;

    public function __construct()
    {
        $this->userService = \Config\Services::userService();
    }

    /**
     * Get the service instance for this controller
     *
     * @return object Service instance
     */
    protected function getService(): object
    {
        return $this->userService;
    }

    /**
     * Get the success HTTP status code for a given method
     *
     * @param string $method Method name
     * @return int HTTP status code
     */
    protected function getSuccessStatus(string $method): int
    {
        return match($method) {
            'registerWithToken' => ResponseInterface::HTTP_CREATED,
            default => ResponseInterface::HTTP_OK,
        };
    }

    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'User login',
        tags: ['Authentication'],
    )]
    #[OA\RequestBody(ref: '#/components/requestBodies/LoginRequest')]
    #[OA\Response(
        response: 200,
        description: 'Login successful',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: '#/components/schemas/AuthToken'),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Invalid credentials',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'error'),
                new OA\Property(
                    property: 'errors',
                    properties: [
                        new OA\Property(property: 'credentials', type: 'string', example: 'Invalid credentials'),
                    ],
                    type: 'object'
                ),
            ]
        )
    )]
    public function login(): ResponseInterface
    {
        return $this->handleRequest('loginWithToken');
    }

    #[OA\Post(
        path: '/api/v1/auth/register',
        summary: 'Register new user',
        tags: ['Authentication'],
    )]
    #[OA\RequestBody(ref: '#/components/requestBodies/RegisterRequest')]
    #[OA\Response(
        response: 201,
        description: 'User registered successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: '#/components/schemas/AuthToken'),
            ]
        )
    )]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationErrorResponse')]
    public function register(): ResponseInterface
    {
        return $this->handleRequest('registerWithToken');
    }

    #[OA\Get(
        path: '/api/v1/auth/me',
        summary: 'Get current authenticated user',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Current user details',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'data', ref: '#/components/schemas/User'),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'error'),
                new OA\Property(property: 'message', type: 'string', example: 'User not authenticated'),
            ]
        )
    )]
    public function me(): ResponseInterface
    {
        $userId = $this->request->userId ?? null;

        if (!$userId) {
            return $this->respond([
                'status' => 'error',
                'message' => lang('Users.auth.notAuthenticated'),
            ], ResponseInterface::HTTP_UNAUTHORIZED);
        }

        return $this->handleRequest('show', ['id' => $userId]);
    }
}
