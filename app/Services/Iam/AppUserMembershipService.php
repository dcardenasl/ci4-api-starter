<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\DTO\Request\Iam\AttachRolesRequestDTO;
use App\DTO\Response\Iam\RoleResponseDTO;
use App\DTO\SecurityContext;
use App\Exceptions\NotFoundException;
use App\Interfaces\Core\RepositoryInterface;
use App\Interfaces\Iam\AppUserMembershipServiceInterface;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Libraries\ContextHolder;
use App\Services\Core\BaseCrudService;
use App\Services\Core\Support\RelationLabelLoader;
use Config\Database;

class AppUserMembershipService extends BaseCrudService implements AppUserMembershipServiceInterface
{
    public function __construct(
        RepositoryInterface $appUserMembershipRepository,
        ResponseMapperInterface $responseMapper,
        private readonly EffectivePermissionsResolver $permissionsResolver,
        private readonly IamAuthorizationService $authz,
        private readonly RelationLabelLoader $labels = new RelationLabelLoader()
    ) {
        parent::__construct($appUserMembershipRepository, $responseMapper);
    }

    protected function enrichEntities(array $entities): array
    {
        $entities = $this->labels->attachLabel(
            $entities,
            sourceField: 'application_id',
            targetField: 'application_name',
            relatedTable: 'applications',
            relatedLabel: 'name'
        );

        return $this->labels->attachUserLabels($entities, 'user_id');
    }

    protected function beforeUpdate(int $id, array $data, ?SecurityContext $context): array
    {
        $membership = $this->ensureMembership($id);
        $this->authz->assertCanModifySubject($context, (int) $membership['user_id'], (int) $membership['application_id']);

        return parent::beforeUpdate($id, $data, $context);
    }

    protected function beforeDelete(int $id, ?SecurityContext $context): void
    {
        $membership = $this->ensureMembership($id);
        $this->authz->assertCanModifySubject($context, (int) $membership['user_id'], (int) $membership['application_id']);

        parent::beforeDelete($id, $context);
    }

    /**
     * Hide memberships belonging to SuperAdmin users from non-SA actors.
     */
    protected function applyBaseCriteria(object $builder): void
    {
        $context = ContextHolder::get();
        if ($context === null || $this->authz->isSuperAdmin($context)) {
            return;
        }

        $sub = '(SELECT m2.user_id FROM app_user_memberships m2'
            . ' INNER JOIN membership_roles mr2 ON mr2.membership_id = m2.id'
            . ' INNER JOIN role_permissions rp2 ON rp2.role_id = mr2.role_id'
            . ' INNER JOIN permissions p2 ON p2.id = rp2.permission_id'
            . " WHERE p2.code = 'iam.superadmin-access')";

        if (method_exists($builder, 'where')) {
            $builder->where("app_user_memberships.user_id NOT IN {$sub}", null, false);
        }
    }

    protected function beforeStore(array $data, ?SecurityContext $context): array
    {
        if (isset($data['user_id'])) {
            $applicationId = isset($data['application_id']) ? (int) $data['application_id'] : IamAuthorizationService::DEFAULT_APPLICATION_ID;
            $this->authz->assertCanModifySubject($context, (int) $data['user_id'], $applicationId);
        }

        return parent::beforeStore($data, $context);
    }

    /**
     * @return RoleResponseDTO[]
     */
    public function listRoles(int $membershipId, ?SecurityContext $context = null): array
    {
        $membership = $this->ensureMembership($membershipId);

        $db = Database::connect();
        $query = $db->table('membership_roles mr')
            ->select('r.id, r.application_id, a.name AS application_name, r.code, r.name, r.description, r.is_system, r.created_at, r.updated_at')
            ->join('roles r', 'r.id = mr.role_id')
            ->join('applications a', 'a.id = r.application_id', 'left')
            ->where('mr.membership_id', $membershipId)
            ->orderBy('r.code', 'ASC')
            ->get();

        $rows = $query === false ? [] : $query->getResultArray();

        return array_map(static fn (array $row) => self::roleFromRow($row), $rows);
    }

