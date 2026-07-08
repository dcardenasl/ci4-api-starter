<?php

declare(strict_types=1);

namespace Tests\Integration\Commands;

use App\Database\Seeds\RbacBootstrapSeeder;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\Mock\MockInputOutput;
use ReflectionClass;
use Tests\Support\IntegrationTestCase;

/**
 * Integration tests for `iam:remove-mirrored-permissions` (WBS-BP-09).
 *
 * Mirrored permissions are rows inserted under the "self" application
 * (application_id = 1) by a Domain app's
 * `domain:sync-permissions --mirror-to-self`, whose `code` is always
 * prefixed with the source app's `code` + '.' (e.g. a Domain app with
 * code "blog" mirrors "posts.write" in as "blog.posts.write"). The
 * command detects a mirrored row purely by that naming convention: a
 * self-scoped permission code that starts with `<other-app-code>.` for
 * some OTHER registered application.
 *
 * Note: `IntegrationTestCase` purges non-migration tables only once per
 * *class* (not per test method) to avoid DDL pressure — see its docblock.
 * That means fixtures inserted by one test method are still present for
 * the next one in this class. Every test here tracks the ids it creates
 * and tears them down explicitly so behavior doesn't depend on execution
 * order (relevant since `phpunit.xml` uses `executionOrder="depends,defects"`,
 * which reorders tests after a failure).
 *
 * @internal
 */
final class RemoveMirroredPermissionsTest extends IntegrationTestCase
{
    protected $seed = RbacBootstrapSeeder::class;

    /** @var list<int> */
    private array $createdAppIds = [];

    /** @var list<int> */
    private array $createdPermissionIds = [];

    /** @var list<int> */
    private array $createdRoleIds = [];

