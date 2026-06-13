<?php

declare(strict_types=1);

namespace Tests\Feature\Iam;

use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

/**
 * @internal
 */
final class RolePermissionMatrixTest extends ApiTestCase
{
    use AuthTestTrait;

    public function testSuperadminCanReadRolePermissionMatrixShape(): void
    {
        $this->actAs('superadmin');

        $result = $this->get('/api/v1/iam/role-permission-matrix');

        $result->assertStatus(200);

        $json = $this->getResponseJson($result);
        $data = $json['data'] ?? [];

        $this->assertIsArray($data);
        $this->assertArrayHasKey('applications', $data);
        $this->assertArrayHasKey('roles', $data);
        $this->assertArrayHasKey('assignments', $data);
        $this->assertIsArray($data['applications']);
        $this->assertIsArray($data['roles']);
        $this->assertIsArray($data['assignments']);
    }
}
