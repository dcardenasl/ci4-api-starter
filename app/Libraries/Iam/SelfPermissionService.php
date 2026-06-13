<?php

declare(strict_types=1);

namespace App\Libraries\Iam;

use App\Models\ApplicationModel;
use App\Models\PermissionModel;
use Config\Database;
use Config\Services;

/**
 * Allows a domain app (authenticated via X-App-Key) to register its own
 * permissions in the hub without requiring a superadmin JWT.
 *
 * Security: each permission code MUST start with "{app.code}." — a domain app
 * registered as "catalog" can only register "catalog.*" codes. Codes that fail
 * this namespace check are rejected and counted separately.
 */
class SelfPermissionService
{
    public function __construct(
        private readonly PermissionModel $permissionModel,
        private readonly ApplicationModel $applicationModel,
    ) {
    }

    /**
     * @param list<array<string, string>> $permissions
     */
    public function sync(int $appId, array $permissions): SelfPermissionResult
    {
        $application = $this->applicationModel->find($appId);
        if ($application === null) {
            return new SelfPermissionResult(0, 0, count($permissions), ['Application not found']);
        }

        /** @var \App\Entities\ApplicationEntity $application */
        $namespace = $application->code . '.';
        $created   = 0;
        $existing  = 0;
        $rejected  = 0;
        $errors    = [];
        $permissionIds = [];

        foreach ($permissions as $perm) {
            $code = trim((string) ($perm['code'] ?? ''));

            if ($code === '' || !str_starts_with($code, $namespace)) {
                $rejected++;
                $errors[] = "Rejected '{$code}': must start with '{$namespace}'";
                continue;
            }

            $existingRow = $this->permissionModel
                ->where('application_id', $appId)
                ->where('code', $code)
                ->first();

            if ($existingRow !== null) {
                $existing++;
                /** @var \App\Entities\PermissionEntity $existingRow */
                $permissionIds[] = (int) $existingRow->id;
                continue;
            }

            $inserted = $this->permissionModel->insert([
                'application_id' => $appId,
                'code'           => $code,
                'resource'       => (string) ($perm['resource'] ?? ''),
                'action'         => (string) ($perm['action'] ?? ''),
                'description'    => (string) ($perm['description'] ?? ''),
            ]);

            if ($inserted === false || $inserted === 0) {
                $rejected++;
                $errors[] = "Failed to insert '{$code}': " . implode(', ', $this->permissionModel->errors());
            } else {
                $created++;
                $permissionIds[] = (int) $inserted;
            }
        }

        $this->attachToSuperadmin($permissionIds);

        return new SelfPermissionResult($created, $existing, $rejected, $errors);
    }

    /**
     * @param list<int> $permissionIds
     */
    private function attachToSuperadmin(array $permissionIds): void
    {
        $permissionIds = array_values(array_unique(array_filter($permissionIds)));
        if ($permissionIds === []) {
            return;
        }

        $db = Database::connect();
        $roleResult = $db->table('roles')->where('code', 'superadmin')->get();
        if (!($roleResult instanceof \CodeIgniter\Database\ResultInterface)) {
            return;
        }

        $role = $roleResult->getRowArray();
        if ($role === null) {
            return;
        }

        $roleId = (int) $role['id'];
        $existingResult = $db->table('role_permissions')
            ->select('permission_id')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', $permissionIds)
            ->get();
        if (!($existingResult instanceof \CodeIgniter\Database\ResultInterface)) {
            return;
        }

        $existing = $existingResult->getResultArray();
        $existingIds = array_map(static fn (array $row): int => (int) $row['permission_id'], $existing);

        $rows = [];
        foreach (array_diff($permissionIds, $existingIds) as $permissionId) {
            $rows[] = ['role_id' => $roleId, 'permission_id' => (int) $permissionId];
        }

        if ($rows !== []) {
            $db->table('role_permissions')->insertBatch($rows);
            Services::effectivePermissionsResolver(false)->invalidateAll();
        }
    }
}
