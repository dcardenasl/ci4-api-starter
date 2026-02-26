<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Users;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * User Controller - CRUD operations
 */
class UserController extends ApiController
{
    protected string $serviceName = 'userService';

    /**
     * List users with filters and pagination
     */
    public function index(): ResponseInterface
    {
        $dto = $this->getDTO(\App\DTO\Request\Users\UserIndexRequestDTO::class);

        return $this->handleRequest(
            fn () => $this->getService()->index($dto)
        );
    }

    /**
     * Display a specific user.
     * Regular users can only see their own profile.
     */
    public function show(string|int|null $id = null): ResponseInterface
    {
        $currentUserId = $this->getUserId();
        $currentUserRole = $this->getUserRole();

        // If not admin and trying to view another user
        if ($currentUserRole !== 'admin' && $currentUserRole !== 'superadmin' && (int)$id !== $currentUserId) {
            return $this->respondForbidden(lang('Auth.insufficientPermissions'));
        }

        return $this->handleRequest(
            fn () => $this->getService()->show(['id' => $id])
        );
    }

    public function create(): ResponseInterface
    {
        return $this->handleRequest(
            fn () => $this->getService()->store($this->collectRequestData())
        );
    }

    public function update(string|int|null $id = null): ResponseInterface
    {
        return $this->handleRequest(
            fn () => $this->getService()->update(['id' => $id] + $this->collectRequestData())
        );
    }

    public function approve(string|int|null $id = null): ResponseInterface
    {
        return $this->handleRequest(
            fn () => $this->getService()->approve(['id' => $id])
        );
    }
}
