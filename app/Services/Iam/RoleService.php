<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\DTO\Request\Iam\AttachPermissionsRequestDTO;
use App\DTO\Response\Iam\PermissionResponseDTO;
use App\DTO\SecurityContext;
use App\Exceptions\NotFoundException;
use App\Interfaces\Core\RepositoryInterface;
use App\Interfaces\Iam\RoleServiceInterface;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Services\Core\BaseCrudService;
use Config\Database;

class RoleService extends BaseCrudService implements RoleServiceInterface
{
    public function __construct(
        RepositoryInterface $roleRepository,
        ResponseMapperInterface $responseMapper
    ) {
        parent::__construct($roleRepository, $responseMapper);
    }

    /**
     * List permissions attached to a role.
     *
     * @return PermissionResponseDTO[]
     */
    public function listPermissions(int $roleId, ?SecurityContext $context = null): array
    {
        $this->ensureRoleExists($roleId);

        $db = Database::connect();
        $query = $db->table('role_permissions rp')
            ->select('p.id, p.application_id, p.code, p.resource, p.action, p.description, p.created_at, p.updated_at')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('rp.role_id', $roleId)
            ->orderBy('p.code', 'ASC')
            ->get();

        $rows = $query === false ? [] : $query->getResultArray();

        return array_map(static fn (array $row) => self::permissionFromRow($row), $rows);
    }

    /**
     * Attach a list of permissions to a role. Idempotent — already-attached
     * permissions are silently ignored.
     *
     * @return PermissionResponseDTO[] full list of attached permissions after the operation
     */
    public function attachPermissions(int $roleId, AttachPermissionsRequestDTO $request, ?SecurityContext $context = null): array
    {
        return $this->wrapInTransaction(function () use ($roleId, $request) {
            $this->ensureRoleExists($roleId);

            $db = Database::connect();
            $existingQuery = $db->table('role_permissions')
                ->where('role_id', $roleId)
                ->select('permission_id')->get();
            $existing = $existingQuery === false ? [] : $existingQuery->getResultArray();
            $existingIds = array_map(static fn (array $r) => (int) $r['permission_id'], $existing);

            $toInsert = array_diff($request->permission_ids, $existingIds);

            if ($toInsert !== []) {
                $validQuery = $db->table('permissions')
                    ->whereIn('id', $toInsert)
                    ->select('id')->get();
                $validRows = $validQuery === false ? [] : $validQuery->getResultArray();
                $validIds = array_map(static fn (array $r) => (int) $r['id'], $validRows);

                if (count($validIds) !== count($toInsert)) {
                    throw new NotFoundException(lang('Api.resourceNotFound'));
                }

                $rows = array_map(
                    static fn (int $pid) => ['role_id' => $roleId, 'permission_id' => $pid],
                    $validIds
                );
                $db->table('role_permissions')->insertBatch($rows);
            }

            return $this->listPermissions($roleId);
        });
    }

    /**
     * Remove a single permission from a role.
     */
    public function detachPermission(int $roleId, int $permissionId, ?SecurityContext $context = null): bool
    {
        return $this->wrapInTransaction(function () use ($roleId, $permissionId) {
            $this->ensureRoleExists($roleId);

            $db = Database::connect();
            $db->table('role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->delete();

            return true;
        });
    }

    private function ensureRoleExists(int $roleId): void
    {
        $role = $this->repository->find($roleId);
        if ($role === null) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function permissionFromRow(array $row): PermissionResponseDTO
    {
        return new PermissionResponseDTO(
            id: (int) $row['id'],
            application_id: (int) $row['application_id'],
            code: (string) $row['code'],
            resource: (string) $row['resource'],
            action: (string) $row['action'],
            description: (string) ($row['description'] ?? ''),
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }
}
