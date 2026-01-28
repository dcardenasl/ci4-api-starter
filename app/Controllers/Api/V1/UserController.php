<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\UserService;
use App\Repositories\UserRepository;
use CodeIgniter\HTTP\ResponseInterface;

class UserController extends BaseController
{
    protected $userService;

    public function __construct()
    {
        // Manual dependency injection for now
        $db = \Config\Database::connect();
        $userRepository = new UserRepository($db);
        $this->userService = new UserService($userRepository);
    }

    /**
     * Get all users
     * GET /api/v1/users
     */
    public function index(): ResponseInterface
    {
        try {
            $users = $this->userService->getAllUsers();

            return $this->response->setJSON([
                'status' => 'success',
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get user by ID
     * GET /api/v1/users/{id}
     */
    public function show($id = null): ResponseInterface
    {
        try {
            $user = $this->userService->getUserById($id);

            if (!$user) {
                return $this->response->setStatusCode(404)->setJSON([
                    'status' => 'error',
                    'message' => 'User not found',
                ]);
            }

            return $this->response->setJSON([
                'status' => 'success',
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create new user
     * POST /api/v1/users
     */
    public function create(): ResponseInterface
    {
        try {
            $data = $this->request->getJSON(true);

            $user = $this->userService->createUser($data);

            if (!$user) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status' => 'error',
                    'message' => 'Failed to create user',
                ]);
            }

            return $this->response->setStatusCode(201)->setJSON([
                'status' => 'success',
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update user
     * PUT /api/v1/users/{id}
     */
    public function update($id = null): ResponseInterface
    {
        try {
            $data = $this->request->getJSON(true);

            $user = $this->userService->updateUser($id, $data);

            if (!$user) {
                return $this->response->setStatusCode(404)->setJSON([
                    'status' => 'error',
                    'message' => 'User not found or failed to update',
                ]);
            }

            return $this->response->setJSON([
                'status' => 'success',
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete user
     * DELETE /api/v1/users/{id}
     */
    public function delete($id = null): ResponseInterface
    {
        try {
            $result = $this->userService->deleteUser($id);

            if (!$result) {
                return $this->response->setStatusCode(404)->setJSON([
                    'status' => 'error',
                    'message' => 'User not found',
                ]);
            }

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'User deleted successfully',
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