    /**
     * @return RoleResponseDTO[] full list of attached roles after the operation
     */
    public function attachRoles(int $membershipId, AttachRolesRequestDTO $request, ?SecurityContext $context = null): array
    {
        return $this->wrapInTransaction(function () use ($membershipId, $request, $context) {
            $membership    = $this->ensureMembership($membershipId);
            $applicationId = (int) $membership['application_id'];

            $this->authz->assertCanModifySubject($context, (int) $membership['user_id'], $applicationId);
            $this->authz->assertCanGrantRoles($context, $request->role_ids, $applicationId);

            $db = Database::connect();
            $existingQuery = $db->table('membership_roles')
                ->where('membership_id', $membershipId)
                ->select('role_id')->get();
            $existing = $existingQuery === false ? [] : $existingQuery->getResultArray();
            $existingIds = array_map(static fn (array $r) => (int) $r['role_id'], $existing);

            $toInsert = array_diff($request->role_ids, $existingIds);

            if ($toInsert !== []) {
                $validQuery = $db->table('roles')
                    ->whereIn('id', $toInsert)
                    ->select('id')->get();
                $validRows = $validQuery === false ? [] : $validQuery->getResultArray();
                $validIds = array_map(static fn (array $r) => (int) $r['id'], $validRows);

                if (count($validIds) !== count($toInsert)) {
                    throw new NotFoundException(lang('Api.resourceNotFound'));
                }

                $rows = array_map(
                    static fn (int $rid) => ['membership_id' => $membershipId, 'role_id' => $rid],
                    $validIds
                );
                $db->table('membership_roles')->insertBatch($rows);
            }

            $this->permissionsResolver->invalidateForUser((int) $membership['user_id'], (int) $membership['application_id']);

            return $this->listRoles($membershipId);
        });
    }

    public function detachRole(int $membershipId, int $roleId, ?SecurityContext $context = null): bool
    {
        return $this->wrapInTransaction(function () use ($membershipId, $roleId, $context) {
            $membership    = $this->ensureMembership($membershipId);
            $applicationId = (int) $membership['application_id'];

            $this->authz->assertCanModifySubject($context, (int) $membership['user_id'], $applicationId);
            $this->authz->assertCanGrantRoles($context, [$roleId], $applicationId);

            $db = Database::connect();
            $db->table('membership_roles')
                ->where('membership_id', $membershipId)
                ->where('role_id', $roleId)
                ->delete();

            $this->permissionsResolver->invalidateForUser((int) $membership['user_id'], (int) $membership['application_id']);

            return true;
        });
    }

    /**
     * Effective permissions for the user in the given application.
     *
     * @return list<string>
     */
    public function listEffectivePermissions(int $userId, int $applicationId, ?SecurityContext $context = null): array
    {
        return $this->permissionsResolver->resolve($userId, $applicationId);
    }

    /**
     * @return \App\Interfaces\DataTransferObjectInterface[] memberships for a user (one per application)
     */
    public function listForUser(int $userId, ?SecurityContext $context = null): array
    {
        $db = Database::connect();
        $query = $db->table('app_user_memberships')
            ->where('user_id', $userId)
            ->orderBy('application_id', 'ASC')
            ->get();

        $rows     = $query === false ? [] : $query->getResultArray();
        $entities = [];
        foreach ($rows as $row) {
            $entity = $this->repository->find((int) $row['id']);
            if ($entity !== null) {
                $entities[] = $entity;
            }
        }

        $entities = $this->enrichEntities($entities);

        return array_map(fn ($e) => $this->mapToResponse($e), $entities);
    }

    /**
     * @return array<string, mixed>
     */
    private function ensureMembership(int $membershipId): array
    {
        $db = Database::connect();
        $row = $db->table('app_user_memberships')->where('id', $membershipId)->get();
        $data = $row === false ? null : $row->getRowArray();
        if ($data === null) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function roleFromRow(array $row): RoleResponseDTO
    {
        return new RoleResponseDTO(
            id: (int) $row['id'],
            application_id: $row['application_id'] !== null ? (int) $row['application_id'] : null,
            code: (string) $row['code'],
            name: (string) $row['name'],
            description: $row['description'] !== null ? (string) $row['description'] : null,
            is_system: (bool) $row['is_system'],
            application_name: isset($row['application_name']) ? (string) $row['application_name'] : null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }
}
