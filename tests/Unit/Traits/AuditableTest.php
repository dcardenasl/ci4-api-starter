<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Auditable Trait Tests
 *
 * Uses UserModel which implements the Auditable trait
 */
class AuditableTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Services\System\AuditService::$forceEnabledInTests = true;
        $this->userModel = new UserModel();
    }

    protected function tearDown(): void
    {
        \App\Services\System\AuditService::$forceEnabledInTests = false;
        parent::tearDown();
    }

    public function testAuditInsertCreatesAuditLog(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'audit@example.com',
            'password' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'first_name' => 'Audit',
            'last_name' => 'Test',
        ]);

        $this->assertIsInt($userId);

        // Check if audit log was created
        $auditLogs = $this->db->table('audit_logs')
            ->where('entity_type', 'users')
            ->where('entity_id', $userId)
            ->where('action', 'create')
            ->get()
            ->getResult();

        $this->assertNotEmpty($auditLogs);
        $this->assertCount(1, $auditLogs);
    }

    public function testAuditUpdateCreatesAuditLog(): void
    {
        // Create user first
        $userId = $this->userModel->insert([
            'email' => 'update@example.com',
            'password' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        // Clear previous audit logs for cleaner test
        $initialAuditCount = $this->db->table('audit_logs')
            ->where('entity_id', $userId)
            ->countAllResults();

        // Update user
        $this->userModel->update($userId, [
            'first_name' => 'Updated',
            'role' => 'admin',
        ]);

        // Check if audit log was created for update
        $finalAuditCount = $this->db->table('audit_logs')
            ->where('entity_type', 'users')
            ->where('entity_id', $userId)
            ->where('action', 'update')
            ->countAllResults();

        $this->assertGreaterThan(0, $finalAuditCount);
    }

    public function testAuditDeleteCreatesAuditLog(): void
    {
        // Create user first
        $userId = $this->userModel->insert([
            'email' => 'delete@example.com',
            'password' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        // Delete user
        $this->userModel->delete($userId);

        // Check if audit log was created for delete
        $auditLogs = $this->db->table('audit_logs')
            ->where('entity_type', 'users')
            ->where('entity_id', $userId)
            ->where('action', 'delete')
            ->get()
            ->getResult();

        $this->assertNotEmpty($auditLogs);
    }

    public function testAuditLogContainsOldAndNewValues(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'values@example.com',
            'password' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'first_name' => 'Original',
        ]);

        // Update user
        $this->userModel->update($userId, [
            'first_name' => 'Modified',
            'last_name' => 'Added',
        ]);

        // Get the update audit log
        $auditLog = $this->db->table('audit_logs')
            ->where('entity_type', 'users')
            ->where('entity_id', $userId)
            ->where('action', 'update')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRow();

        $this->assertNotNull($auditLog);
        $this->assertNotNull($auditLog->old_values);
        $this->assertNotNull($auditLog->new_values);

        $oldValues = json_decode($auditLog->old_values, true);
        $newValues = json_decode($auditLog->new_values, true);

        $this->assertIsArray($oldValues);
        $this->assertIsArray($newValues);
    }

    public function testAuditDoesNotLogSensitiveFields(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'sensitive@example.com',
            'password' => password_hash('SecretPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        // Get the insert audit log
        $auditLog = $this->db->table('audit_logs')
            ->where('entity_type', 'users')
            ->where('entity_id', $userId)
            ->where('action', 'create')
            ->get()
            ->getRow();

        $this->assertNotNull($auditLog);

        $newValues = json_decode($auditLog->new_values, true);

        // Password should not be in audit log (filtered by UserEntity::toArray())
        $this->assertArrayNotHasKey('password', $newValues);
    }

    public function testAuditLogsUseCorrectEntityType(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'entitytype@example.com',
            'password' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $auditLog = $this->db->table('audit_logs')
            ->where('entity_id', $userId)
            ->get()
            ->getRow();

        $this->assertNotNull($auditLog);
        $this->assertEquals('users', $auditLog->entity_type);
    }
}
