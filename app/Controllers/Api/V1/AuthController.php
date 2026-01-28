<?php

namespace App\Controllers\Api\V1;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use App\Services\UserService;
use App\Services\JwtService;
use App\Models\UserModel;

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

    /**
     * User login endpoint
     * POST /api/v1/auth/login
     */
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

    /**
     * User registration endpoint
     * POST /api/v1/auth/register
     */
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

    /**
     * Get current authenticated user
     * GET /api/v1/auth/me
     */
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
