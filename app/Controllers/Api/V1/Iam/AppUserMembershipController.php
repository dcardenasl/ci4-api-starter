<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Iam;

use App\Controllers\ApiController;
use App\DTO\Request\Iam\AppUserMembershipCreateRequestDTO;
use App\DTO\Request\Iam\AppUserMembershipIndexRequestDTO;
use App\DTO\Request\Iam\AppUserMembershipUpdateRequestDTO;
use App\DTO\Request\Iam\AttachRolesRequestDTO;
use App\Interfaces\Iam\AppUserMembershipServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class AppUserMembershipController extends ApiController
{
    protected AppUserMembershipServiceInterface $appUserMembershipService;

    protected function resolveDefaultService(): object
    {
        $this->appUserMembershipService = Services::appUserMembershipService();

        return $this->appUserMembershipService;
    }

    protected array $statusCodes = [
        'store' => 201,
    ];

    public function index(): ResponseInterface
    {
        return $this->handleRequest('index', AppUserMembershipIndexRequestDTO::class);
    }

    public function create(): ResponseInterface
    {
        return $this->handleRequest('store', AppUserMembershipCreateRequestDTO::class);
    }

    public function update(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->appUserMembershipService->update($id, $dto, $context),
            AppUserMembershipUpdateRequestDTO::class
        );
    }

    public function show(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->appUserMembershipService->show($id, $context));
    }

    public function delete(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->appUserMembershipService->destroy($id, $context));
    }

    public function listRoles(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->appUserMembershipService->listRoles($id, $context));
    }

    public function attachRoles(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->appUserMembershipService->attachRoles($id, $dto, $context),
            AttachRolesRequestDTO::class
        );
    }

    public function detachRole(int $id, int $roleId): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->appUserMembershipService->detachRole($id, $roleId, $context));
    }

    public function listForUser(int $userId): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->appUserMembershipService->listForUser($userId, $context));
    }

    public function listEffectivePermissionsForUser(int $userId): ResponseInterface
    {
        return $this->handleRequest(function ($dto, $context) use ($userId) {
            $appIdRaw      = $this->request->getGet('application_id');
            $applicationId = is_numeric($appIdRaw) ? (int) $appIdRaw : 1;

            return [
                'data' => [
                    'user_id'         => $userId,
                    'application_id'  => $applicationId,
                    'permissions'     => $this->appUserMembershipService->listEffectivePermissions($userId, $applicationId, $context),
                ],
            ];
        });
    }
}
