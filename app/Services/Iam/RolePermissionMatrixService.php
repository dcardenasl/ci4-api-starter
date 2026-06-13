<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\DTO\Response\Iam\RolePermissionMatrixResponseDTO;
use CodeIgniter\Database\ConnectionInterface;

class RolePermissionMatrixService
{
    /**
     * @param ConnectionInterface<object, object> $db
     */
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function matrix(): RolePermissionMatrixResponseDTO
    {
        $appResult = $this->db->table('applications')
            ->select('id, code, name')
            ->orderBy('code', 'ASC')
            ->get();
        $applications = ($appResult instanceof \CodeIgniter\Database\ResultInterface)
            ? $appResult->getResultArray()
            : [];

        $permResult = $this->db->table('permissions')
            ->select('id, application_id, code, resource, action, description')
            ->orderBy('application_id', 'ASC')
            ->orderBy('resource', 'ASC')
            ->orderBy('action', 'ASC')
            ->get();
        $permissions = ($permResult instanceof \CodeIgniter\Database\ResultInterface)
            ? $permResult->getResultArray()
            : [];

        $byApp = [];
        foreach ($applications as $application) {
            $id = (int) $application['id'];
            $byApp[$id] = [
                'id'          => $id,
                'code'        => (string) $application['code'],
                'name'        => (string) $application['name'],
                'permissions' => [],
            ];
        }

        foreach ($permissions as $permission) {
            $appId = (int) $permission['application_id'];
            if (! isset($byApp[$appId])) {
                continue;
            }

            $byApp[$appId]['permissions'][] = [
                'id'          => (int) $permission['id'],
                'code'        => (string) $permission['code'],
                'resource'    => (string) $permission['resource'],
                'action'      => (string) $permission['action'],
                'description' => (string) ($permission['description'] ?? ''),
            ];
        }

        $rolesResult = $this->db->table('roles')
            ->select('id, code, name, description, is_system')
            ->orderBy('code', 'ASC')
            ->get();
        $rolesData = ($rolesResult instanceof \CodeIgniter\Database\ResultInterface)
            ? $rolesResult->getResultArray()
            : [];

        $roles = array_map(
            static fn (array $role): array => [
                'id'          => (int) $role['id'],
                'code'        => (string) $role['code'],
                'name'        => (string) $role['name'],
                'description' => (string) ($role['description'] ?? ''),
                'is_system'   => (int) ($role['is_system'] ?? 0),
            ],
            $rolesData
        );

        $assignments = [];
        $assignResult = $this->db->table('role_permissions')->select('role_id, permission_id')->get();
        $assignData = ($assignResult instanceof \CodeIgniter\Database\ResultInterface)
            ? $assignResult->getResultArray()
            : [];

        foreach ($assignData as $row) {
            $roleId = (int) $row['role_id'];
            $assignments[$roleId] ??= [];
            $assignments[$roleId][] = (int) $row['permission_id'];
        }

        foreach ($assignments as $roleId => $ids) {
            $assignments[$roleId] = array_values(array_unique($ids));
            sort($assignments[$roleId]);
        }

        return RolePermissionMatrixResponseDTO::fromArray([
            'applications' => array_values($byApp),
            'roles'       => array_values($roles),
            'assignments' => $assignments,
        ]);
    }
}
