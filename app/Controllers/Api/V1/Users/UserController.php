<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Users;

use App\Controllers\ApiController;
use App\DTO\Request\Users\UserIndexRequestDTO;
use App\DTO\Request\Users\UserStoreRequestDTO;
use App\DTO\Request\Users\UserUpdateRequestDTO;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Modernized User Controller
 *
 * Handles administrative and profile-related user operations with strict DTOs.
 */
class UserController extends ApiController
{
    protected string $serviceName = 'userService';

    /**
     * List users with filters and pagination
     */
    public function index(): ResponseInterface
    {
        return $this->handleRequest('index', UserIndexRequestDTO::class);
    }

    /**
     * Display a specific user
     */
    public function show(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->getService()->show($id, $context));
    }

    /**
     * Create a new user (Admin only)
     */
    public function create(): ResponseInterface
    {
        return $this->handleRequest('store', UserStoreRequestDTO::class);
    }

    /**
     * Update an existing user
     */
    public function update(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->getService()->update($id, $dto, $context),
            UserUpdateRequestDTO::class
        );
    }

    /**
     * Approve a pending user
     */
    public function approve(int $id): ResponseInterface
    {
        return $this->handleRequest(
            function ($dto, $context) use ($id) {
                $clientBaseUrl = $this->request->getVar('client_base_url');
                return $this->getService()->approve(
                    $id,
                    $context,
                    is_string($clientBaseUrl) ? $clientBaseUrl : null
                );
            }
        );
    }

    /**
     * Delete a user
     */
    public function delete(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->getService()->destroy($id, $context));
    }

}
