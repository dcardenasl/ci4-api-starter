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
     * Display a specific user.
     * Regular users can only see their own profile.
     */
    public function show($id = null): ResponseInterface
    {
        $currentUserId = $this->getUserId();
        $currentUserRole = $this->getUserRole();

        // If not admin and trying to view another user
        if ($currentUserRole !== 'admin' && $currentUserRole !== 'superadmin' && (int)$id !== $currentUserId) {
            return $this->failForbidden(lang('Auth.insufficientPermissions'));
        }

        return $this->handleRequest('show', ['id' => $id]);
    }

    public function approve($id = null): ResponseInterface
    {
        return $this->handleRequest('approve', ['id' => $id]);
    }
}
