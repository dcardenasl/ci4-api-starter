<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\AuditLogModel;
use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

class CatalogControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actAs('admin');
    }

    public function testCatalogEndpointsRequireAdmin(): void
    {
        $this->actAs('user');

        $catalogs = $this->get('/api/v1/catalogs');
        $catalogs->assertStatus(403);

        $facets = $this->get('/api/v1/catalogs/audit-facets');
        $facets->assertStatus(403);
    }

    public function testAdminCanReadCatalogIndex(): void
    {
        $result = $this->get('/api/v1/catalogs');
        $result->assertStatus(200);

        $json = $this->getResponseJson($result);
        $this->assertSame('success', $json['status'] ?? null);
        $this->assertArrayHasKey('users', $json['data'] ?? []);
        $this->assertArrayHasKey('api_keys', $json['data'] ?? []);
    }

    public function testAdminCanReadAuditFacets(): void
    {
        $auditLogModel = new AuditLogModel();
        $auditLogModel->insert([
            'user_id' => $this->currentUserId,
            'action' => 'create',
            'entity_type' => 'users',
            'entity_id' => $this->currentUserId,
            'old_values' => null,
            'new_values' => json_encode(['email' => 'catalog@example.com']),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'tests',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->get('/api/v1/catalogs/audit-facets?window_days=30&limit=10');
        $result->assertStatus(200);

        $json = $this->getResponseJson($result);
        $this->assertSame('success', $json['status'] ?? null);
        $this->assertArrayHasKey('actions', $json['data'] ?? []);
        $this->assertArrayHasKey('entity_types', $json['data'] ?? []);
    }
}
