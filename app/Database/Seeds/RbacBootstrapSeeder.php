<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Bootstraps the IAM tables with the system app, permissions, system roles,
 * role-permission map, and memberships for any existing users.
 *
 * Idempotent: every insert checks for an existing row first. Safe to re-run.
 */
class RbacBootstrapSeeder extends Seeder
{
    private const APP_SELF = 'self';

    /** @var array<int, array{code: string, resource: string, action: string, description: string}> */
    private const PERMISSIONS = [
        ['code' => 'users:read',             'resource' => 'users',    'action' => 'read',              'description' => 'Read user records'],
        ['code' => 'users:write',            'resource' => 'users',    'action' => 'write',             'description' => 'Create, update or delete users'],
        ['code' => 'files:read',             'resource' => 'files',    'action' => 'read',              'description' => 'Read files'],
        ['code' => 'files:write',            'resource' => 'files',    'action' => 'write',             'description' => 'Upload or delete files'],
        ['code' => 'audit:read',             'resource' => 'audit',    'action' => 'read',              'description' => 'Read audit log entries'],
        ['code' => 'metrics:read',           'resource' => 'metrics',  'action' => 'read',              'description' => 'Read metrics dashboards'],
        ['code' => 'apikeys:read',           'resource' => 'apikeys',  'action' => 'read',              'description' => 'Read API keys'],
        ['code' => 'apikeys:write',          'resource' => 'apikeys',  'action' => 'write',             'description' => 'Create, update or revoke API keys'],
        ['code' => 'iam:admin-access',       'resource' => 'iam',      'action' => 'admin-access',      'description' => 'Access the admin panel'],
        ['code' => 'iam:superadmin-access',  'resource' => 'iam',      'action' => 'superadmin-access', 'description' => 'Access superadmin-only operations'],
    ];

    /** @var array<int, array{code: string, name: string, description: string, permissions: array<int, string>|string}> */
    private const ROLES = [
        [
            'code'        => 'superadmin',
            'name'        => 'Super Administrator',
            'description' => 'Full access to all resources and IAM operations.',
            'permissions' => '*',
        ],
        [
            'code'        => 'admin',
            'name'        => 'Administrator',
            'description' => 'Administrative access excluding superadmin-only operations.',
            'permissions' => [
                'users:read', 'users:write',
                'files:read', 'files:write',
                'audit:read',
                'metrics:read',
                'apikeys:read', 'apikeys:write',
                'iam:admin-access',
            ],
        ],
        [
            'code'        => 'user',
            'name'        => 'User',
            'description' => 'Default role for end users.',
            'permissions' => ['files:read', 'files:write'],
        ],
    ];

    public function run()
    {
        $appId = $this->ensureApplication(self::APP_SELF);
        $permissionIds = $this->ensurePermissions($appId);
        $roleIds = $this->ensureRoles($appId, $permissionIds);
        $this->ensureMembershipsForExistingUsers($appId, $roleIds);
    }

    private function ensureApplication(string $name): int
    {
        $existing = $this->db->table('applications')->where('name', $name)->get()->getRowArray();
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('applications')->insert([
            'name'       => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * @return array<string, int> map of permission code → id
     */
    private function ensurePermissions(int $appId): array
    {
        $now = date('Y-m-d H:i:s');
        $existing = $this->db->table('permissions')
            ->where('application_id', $appId)
            ->get()->getResultArray();

        /** @var array<string, int> $map */
        $map = [];
        foreach ($existing as $row) {
            $map[(string) $row['code']] = (int) $row['id'];
        }

        foreach (self::PERMISSIONS as $perm) {
            if (isset($map[$perm['code']])) {
                continue;
            }

            $this->db->table('permissions')->insert([
                'application_id' => $appId,
                'code'           => $perm['code'],
                'resource'       => $perm['resource'],
                'action'         => $perm['action'],
                'description'    => $perm['description'],
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            $map[$perm['code']] = (int) $this->db->insertID();
        }

        return $map;
    }

    /**
     * @param array<string, int> $permissionIds map of permission code → id
     * @return array<string, int> map of role code → id
     */
    private function ensureRoles(int $appId, array $permissionIds): array
    {
        $now = date('Y-m-d H:i:s');
        /** @var array<string, int> $map */
        $map = [];

        foreach (self::ROLES as $roleDef) {
            $existing = $this->db->table('roles')
                ->where('application_id', $appId)
                ->where('code', $roleDef['code'])
                ->get()->getRowArray();

            if ($existing !== null) {
                $roleId = (int) $existing['id'];
            } else {
                $this->db->table('roles')->insert([
                    'application_id' => $appId,
                    'code'           => $roleDef['code'],
                    'name'           => $roleDef['name'],
                    'description'    => $roleDef['description'],
                    'is_system'      => 1,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
                $roleId = (int) $this->db->insertID();
            }

            $map[$roleDef['code']] = $roleId;

            $codes = $roleDef['permissions'] === '*' ? array_keys($permissionIds) : $roleDef['permissions'];
            $this->syncRolePermissions($roleId, array_map(static fn (string $c) => $permissionIds[$c], $codes));
        }

        return $map;
    }

    /**
     * @param array<int, int> $permissionIds
     */
    private function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        $existing = $this->db->table('role_permissions')
            ->where('role_id', $roleId)
            ->get()->getResultArray();
        $existingIds = array_map(static fn (array $row) => (int) $row['permission_id'], $existing);

        $toInsert = array_diff($permissionIds, $existingIds);
        if ($toInsert === []) {
            return;
        }

        $rows = [];
        foreach ($toInsert as $permissionId) {
            $rows[] = ['role_id' => $roleId, 'permission_id' => $permissionId];
        }
        $this->db->table('role_permissions')->insertBatch($rows);
    }

    /**
     * @param array<string, int> $roleIds map of role code → id
     */
    private function ensureMembershipsForExistingUsers(int $appId, array $roleIds): void
    {
        if (! $this->db->fieldExists('role', 'users')) {
            return;
        }

        $users = $this->db->table('users')->select('id, role')->get()->getResultArray();
        if ($users === []) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        foreach ($users as $user) {
            $userId = (int) $user['id'];
            $roleCode = (string) ($user['role'] ?? 'user');
            $roleId = $roleIds[$roleCode] ?? $roleIds['user'];

            $membership = $this->db->table('app_user_memberships')
                ->where('user_id', $userId)
                ->where('application_id', $appId)
                ->get()->getRowArray();

            if ($membership !== null) {
                $membershipId = (int) $membership['id'];
            } else {
                $this->db->table('app_user_memberships')->insert([
                    'user_id'        => $userId,
                    'application_id' => $appId,
                    'status'         => 'active',
                    'accepted_at'    => $now,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
                $membershipId = (int) $this->db->insertID();
            }

            $hasRole = $this->db->table('membership_roles')
                ->where('membership_id', $membershipId)
                ->where('role_id', $roleId)
                ->countAllResults() > 0;

            if (! $hasRole) {
                $this->db->table('membership_roles')->insert([
                    'membership_id' => $membershipId,
                    'role_id'       => $roleId,
                ]);
            }
        }
    }
}
