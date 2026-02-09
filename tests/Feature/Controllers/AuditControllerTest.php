<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\AuditLogModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\AuthTestTrait;

class AuditControllerTest extends CIUnitTestCase
{
    use AuthTestTrait;
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected AuditLogModel $auditLogModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditLogModel = new AuditLogModel();
    }

    public function testAuditRequiresAdmin(): void
    {
        $email = 'audit-user@example.com';
        $password = 'ValidPass123!';
        $this->createUser($email, $password, 'user');

        $token = $this->loginAndGetToken($email, $password);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->get('/api/v1/audit');

        $result->assertStatus(403);
    }

    public function testAuditEndpointsReturnSuccessForAdmin(): void
    {
        $email = 'audit-admin@example.com';
        $password = 'ValidPass123!';
        $adminId = $this->createUser($email, $password, 'admin');

        $logId = $this->createAuditLog($adminId);

        $token = $this->loginAndGetToken($email, $password);

        $listResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->get('/api/v1/audit');

        $listResult->assertStatus(200);

        $showResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->get("/api/v1/audit/{$logId}");

        $showResult->assertStatus(200);

        $entityResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->get("/api/v1/audit/entity/user/{$adminId}");

        $entityResult->assertStatus(200);
    }

    private function createAuditLog(int $userId): int
    {
        return (int) $this->auditLogModel->insert([
            'user_id' => $userId,
            'action' => 'create',
            'entity_type' => 'user',
            'entity_id' => $userId,
            'old_values' => null,
            'new_values' => json_encode(['email' => 'audit@example.com']),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'tests',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
