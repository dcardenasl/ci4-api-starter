<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\DomainPermissions;
use Config\Services;

class SyncOwnPermissions extends BaseCommand
{
    protected $group       = 'IAM';
    protected $name        = 'iam:sync-permissions';
    protected $description = 'Sync permissions declared by the hub in Config\DomainPermissions and attach them to superadmin.';
    protected $usage       = 'iam:sync-permissions';

    public function run(array $params): int
    {
        $db = \Config\Database::connect();
        $app = $db->table('applications')->where('code', 'self')->get()->getRowArray();
        if ($app === null) {
            CLI::error('Application "self" not found. Run db:seed RbacBootstrapSeeder first.');

            return EXIT_ERROR;
        }

        $created = 0;
        $existing = 0;
        $permissionIds = [];
        $now = date('Y-m-d H:i:s');

        foreach (DomainPermissions::PERMISSIONS as $permission) {
            $code = trim((string) ($permission['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $row = $db->table('permissions')
                ->where('application_id', (int) $app['id'])
                ->where('code', $code)
                ->get()
                ->getRowArray();

            if ($row !== null) {
                $existing++;
                $permissionIds[] = (int) $row['id'];
                continue;
            }

            $db->table('permissions')->insert([
                'application_id' => (int) $app['id'],
                'code'           => $code,
                'resource'       => (string) ($permission['resource'] ?? ''),
                'action'         => (string) ($permission['action'] ?? ''),
                'description'    => (string) ($permission['description'] ?? ''),
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            $created++;
            $permissionIds[] = (int) $db->insertID();
        }

        $this->attachToSuperadmin($permissionIds);

        CLI::write(sprintf(
            'Permissions synced: created=%d existing=%d rejected=0',
            $created,
            $existing
        ), 'green');

        return EXIT_SUCCESS;
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

        $db = \Config\Database::connect();
        $role = $db->table('roles')->where('code', 'superadmin')->get()->getRowArray();
        if ($role === null) {
            return;
        }

        $roleId = (int) $role['id'];
        $existing = $db->table('role_permissions')
            ->select('permission_id')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', $permissionIds)
            ->get()
            ->getResultArray();
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
