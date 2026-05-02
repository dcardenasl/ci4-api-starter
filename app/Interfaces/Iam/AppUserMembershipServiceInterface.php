<?php

declare(strict_types=1);

namespace App\Interfaces\Iam;

use App\DTO\Request\Iam\AttachRolesRequestDTO;
use App\DTO\Response\Iam\RoleResponseDTO;
use App\DTO\SecurityContext;
use App\Interfaces\Core\CrudServiceContract;

interface AppUserMembershipServiceInterface extends CrudServiceContract
{
    /** @return RoleResponseDTO[] */
    public function listRoles(int $membershipId, ?SecurityContext $context = null): array;

    /** @return RoleResponseDTO[] */
    public function attachRoles(int $membershipId, AttachRolesRequestDTO $request, ?SecurityContext $context = null): array;

    public function detachRole(int $membershipId, int $roleId, ?SecurityContext $context = null): bool;

    /** @return list<string> */
    public function listEffectivePermissions(int $userId, int $applicationId, ?SecurityContext $context = null): array;

    /** @return \App\Interfaces\DataTransferObjectInterface[] */
    public function listForUser(int $userId, ?SecurityContext $context = null): array;
}
