<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Users;

use App\Controllers\ApiController;
use App\DTO\Request\Users\UserCreateRequestDTO;
use App\DTO\Request\Users\UserIndexRequestDTO;
use App\DTO\Request\Users\UserUpdateRequestDTO;
use App\Interfaces\Users\UserServiceInterface;
use App\Libraries\ApiResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Modernized User Controller
 *
 * Handles administrative and profile-related user operations with strict DTOs.
 */
class UserController extends ApiController
{
    protected UserServiceInterface $userService;

    protected function resolveDefaultService(): object
    {
        $this->userService = Services::userService();

        return $this->userService;
    }

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
        return $this->handleRequest(fn ($dto, $context) => $this->userService->show($id, $context));
    }

    /**
     * Create a new user (Admin only)
     */
    public function create(): ResponseInterface
    {
        return $this->handleRequest('store', UserCreateRequestDTO::class);
    }

    /**
     * Update an existing user
     */
    public function update(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->userService->update($id, $dto, $context),
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
                return $this->userService->approve(
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
        return $this->handleRequest(fn ($dto, $context) => $this->userService->destroy($id, $context));
    }

    /**
     * Lists the global roles the current actor is allowed to assign to other
     * users. Anti-escalation: a role appears only if all its permissions are
     * a subset of the actor's effective permissions in the current application.
     */
    public function assignableRoles(): ResponseInterface
    {
        return $this->handleRequest(function ($dto, $context) {
            $db = \Config\Database::connect();
            $actorPerms = $context !== null ? $context->permissions : [];

            $rolesResult = $db->table('roles r')
                ->select('r.id, r.code, r.name, r.description, r.is_system, r.is_self_assignable')
                ->orderBy('r.name', 'ASC')
                ->get();
            $roles = $rolesResult !== false ? $rolesResult->getResultArray() : [];

            $rolePerms  = [];
            $rpcResult  = $db->table('role_permissions rp')
                ->select('rp.role_id, p.code')
                ->join('permissions p', 'p.id = rp.permission_id')
                ->get();
            $rows = $rpcResult !== false ? $rpcResult->getResultArray() : [];
            foreach ($rows as $row) {
                $rolePerms[(int) $row['role_id']][] = (string) $row['code'];
            }

            $assignable = [];
            foreach ($roles as $role) {
                $codes = $rolePerms[(int) $role['id']] ?? [];
                if (array_diff($codes, $actorPerms) === []) {
                    $assignable[] = [
                        'id'                 => (int) $role['id'],
                        'code'               => (string) $role['code'],
                        'name'               => (string) $role['name'],
                        'description'        => $role['description'] !== null ? (string) $role['description'] : null,
                        'is_system'          => (int) $role['is_system'],
                        'is_self_assignable' => (int) $role['is_self_assignable'],
                    ];
                }
            }

            return $this->response->setJSON(ApiResponse::success($assignable));
        });
    }

}
