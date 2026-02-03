<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\AuditLogModel;
use Tests\Support\DatabaseTestCase;

/**
 * AuditLogModel Integration Tests
 *
 * Tests database operations for audit logs including
 * creation, retrieval, filtering, and searchability.
 */
class AuditLogModelTest extends DatabaseTestCase
{
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected AuditLogModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new AuditLogModel();
        $this->seedDatabase();
    }

    protected function seedDatabase(): void
    {
        // Create test users
        $seeder = \Config\Database::seeder();
        $seeder->call('Tests\Support\Database\Seeds\TestUserSeeder');

        // Insert audit logs
        $this->db->table('audit_logs')->insertBatch([
            [
                'user_id' => 1,
                'action' => 'create',
                'entity_type' => 'user',
                'entity_id' => 10,
                'old_values' => null,
                'new_values' => '{"name":"John Doe","email":"john@example.com"}',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0',
                'created_at' => date('Y-m-d H:i:s', time() - 3600),
            ],
            [
                'user_id' => 1,
                'action' => 'update',
                'entity_type' => 'user',
                'entity_id' => 10,
                'old_values' => '{"name":"John Doe"}',
                'new_values' => '{"name":"Jane Doe"}',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0',
                'created_at' => date('Y-m-d H:i:s', time() - 1800),
            ],
            [
                'user_id' => 2,
                'action' => 'delete',
                'entity_type' => 'file',
                'entity_id' => 5,
                'old_values' => '{"filename":"test.pdf"}',
                'new_values' => null,
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Chrome/98.0',
                'created_at' => date('Y-m-d H:i:s', time() - 900),
            ],
        ]);
    }

    // ==================== VALIDATION TESTS ====================

    public function testValidationRequiresAction(): void
    {
        $data = [
            'entity_type' => 'user',
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $errors = $this->model->errors();
        $this->assertArrayHasKey('action', $errors);
    }

    public function testValidationRequiresEntityType(): void
    {
        $data = [
            'action' => 'create',
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $errors = $this->model->errors();
        $this->assertArrayHasKey('entity_type', $errors);
    }

    public function testValidationRequiresIpAddress(): void
    {
        $data = [
            'action' => 'create',
            'entity_type' => 'user',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $errors = $this->model->errors();
        $this->assertArrayHasKey('ip_address', $errors);
    }

    public function testInsertValidAuditLog(): void
    {
        $data = [
            'user_id' => 1,
            'action' => 'create',
            'entity_type' => 'user',
            'entity_id' => 20,
            'old_values' => null,
            'new_values' => '{"name":"Test"}',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    // ==================== GET BY ENTITY TESTS ====================

    public function testGetByEntityReturnsEntityLogs(): void
    {
        $logs = $this->model->getByEntity('user', 10);

        $this->assertIsArray($logs);
        $this->assertCount(2, $logs); // create + update

        foreach ($logs as $log) {
            $this->assertEquals('user', $log->entity_type);
            $this->assertEquals(10, $log->entity_id);
        }
    }

    public function testGetByEntityReturnsEmptyForNonExistent(): void
    {
        $logs = $this->model->getByEntity('user', 999);

        $this->assertIsArray($logs);
        $this->assertEmpty($logs);
    }

    public function testGetByEntityOrdersByCreatedAtDesc(): void
    {
        $logs = $this->model->getByEntity('user', 10);

        $this->assertCount(2, $logs);

        // First log should be more recent
        $this->assertGreaterThan(
            strtotime($logs[1]->created_at),
            strtotime($logs[0]->created_at)
        );
    }

    public function testGetByEntityDifferentiatesEntityTypes(): void
    {
        $userLogs = $this->model->getByEntity('user', 10);
        $fileLogs = $this->model->getByEntity('file', 5);

        $this->assertCount(2, $userLogs);
        $this->assertCount(1, $fileLogs);

        foreach ($userLogs as $log) {
            $this->assertEquals('user', $log->entity_type);
        }

        foreach ($fileLogs as $log) {
            $this->assertEquals('file', $log->entity_type);
        }
    }

    // ==================== GET BY USER TESTS ====================

    public function testGetByUserReturnsUserLogs(): void
    {
        $logs = $this->model->getByUser(1);

        $this->assertIsArray($logs);
        $this->assertCount(2, $logs);

        foreach ($logs as $log) {
            $this->assertEquals(1, $log->user_id);
        }
    }

    public function testGetByUserReturnsEmptyForUserWithNoLogs(): void
    {
        $logs = $this->model->getByUser(999);

        $this->assertIsArray($logs);
        $this->assertEmpty($logs);
    }

    public function testGetByUserRespectsLimit(): void
    {
        // Add more logs for user 1
        for ($i = 0; $i < 10; $i++) {
            $this->db->table('audit_logs')->insert([
                'user_id' => 1,
                'action' => 'create',
                'entity_type' => 'test',
                'entity_id' => $i,
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s', time() - ($i * 60)),
            ]);
        }

        $logs = $this->model->getByUser(1, 5);

        $this->assertCount(5, $logs);
    }

    public function testGetByUserOrdersByCreatedAtDesc(): void
    {
        $logs = $this->model->getByUser(1);

        for ($i = 0; $i < count($logs) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                strtotime($logs[$i + 1]->created_at),
                strtotime($logs[$i]->created_at)
            );
        }
    }

    // ==================== GET RECENT TESTS ====================

    public function testGetRecentReturnsLogs(): void
    {
        $logs = $this->model->getRecent(10);

        $this->assertIsArray($logs);
        $this->assertGreaterThan(0, count($logs));
    }

    public function testGetRecentRespectsLimit(): void
    {
        // Add many logs
        for ($i = 0; $i < 50; $i++) {
            $this->db->table('audit_logs')->insert([
                'user_id' => 1,
                'action' => 'create',
                'entity_type' => 'test',
                'entity_id' => $i,
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s', time() - ($i * 10)),
            ]);
        }

        $logs = $this->model->getRecent(20);

        $this->assertCount(20, $logs);
    }

    public function testGetRecentOrdersByCreatedAtDesc(): void
    {
        $logs = $this->model->getRecent(10);

        for ($i = 0; $i < count($logs) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                strtotime($logs[$i + 1]->created_at),
                strtotime($logs[$i]->created_at)
            );
        }
    }

    public function testGetRecentDefaultLimit(): void
    {
        // Add 150 logs
        for ($i = 0; $i < 150; $i++) {
            $this->db->table('audit_logs')->insert([
                'user_id' => 1,
                'action' => 'create',
                'entity_type' => 'test',
                'entity_id' => $i,
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s', time() - ($i * 5)),
            ]);
        }

        $logs = $this->model->getRecent(); // Default 100

        $this->assertCount(100, $logs);
    }

    // ==================== EDGE CASES ====================

    public function testInsertWithNullUserId(): void
    {
        $data = [
            'user_id' => null,
            'action' => 'create',
            'entity_type' => 'system',
            'entity_id' => 1,
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertIsInt($result);

        $log = $this->model->find($result);
        $this->assertNull($log->user_id);
    }

    public function testInsertWithNullEntityId(): void
    {
        $data = [
            'user_id' => 1,
            'action' => 'login',
            'entity_type' => 'auth',
            'entity_id' => null,
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertIsInt($result);

        $log = $this->model->find($result);
        $this->assertNull($log->entity_id);
    }

    public function testInsertWithJsonValues(): void
    {
        $oldValues = json_encode(['name' => 'Old', 'email' => 'old@example.com']);
        $newValues = json_encode(['name' => 'New', 'email' => 'new@example.com']);

        $data = [
            'user_id' => 1,
            'action' => 'update',
            'entity_type' => 'user',
            'entity_id' => 1,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertIsInt($result);

        $log = $this->model->find($result);
        $this->assertEquals($oldValues, $log->old_values);
        $this->assertEquals($newValues, $log->new_values);
    }

    public function testInsertWithLongUserAgent(): void
    {
        $longAgent = str_repeat('Mozilla/5.0 ', 50);

        $data = [
            'user_id' => 1,
            'action' => 'create',
            'entity_type' => 'user',
            'entity_id' => 1,
            'ip_address' => '127.0.0.1',
            'user_agent' => $longAgent,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertIsInt($result);
    }

    public function testInsertWithIPv6Address(): void
    {
        $data = [
            'user_id' => 1,
            'action' => 'create',
            'entity_type' => 'user',
            'entity_id' => 1,
            'ip_address' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertIsInt($result);
    }

    public function testGetByEntityHandlesMultipleActions(): void
    {
        $entityId = 50;

        // Create multiple actions for same entity
        $actions = ['create', 'update', 'update', 'delete'];
        foreach ($actions as $index => $action) {
            $this->db->table('audit_logs')->insert([
                'user_id' => 1,
                'action' => $action,
                'entity_type' => 'test_entity',
                'entity_id' => $entityId,
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s', time() - ((count($actions) - $index) * 60)),
            ]);
        }

        $logs = $this->model->getByEntity('test_entity', $entityId);

        $this->assertCount(4, $logs);

        // Verify order (most recent first)
        $this->assertEquals('delete', $logs[0]->action);
        $this->assertEquals('create', $logs[3]->action);
    }

    // ==================== DATA INTEGRITY TESTS ====================

    public function testCreatedAtIsStored(): void
    {
        $now = date('Y-m-d H:i:s');

        $data = [
            'user_id' => 1,
            'action' => 'create',
            'entity_type' => 'user',
            'entity_id' => 1,
            'ip_address' => '127.0.0.1',
            'created_at' => $now,
        ];

        $id = $this->model->insert($data);
        $log = $this->model->find($id);

        $this->assertNotNull($log->created_at);
        $this->assertEquals($now, $log->created_at);
    }

    public function testAutoIncrementIdWorks(): void
    {
        $data1 = [
            'user_id' => 1,
            'action' => 'create',
            'entity_type' => 'user',
            'entity_id' => 1,
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $data2 = [
            'user_id' => 1,
            'action' => 'update',
            'entity_type' => 'user',
            'entity_id' => 1,
            'ip_address' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $id1 = $this->model->insert($data1);
        $id2 = $this->model->insert($data2);

        $this->assertNotEquals($id1, $id2);
        $this->assertGreaterThan($id1, $id2);
    }

    public function testReturnsObjectType(): void
    {
        $logs = $this->model->getRecent(1);

        $this->assertNotEmpty($logs);
        $this->assertIsObject($logs[0]);
    }
}
