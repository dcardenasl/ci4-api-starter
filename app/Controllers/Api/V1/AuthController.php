<?php

namespace App\Controllers\Api\V1;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use App\Services\UserService;
use App\Services\JwtService;
use App\Models\UserModel;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    use ResponseTrait;
    protected UserService $userService;
    protected JwtService $jwtService;

    public function __construct()
    {
        $this->userService = new UserService(new UserModel());
        $this->jwtService = new JwtService();
    }

    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'User login',
        tags: ['Authentication'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['username', 'password'],
            properties: [
                new OA\Property(property: 'username', type: 'string', example: 'testuser', description: 'Username or email'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'testpass123'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Login successful',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Login successful'),
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGc...'),
                        new OA\Property(
                            property: 'user',
                            properties: [
                                new OA\Property(property: 'id', type: 'string', example: '1'),
                                new OA\Property(property: 'username', type: 'string', example: 'testuser'),
                                new OA\Property(property: 'email', type: 'string', example: 'test@example.com'),
                                new OA\Property(property: 'role', type: 'string', example: 'user'),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Invalid credentials',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
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
    public function login()
    {
        $data = $this->request->getJSON(true) ?? [];

        $result = $this->userService->login($data);

        if (isset($result['errors'])) {
            return $this->respond([
                'success' => false,
                'errors' => $result['errors'],
            ], ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $user = $result['data'];
        $token = $this->jwtService->encode($user['id'], $user['role']);

        return $this->respond([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => $user,
            ],
        ], ResponseInterface::HTTP_OK);
    }

    #[OA\Post(
        path: '/api/v1/auth/register',
        summary: 'Register new user',
        tags: ['Authentication'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['username', 'email', 'password'],
            properties: [
                new OA\Property(property: 'username', type: 'string', example: 'newuser'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'newuser@example.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                new OA\Property(property: 'role', type: 'string', example: 'user', description: 'Optional, defaults to "user"'),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'User registered successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'User registered successfully'),
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGc...'),
                        new OA\Property(
                            property: 'user',
                            properties: [
                                new OA\Property(property: 'id', type: 'string', example: '1'),
                                new OA\Property(property: 'username', type: 'string', example: 'newuser'),
                                new OA\Property(property: 'email', type: 'string', example: 'newuser@example.com'),
                                new OA\Property(property: 'role', type: 'string', example: 'user'),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    example: ['email' => 'This email is already registered']
                ),
            ]
        )
    )]
    public function register()
    {
        $data = $this->request->getJSON(true) ?? [];

        $result = $this->userService->register($data);

        if (isset($result['errors'])) {
            return $this->respond([
                'success' => false,
                'errors' => $result['errors'],
            ], ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $result['data'];
        $token = $this->jwtService->encode($user['id'], $user['role']);

        return $this->respond([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'token' => $token,
                'user' => $user,
            ],
        ], ResponseInterface::HTTP_CREATED);
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
                new OA\Property(property: 'success', type: 'boolean', example: true),
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
        response: 401,
        description: 'Unauthorized',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'User not authenticated'),
            ]
        )
    )]
    public function me()
    {
        $userId = $this->request->userId ?? null;

        if (!$userId) {
            return $this->respond([
                'success' => false,
                'message' => 'User not authenticated',
            ], ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $result = $this->userService->show(['id' => $userId]);

        if (isset($result['errors'])) {
            return $this->respond([
                'success' => false,
                'errors' => $result['errors'],
            ], ResponseInterface::HTTP_NOT_FOUND);
        }

        return $this->respond([
            'success' => true,
            'data' => $result['data'],
        ], ResponseInterface::HTTP_OK);
    }
}
