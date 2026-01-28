<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\ApiController;
use App\Interfaces\UserServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Attributes as OA;

class UserController extends ApiController
{
    protected UserServiceInterface $userService;

    public function __construct()
    {
        // Usar Config\Services para inyección de dependencias
        $this->userService = \Config\Services::userService();
    }

    /**
     * Get the service instance
     *
     * @return object
     */
    protected function getService(): object
    {
        return $this->userService;
    }

    /**
     * Get the appropriate HTTP status code for successful operations
     *
     * @param string $method The service method name
     * @return int HTTP status code
     */
    protected function getSuccessStatus(string $method): int
    {
        return match ($method) {
            'store' => ResponseInterface::HTTP_CREATED,
            default => ResponseInterface::HTTP_OK,
        };
    }

    #[OA\Get(
        path: '/api/v1/users',
        summary: 'Get all users',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
    )]
    #[OA\Response(
        response: 200,
        description: 'List of users',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', example: '1'),
                            new OA\Property(property: 'username', type: 'string', example: 'testuser'),
                            new OA\Property(property: 'email', type: 'string', example: 'test@example.com'),
                            new OA\Property(property: 'role', type: 'string', example: 'user'),
                            new OA\Property(property: 'created_at', type: 'object'),
                            new OA\Property(property: 'updated_at', type: 'object'),
                            new OA\Property(property: 'deleted_at', type: 'string', nullable: true),
                        ],
                        type: 'object'
                    )
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Authorization header missing'),
            ]
        )
    )]
    public function index(): ResponseInterface
    {
        return $this->handleRequest('index');
    }

    #[OA\Get(
        path: '/api/v1/users/{id}',
        summary: 'Get user by ID',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        example: 1
    )]
    #[OA\Response(
        response: 200,
        description: 'User details',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: '1'),
                        new OA\Property(property: 'username', type: 'string', example: 'testuser'),
                        new OA\Property(property: 'email', type: 'string', example: 'test@example.com'),
                        new OA\Property(property: 'role', type: 'string', example: 'user'),
                        new OA\Property(property: 'created_at', type: 'object'),
                        new OA\Property(property: 'updated_at', type: 'object'),
                        new OA\Property(property: 'deleted_at', type: 'string', nullable: true),
                    ],
                    type: 'object'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'User not found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Usuario no encontrado'),
            ]
        )
    )]
    public function show($id = null): ResponseInterface
    {
        return $this->handleRequest('show', ['id' => $id]);
    }

    #[OA\Post(
        path: '/api/v1/users',
        summary: 'Create new user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['username', 'email'],
            properties: [
                new OA\Property(property: 'username', type: 'string', example: 'newuser'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'newuser@example.com'),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'User created successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: '1'),
                        new OA\Property(property: 'username', type: 'string', example: 'newuser'),
                        new OA\Property(property: 'email', type: 'string', example: 'newuser@example.com'),
                        new OA\Property(property: 'role', type: 'string', example: 'user'),
                        new OA\Property(property: 'created_at', type: 'object'),
                        new OA\Property(property: 'updated_at', type: 'object'),
                        new OA\Property(property: 'deleted_at', type: 'string', nullable: true),
                    ],
                    type: 'object'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Validation error',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    example: ['email' => 'This email is already registered']
                ),
            ]
        )
    )]
    public function create(): ResponseInterface
    {
        return $this->handleRequest('store');
    }

    #[OA\Put(
        path: '/api/v1/users/{id}',
        summary: 'Update user',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        example: 1
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'username', type: 'string', example: 'updateduser'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'updated@example.com'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'User updated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: '1'),
                        new OA\Property(property: 'username', type: 'string', example: 'updateduser'),
                        new OA\Property(property: 'email', type: 'string', example: 'updated@example.com'),
                        new OA\Property(property: 'role', type: 'string', example: 'user'),
                        new OA\Property(property: 'created_at', type: 'object'),
                        new OA\Property(property: 'updated_at', type: 'object'),
                        new OA\Property(property: 'deleted_at', type: 'string', nullable: true),
                    ],
                    type: 'object'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Validation error or user not found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    example: ['fields' => 'At least one field (email or username) is required']
                ),
            ]
        )
    )]
    public function update($id = null): ResponseInterface
    {
        return $this->handleRequest('update', ['id' => $id]);
    }

    #[OA\Delete(
        path: '/api/v1/users/{id}',
        summary: 'Delete user (soft delete)',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer'),
        example: 1
    )]
    #[OA\Response(
        response: 200,
        description: 'User deleted successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'success'),
                new OA\Property(property: 'message', type: 'string', example: 'Usuario eliminado correctamente'),
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'User not found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Usuario no encontrado'),
            ]
        )
    )]
    public function delete($id = null): ResponseInterface
    {
        return $this->handleRequest('destroy', ['id' => $id]);
    }
}
