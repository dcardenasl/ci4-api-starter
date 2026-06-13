<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Iam;

use App\Database\Seeds\RbacBootstrapSeeder;
use App\Libraries\Iam\SelfPermissionService;
use App\Models\ApplicationModel;
use App\Models\PermissionModel;
use CodeIgniter\Database\Seeder;
use Config\Database;
use Config\Services;
use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

final class RbacAutomationTest extends ApiTestCase
{
    use AuthTestTrait;

    public function testSuperadminResolvesAllPermissionsForRequestedApplication(): void
    {
        $user = $this->actAs('superadmin');
        $appId = $this->insertApplication('catalog', 'Catalog');
        $permissionId = $this->insertPermission($appId, 'catalog.products.read', 'products', 'read');

        $this->assertFalse($this->roleHasPermission('superadmin', $permissionId));

        $codes = Services::effectivePermissionsResolver(false)->resolve((int) $user['user_id'], $appId);

        $this->assertContains('catalog.products.read', $codes);
    }

    public function testSelfPermissionSyncAttachesCreatedAndExistingPermissionsToSuperadmin(): void
    {
        $appId = $this->insertApplication('cms-sync', 'CMS Sync');
        $existingId = $this->insertPermission($appId, 'cms-sync.pages.read', 'pages', 'read');

        $service = new SelfPermissionService(new PermissionModel(), new ApplicationModel());
        $result = $service->sync($appId, [
            [
                'code'        => 'cms-sync.pages.read',
                'resource'    => 'pages',
                'action'      => 'read',
                'description' => 'Read pages',
            ],
            [
                'code'        => 'cms-sync.pages.create',
                'resource'    => 'pages',
                'action'      => 'create',
                'description' => 'Create pages',
            ],
        ]);

        $created = Database::connect()->table('permissions')
            ->where('application_id', $appId)
            ->where('code', 'cms-sync.pages.create')
            ->get()
            ->getRowArray();

        $this->assertSame(1, $result->created);
        $this->assertSame(1, $result->existing);
        $this->assertTrue($this->roleHasPermission('superadmin', $existingId));
        $this->assertTrue($this->roleHasPermission('superadmin', (int) $created['id']));
    }

    public function testRbacBootstrapSeederKeepsDomainPermissionsOnSuperadmin(): void
    {
        $appId = $this->insertApplication('cms-seed', 'CMS Seed');
        $permissionId = $this->insertPermission($appId, 'cms-seed.pages.read', 'pages', 'read');
        $this->attachPermissionToRole('superadmin', $permissionId);

        (new Seeder(new \Config\Database(), Database::connect()))->call(RbacBootstrapSeeder::class);

        $this->assertTrue($this->roleHasPermission('superadmin', $permissionId));
    }

    private function insertApplication(string $code, string $name): int
    {
        $db = Database::connect();
        $db->table('applications')->insert([
            'code'       => $code,
            'name'       => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }

    private function insertPermission(int $appId, string $code, string $resource, string $action): int
    {
        $db = Database::connect();
        $db->table('permissions')->insert([
            'application_id' => $appId,
            'code'           => $code,
            'resource'       => $resource,
            'action'         => $action,
            'description'    => $code,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }

    private function roleHasPermission(string $roleCode, int $permissionId): bool
    {
        $db = Database::connect();
        $role = $db->table('roles')->where('code', $roleCode)->get()->getRowArray();

        return $db->table('role_permissions')
            ->where('role_id', (int) $role['id'])
            ->where('permission_id', $permissionId)
            ->countAllResults() > 0;
    }

    private function attachPermissionToRole(string $roleCode, int $permissionId): void
    {
        $db = Database::connect();
        $role = $db->table('roles')->where('code', $roleCode)->get()->getRowArray();
        $db->table('role_permissions')->insert([
            'role_id'       => (int) $role['id'],
            'permission_id' => $permissionId,
        ]);
    }
}
