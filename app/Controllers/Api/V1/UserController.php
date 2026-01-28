<?php

namespace App\Controllers\Api\V1;

use App\Controllers\ApiController;
use App\Services\UserService;
use CodeIgniter\HTTP\ResponseInterface;

class UserController extends ApiController
{
    protected UserService $userService;

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

    /**
     * Get all users
     * GET /api/v1/users
     */
    public function index(): ResponseInterface
    {
        return $this->handleRequest('index');
    }

    /**
     * Get user by ID
     * GET /api/v1/users/{id}
     */
    public function show($id = null): ResponseInterface
    {
        return $this->handleRequest('show', ['id' => $id]);
    }

    /**
     * Create new user
     * POST /api/v1/users
     */
    public function create(): ResponseInterface
    {
        return $this->handleRequest('store');
    }

    /**
     * Update user
     * PUT /api/v1/users/{id}
     */
    public function update($id = null): ResponseInterface
    {
        return $this->handleRequest('update', ['id' => $id]);
    }

    /**
     * Delete user
     * DELETE /api/v1/users/{id}
     */
    public function delete($id = null): ResponseInterface
    {
        return $this->handleRequest('destroy', ['id' => $id]);
    }
}
