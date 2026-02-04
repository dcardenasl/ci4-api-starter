<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Exceptions\NotFoundException;
use App\Models\AuditLogModel;
use App\Services\AuditService;
use Tests\Support\DatabaseTestCase;

/**
 * AuditService Integration Tests
 *
 * Tests the complete audit logging flow with real database operations.
 */
class AuditServiceTest extends DatabaseTestCase
{
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected AuditService $service;
    protected AuditLogModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new AuditLogModel();
        $this->service = new AuditService($this->model);

        $this->seedDatabase();
    }

    protected function seedDatabase(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call('Tests\Support\Database\Seeds\TestUserSeeder');
    }

    // ==================== LOG INTEGRATION TESTS ====================

    public function testLogCreatesAuditRecord(): void
    {
        $this->service->log(
            'create',
            'user',
            1,
            [],
            ['name' => 'Test User'],
            1
        );

        $logs = $this->db->table('audit_logs')
            ->where('entity_type', 'user')
            ->where('entity_id', 1)
            ->get()
            ->getResult();

        $this->assertCount(1, $logs);
        $this->assertEquals('create', $logs[0]->action);
    }

    public function testLogStoresJsonValues(): void
    {
        $oldValues = ['name' => 'Old Name', 'email' => 'old@example.com'];
        $newValues = ['name' => 'New Name', 'email' => 'new@example.com'];

        $this->service->log('update', 'user', 1, $oldValues, $newValues, 1);

        $log = $this->db->table('audit_logs')
            ->orderBy('id', 'DESC')
            ->get()
            ->getFirstRow();

        $this->assertEquals($oldValues, json_decode($log->old_values, true));
        $this->assertEquals($newValues, json_decode($log->new_values, true));
    }

    public function testLogCapturesIpAddress(): void
    {
        $this->service->log('create', 'user', 1, [], ['name' => 'Test'], 1);

        $log = $this->db->table('audit_logs')
            ->orderBy('id', 'DESC')
            ->get()
            ->getFirstRow();

        $this->assertNotNull($log->ip_address);
    }

    // ==================== LOG CREATE INTEGRATION TESTS ====================

    public function testLogCreateCreatesRecord(): void
    {
        $data = ['name' => 'John Doe', 'email' => 'john@example.com'];

        $this->service->logCreate('user', 10, $data, 1);

        $log = $this->db->table('audit_logs')
            ->where('action', 'create')
            ->where('entity_id', 10)
            ->get()
            ->getFirstRow();

        $this->assertNotNull($log);
        $this->assertEquals($data, json_decode($log->new_values, true));
        $this->assertNull($log->old_values);
    }

    // ==================== LOG UPDATE INTEGRATION TESTS ====================

    public function testLogUpdateCreatesRecordWhenChanged(): void
    {
        $oldValues = ['name' => 'John'];
        $newValues = ['name' => 'Jane'];

        $this->service->logUpdate('user', 1, $oldValues, $newValues, 1);

        $count = $this->db->table('audit_logs')
            ->where('action', 'update')
            ->countAllResults();

        $this->assertGreaterThan(0, $count);
    }

    public function testLogUpdateDoesNotCreateRecordWhenUnchanged(): void
    {
        $values = ['name' => 'John', 'email' => 'john@example.com'];

        $beforeCount = $this->db->table('audit_logs')->countAllResults();

        $this->service->logUpdate('user', 1, $values, $values, 1);

        $afterCount = $this->db->table('audit_logs')->countAllResults();

        $this->assertEquals($beforeCount, $afterCount);
    }

    // ==================== LOG DELETE INTEGRATION TESTS ====================

    public function testLogDeleteCreatesRecord(): void
    {
        $data = ['name' => 'Deleted User', 'email' => 'deleted@example.com'];

        $this->service->logDelete('user', 5, $data, 1);

        $log = $this->db->table('audit_logs')
            ->where('action', 'delete')
            ->where('entity_id', 5)
            ->get()
            ->getFirstRow();

        $this->assertNotNull($log);
        $this->assertEquals($data, json_decode($log->old_values, true));
        $this->assertNull($log->new_values);
    }

    // ==================== INDEX INTEGRATION TESTS ====================

    public function testIndexReturnsLogs(): void
    {
        // Create some logs
        $this->service->logCreate('user', 1, ['name' => 'User 1'], 1);
        $this->service->logCreate('user', 2, ['name' => 'User 2'], 1);

        $result = $this->service->index([]);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
    }

    public function testIndexSupportsPagination(): void
    {
        // Create multiple logs
        for ($i = 0; $i < 20; $i++) {
            $this->service->logCreate('user', $i, ['name' => "User {$i}"], 1);
        }

        $result = $this->service->index(['page' => 1, 'limit' => 10]);

        $this->assertEquals('success', $result['status']);
        $this->assertCount(10, $result['data']);
        $this->assertEquals(1, $result['meta']['page']);
    }

    public function testIndexDecodesJsonValues(): void
    {
        $values = ['name' => 'Test', 'email' => 'test@example.com'];
        $this->service->logCreate('user', 1, $values, 1);

        $result = $this->service->index([]);

        $this->assertArrayHasKey('new_values', $result['data'][0]);
        $this->assertIsArray($result['data'][0]['new_values']);
        $this->assertEquals($values, $result['data'][0]['new_values']);
    }

    // ==================== SHOW INTEGRATION TESTS ====================

    public function testShowReturnsLog(): void
    {
        $this->service->logCreate('user', 1, ['name' => 'Test'], 1);

        $log = $this->db->table('audit_logs')
            ->orderBy('id', 'DESC')
            ->get()
            ->getFirstRow();

        $result = $this->service->show(['id' => $log->id]);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals($log->id, $result['data']['id']);
    }

    public function testShowReturnsErrorForNonExistent(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service->show(['id' => 99999]);
    }

    public function testShowDecodesJsonValues(): void
    {
        $values = ['name' => 'Test', 'email' => 'test@example.com'];
        $this->service->logCreate('user', 1, $values, 1);

        $log = $this->db->table('audit_logs')
            ->orderBy('id', 'DESC')
            ->get()
            ->getFirstRow();

        $result = $this->service->show(['id' => $log->id]);

        $this->assertIsArray($result['data']['new_values']);
        $this->assertEquals($values, $result['data']['new_values']);
    }

    // ==================== BY ENTITY INTEGRATION TESTS ====================

    public function testByEntityReturnsEntityLogs(): void
    {
        $entityId = 100;

        $this->service->logCreate('user', $entityId, ['name' => 'New'], 1);
        $this->service->logUpdate('user', $entityId, ['name' => 'New'], ['name' => 'Updated'], 1);
        $this->service->logDelete('user', $entityId, ['name' => 'Updated'], 1);

        $result = $this->service->byEntity(['entity_type' => 'user', 'entity_id' => $entityId]);

        $this->assertEquals('success', $result['status']);
        $this->assertCount(3, $result['data']);
    }

    public function testByEntityReturnsEmptyForNonExistent(): void
    {
        $result = $this->service->byEntity(['entity_type' => 'user', 'entity_id' => 99999]);

        $this->assertEquals('success', $result['status']);
        $this->assertEmpty($result['data']);
    }

    public function testByEntityOnlyReturnsSpecificEntity(): void
    {
        $this->service->logCreate('user', 1, ['name' => 'User 1'], 1);
        $this->service->logCreate('user', 2, ['name' => 'User 2'], 1);

        $result = $this->service->byEntity(['entity_type' => 'user', 'entity_id' => 1]);

        $this->assertEquals('success', $result['status']);
        $this->assertCount(1, $result['data']);
        $this->assertEquals(1, $result['data'][0]['entity_id']);
    }

    // ==================== FULL WORKFLOW TESTS ====================

    public function testCompleteAuditTrailWorkflow(): void
    {
        $entityId = 50;

        // 1. Create
        $this->service->logCreate('user', $entityId, ['name' => 'John', 'email' => 'john@example.com'], 1);

        // 2. Update
        $this->service->logUpdate(
            'user',
            $entityId,
            ['name' => 'John', 'email' => 'john@example.com'],
            ['name' => 'Jane', 'email' => 'jane@example.com'],
            1
        );

        // 3. Delete
        $this->service->logDelete('user', $entityId, ['name' => 'Jane'], 1);

        // Get entity history
        $result = $this->service->byEntity(['entity_type' => 'user', 'entity_id' => $entityId]);

        $this->assertEquals('success', $result['status']);
        $this->assertCount(3, $result['data']);

        // Verify order (most recent first)
        $this->assertEquals('delete', $result['data'][0]['action']);
        $this->assertEquals('update', $result['data'][1]['action']);
        $this->assertEquals('create', $result['data'][2]['action']);
    }

    public function testMultipleUsersAuditTrail(): void
    {
        $this->service->logCreate('file', 1, ['name' => 'file1.pdf'], 1);
        $this->service->logCreate('file', 2, ['name' => 'file2.pdf'], 2);

        $result = $this->service->index([]);

        $this->assertEquals('success', $result['status']);
        $this->assertGreaterThanOrEqual(2, count($result['data']));
    }
}
