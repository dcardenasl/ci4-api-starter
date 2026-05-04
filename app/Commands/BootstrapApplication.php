<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Creates a new application + its baseline `<code>.access` permission and
 * optionally attaches that permission to the seeded `user` role so all
 * existing end-users can immediately reach the new app.
 *
 * The role-permission graph for the rest of the new app's resources (CRUD,
 * read/write splits, etc.) is curated separately by the superadmin via the
 * IAM UI.
 */
class BootstrapApplication extends BaseCommand
{
    protected $group       = 'IAM';
    protected $name        = 'apps:bootstrap';
    protected $description = 'Create a new application, its <code>.access permission, and optionally grant it to the user role.';
    protected $usage       = 'apps:bootstrap <code> [--name="..."] [--no-grant-user]';
    protected $arguments   = [
        'code' => 'The application code (slug). Used as the prefix for permissions, e.g. blog → blog.access.',
    ];
    protected $options     = [
        '--name'           => 'Display name for the application. Defaults to the code (capitalized).',
        '--no-grant-user'  => 'Skip granting <code>.access to the user role (no interactive prompt).',
    ];

    public function run(array $params)
    {
        $code = strtolower(trim((string) ($params[0] ?? '')));
        if ($code === '' || ! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $code)) {
            CLI::error('Provide a slug-style application code (lowercase letters, digits, dashes, underscores).');
            return EXIT_ERROR;
        }

        $name = (string) (CLI::getOption('name') ?: ucfirst($code));
        $skipGrant = (bool) CLI::getOption('no-grant-user');

        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $appId = $this->ensureApplication($db, $code, $name, $now);
        CLI::write("✓ Application '{$code}' (id={$appId}) ready.", 'green');

        $permId = $this->ensurePermission($db, $appId, $code, $now);
        CLI::write("✓ Permission '{$code}.access' (id={$permId}) ready.", 'green');

        $shouldGrant = ! $skipGrant && CLI::prompt(
            "Grant '{$code}.access' to the 'user' role so every registered user can access this app?",
            ['y', 'n'],
            'required'
        ) === 'y';

        if ($shouldGrant) {
            $userRoleId = $this->resolveUserRoleId($db);
            if ($userRoleId === null) {
                CLI::error('Could not find the seeded "user" role. Run "php spark db:seed RbacBootstrapSeeder" first.');
                return EXIT_ERROR;
            }

            $this->ensureRolePermission($db, $userRoleId, $permId);
            CLI::write("✓ '{$code}.access' attached to the 'user' role.", 'green');
        } else {
            CLI::write("• Skipped granting '{$code}.access' to the 'user' role.", 'yellow');
        }

        CLI::newLine();
        CLI::write("Next: as superadmin, open the IAM Roles UI to assign per-resource permissions for the '{$code}' app.", 'cyan');

        return EXIT_SUCCESS;
    }

    /**
     * @param \CodeIgniter\Database\BaseConnection $db
     */
    private function ensureApplication($db, string $code, string $name, string $now): int
    {
        $existing = $db->table('applications')->where('code', $code)->get()?->getRowArray();
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $db->table('applications')->insert([
            'code'       => $code,
            'name'       => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $db->insertID();
    }

    /**
     * @param \CodeIgniter\Database\BaseConnection $db
     */
    private function ensurePermission($db, int $appId, string $code, string $now): int
    {
        $existing = $db->table('permissions')
            ->where('application_id', $appId)
            ->where('code', "{$code}.access")
            ->get()
            ?->getRowArray();

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $db->table('permissions')->insert([
            'application_id' => $appId,
            'code'           => "{$code}.access",
            'resource'       => $code,
            'action'         => 'access',
            'description'    => "Baseline access to the {$code} application",
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        return (int) $db->insertID();
    }

    /**
     * @param \CodeIgniter\Database\BaseConnection $db
     */
    private function resolveUserRoleId($db): ?int
    {
        $row = $db->table('roles')->where('code', 'user')->get()?->getRowArray();
        return $row !== null ? (int) $row['id'] : null;
    }

    /**
     * @param \CodeIgniter\Database\BaseConnection $db
     */
    private function ensureRolePermission($db, int $roleId, int $permissionId): void
    {
        $exists = $db->table('role_permissions')
            ->where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->countAllResults() > 0;

        if (! $exists) {
            $db->table('role_permissions')->insert([
                'role_id'       => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }
}