    protected function tearDown(): void
    {
        $db = \Config\Database::connect();

        if ($this->createdRoleIds !== [] || $this->createdPermissionIds !== []) {
            $builder = $db->table('role_permissions');
            if ($this->createdRoleIds !== []) {
                $builder->orWhereIn('role_id', $this->createdRoleIds);
            }
            if ($this->createdPermissionIds !== []) {
                $builder->orWhereIn('permission_id', $this->createdPermissionIds);
            }
            $builder->delete();
        }

        if ($this->createdPermissionIds !== []) {
            $db->table('permissions')->whereIn('id', $this->createdPermissionIds)->delete();
        }

        if ($this->createdRoleIds !== []) {
            $db->table('roles')->whereIn('id', $this->createdRoleIds)->delete();
        }

        if ($this->createdAppIds !== []) {
            $db->table('applications')->whereIn('id', $this->createdAppIds)->delete();
        }

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $params
     */
    private function runCommand(string $command, array $params): string
    {
        $options = [];

        foreach ($params as $key => $value) {
            $options[$key] = $value === true ? null : (string) $value;
        }

        $reflection      = new ReflectionClass(CLI::class);
        $optionsProperty = $reflection->getProperty('options');
        $optionsProperty->setValue(null, $options);

        $io = new MockInputOutput();
        CLI::setInputOutput($io);

        try {
            service('commands')->run($command, []);
        } finally {
            $optionsProperty->setValue(null, []);
            CLI::resetInputOutput();
        }

        return $io->getOutput();
    }

    private function insertApp(string $code): int
    {
        $db = \Config\Database::connect();
        $db->table('applications')->insert([
            'code'       => $code,
            'name'       => ucfirst($code),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $id = (int) $db->insertID();
        $this->createdAppIds[] = $id;

        return $id;
    }

    private function insertPermission(int $applicationId, string $code): int
    {
        [$resource, $action] = explode('.', $code, 2) + [1 => 'access'];

        $db = \Config\Database::connect();
        $db->table('permissions')->insert([
            'application_id' => $applicationId,
            'code'           => $code,
            'resource'       => $resource,
            'action'         => $action,
            'description'    => "Test permission {$code}",
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $id = (int) $db->insertID();
        $this->createdPermissionIds[] = $id;

        return $id;
    }

    private function insertRole(string $code): int
    {
        $db = \Config\Database::connect();
        $db->table('roles')->insert([
            'code'               => $code,
            'name'               => $code,
            'description'        => "Test role {$code}",
            'is_system'          => 0,
            'is_self_assignable' => 0,
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        $id = (int) $db->insertID();
        $this->createdRoleIds[] = $id;

        return $id;
    }

    private function attachPermToRole(int $roleId, int $permissionId): void
    {
        \Config\Database::connect()->table('role_permissions')->insert([
            'role_id'       => $roleId,
            'permission_id' => $permissionId,
        ]);
    }

    public function testEmptyCaseWithNoOtherApplicationsDoesNotErrorOrDelete(): void
    {
        $db = \Config\Database::connect();
        $this->assertSame(
            0,
            $db->table('applications')->where('code !=', 'self')->countAllResults(),
            'Fixture assumption for this test: no other applications registered besides "self".'
        );
        $selfPermCountBefore = $db->table('permissions')->countAllResults();

        $output = $this->runCommand('iam:remove-mirrored-permissions', []);

        $this->assertStringContainsString('No other applications found. Nothing to do.', $output);
        $this->assertSame(
            $selfPermCountBefore,
            $db->table('permissions')->countAllResults(),
            'Nothing should be deleted when there are no other registered applications.'
        );
    }

    public function testEmptyCaseWithOtherApplicationButNoMirroredPermissionsDoesNotError(): void
    {
        $this->insertApp('blog-empty-case');
        $selfPermCountBefore = \Config\Database::connect()->table('permissions')->countAllResults();

        $output = $this->runCommand('iam:remove-mirrored-permissions', []);

        $this->assertStringContainsString('No mirrored permissions found. Nothing to do.', $output);
        $this->assertSame(
            $selfPermCountBefore,
            \Config\Database::connect()->table('permissions')->countAllResults(),
            'Nothing should be deleted when self has no permission codes prefixed by another app\'s code.'
        );
    }

    public function testDryRunReportsMirroredPermissionWithoutDeletingIt(): void
    {
        $this->insertApp('blog-dry-run');
        $mirroredId = $this->insertPermission(1, 'blog-dry-run.posts.write');

        $output = $this->runCommand('iam:remove-mirrored-permissions', ['dry-run' => true]);

        $this->assertStringContainsString('Found 1 mirrored permission(s)', $output);
        $this->assertStringContainsString('blog-dry-run.posts.write', $output);
        $this->assertStringContainsString('Dry-run: no changes made.', $output);

        $this->seeInDatabase('permissions', ['id' => $mirroredId, 'code' => 'blog-dry-run.posts.write']);
    }

    public function testActualRunDeletesOnlyGenuinelyMirroredPermissionsAndLeavesLegitimateOnesUntouched(): void
    {
        $blogAppId = $this->insertApp('blog-actual-run');

        // Genuinely mirrored: code is prefixed with the OTHER app's code.
        $mirroredId = $this->insertPermission(1, 'blog-actual-run.posts.write');

        // A legitimately-registered self permission (seeded by
        // RbacBootstrapSeeder) must never be touched.
        $legitSelfPermId = (int) $this->grabFromDatabase('permissions', 'id', [
            'application_id' => 1,
            'code'           => 'users.read',
        ]);
        $this->assertGreaterThan(0, $legitSelfPermId, 'Fixture assumption: RbacBootstrapSeeder must seed users.read under self.');

        // A permission that genuinely belongs to the OTHER app (not self)
        // must never be touched either — it's not even in self's namespace.
        $blogOwnPermId = $this->insertPermission($blogAppId, 'blog-actual-run.posts.write');

        // Attach the mirrored row to a role to confirm the join table is
        // cleaned up too, and that deleting the mirror doesn't cascade into
        // unrelated role_permissions rows for the legit permission.
        $role = $this->insertRole('mirror-cleanup-role');
        $this->attachPermToRole($role, $mirroredId);
        $this->attachPermToRole($role, $legitSelfPermId);

        $output = $this->runCommand('iam:remove-mirrored-permissions', []);

        $this->assertStringContainsString('Found 1 mirrored permission(s)', $output);
        $this->assertStringContainsString('Removed 1 permission(s)', $output);

        // Mirrored row and its role assignment are gone.
        $this->dontSeeInDatabase('permissions', ['id' => $mirroredId]);
        $this->dontSeeInDatabase('role_permissions', ['permission_id' => $mirroredId]);

        // Legitimate self permission survives, code and role assignment intact.
        $this->seeInDatabase('permissions', ['id' => $legitSelfPermId, 'code' => 'users.read']);
        $this->seeInDatabase('role_permissions', ['role_id' => $role, 'permission_id' => $legitSelfPermId]);

        // The other app's own (non-mirrored) copy of the same code, scoped
        // to its own application_id, is untouched.
        $this->seeInDatabase('permissions', ['id' => $blogOwnPermId, 'application_id' => $blogAppId]);
    }

    public function testSecondRunAfterCleanupIsIdempotent(): void
    {
        $this->insertApp('blog-idempotent');
        $this->insertPermission(1, 'blog-idempotent.posts.write');

        $this->runCommand('iam:remove-mirrored-permissions', []);
        $secondOutput = $this->runCommand('iam:remove-mirrored-permissions', []);

        $this->assertStringContainsString('No mirrored permissions found. Nothing to do.', $secondOutput);
    }
}
