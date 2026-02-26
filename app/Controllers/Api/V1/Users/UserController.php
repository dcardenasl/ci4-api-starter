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
        $currentUserId = $this->getUserId();
        $currentUserRole = $this->getUserRole();

        // Security: Non-admins can only see their own profile
        if ($currentUserRole !== 'admin' && $currentUserRole !== 'superadmin' && $id !== $currentUserId) {
            return $this->respondForbidden(lang('Auth.insufficientPermissions'));
        }

        return $this->handleRequest(fn () => $this->getService()->show($id));
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
            fn ($dto) => $this->getService()->update($id, $dto),
            UserUpdateRequestDTO::class
        );
    }

    /**
     * Approve a pending user
     */
    public function approve(int $id): ResponseInterface
    {
        return $this->handleRequest(function () use ($id) {
            /** @var \App\HTTP\ApiRequest $request */
            $request = $this->request;
            $clientBaseUrl = $request->getVar('client_base_url');

            return $this->getService()->approve(
                $id,
                $this->getUserId(),
                is_string($clientBaseUrl) ? $clientBaseUrl : null
            );
        });
    }

    /**
     * Delete a user
     */
    public function delete(int $id): ResponseInterface
    {
        return $this->handleRequest(fn () => $this->getService()->destroy($id));
    }

}
