<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\SecurityContext;
use App\Exceptions\NotFoundException;
use App\Models\AuditLogModel;
use App\Services\System\AuditService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * AuditService Unit Tests
 *
 * Tests audit logging functionality with mocked dependencies.
 */
class AuditServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected AuditService $service;
    protected AuditLogModel $mockAuditLogModel;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Services\System\AuditService::$forceEnabledInTests = true;

        $this->mockAuditLogModel = $this->createMock(AuditLogModel::class);

        // Mock UserModel via factory to satisfy defensive existence checks
        $mockUserModel = $this->createMock(\App\Models\UserModel::class);
        $mockUserModel->method('find')->willReturn((object)['id' => 99]);
        \CodeIgniter\Config\Factories::injectMock('models', \App\Models\UserModel::class, $mockUserModel);

        $this->service = new AuditService($this->mockAuditLogModel);
    }

    protected function tearDown(): void
    {
        \App\Services\System\AuditService::$forceEnabledInTests = false;
        parent::tearDown();
    }

    // ==================== LOG TESTS ====================

    public function testLogInsertsAuditRecord(): void
    {
        $context = new SecurityContext(99, 'admin', ['ip_address' => '127.0.0.1']);

        $this->mockAuditLogModel
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                return $data['action'] === 'create'
                    && $data['entity_type'] === 'users'
                    && $data['entity_id'] === 1
                    && $data['user_id'] === 99
                    && $data['ip_address'] === '127.0.0.1';
            }));

        $this->service->log(
            'create',
            'users',
            1,
            [],
            ['email' => 'newuser@example.com'],
            $context
        );
    }

    public function testLogEncodesValuesAsJson(): void
    {
        $context = new SecurityContext(99);
        $oldValues = ['email' => 'old@example.com'];
        $newValues = ['email' => 'new@example.com'];

        $this->mockAuditLogModel
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) use ($oldValues, $newValues) {
                return $data['old_values'] === json_encode($oldValues)
                    && $data['new_values'] === json_encode($newValues);
            }));

        $this->service->log(
            'update',
            'users',
            1,
            $oldValues,
            $newValues,
            $context
        );
    }

    public function testLogRemovesSensitiveFieldsFromAuditPayload(): void
    {
        $context = new SecurityContext(99);
        $oldValues = [
            'email' => 'old@example.com',
            'password' => 'old-secret',
            'profile' => [
                'token' => 'token-old',
                'timezone' => 'UTC',
            ],
        ];
        $newValues = [
            'email' => 'new@example.com',
            'password' => 'new-secret',
            'access_token' => 'jwt-token',
            'profile' => [
                'refresh_token' => 'refresh-token',
                'timezone' => 'America/Mexico_City',
            ],
        ];

        $this->mockAuditLogModel
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                $old = json_decode((string) $data['old_values'], true);
                $new = json_decode((string) $data['new_values'], true);

                return !isset($old['password'])
                    && !isset($old['profile']['token'])
                    && !isset($new['password'])
                    && !isset($new['access_token'])
                    && !isset($new['profile']['refresh_token'])
                    && ($old['email'] ?? null) === 'old@example.com'
                    && ($new['email'] ?? null) === 'new@example.com';
            }));

        $this->service->log(
            'update',
            'users',
            1,
            $oldValues,
            $newValues,
            $context
        );
    }

    // ==================== LOG CREATE TESTS ====================

    public function testLogCreateLogsWithEmptyOldValues(): void
    {
        $context = new SecurityContext(99);
        $newData = ['first_name' => 'New', 'email' => 'new@example.com'];

        $this->mockAuditLogModel
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) use ($newData) {
                return $data['action'] === 'create'
                    && $data['old_values'] === null
                    && $data['new_values'] === json_encode($newData);
            }));

        $this->service->logCreate('users', 1, $newData, $context);
    }

    // ==================== LOG UPDATE TESTS ====================

    public function testLogUpdateOnlyLogsIfValuesChanged(): void
    {
        $context = new SecurityContext(99);
        $oldValues = ['email' => 'same@example.com'];
        $newValues = ['email' => 'same@example.com'];

        // insert should NOT be called when values are the same
        $this->mockAuditLogModel
            ->expects($this->never())
            ->method('insert');

        $this->service->logUpdate('users', 1, $oldValues, $newValues, $context);
    }

    public function testLogUpdateLogsWhenValuesAreDifferent(): void
    {
        $context = new SecurityContext(99);
        $oldValues = ['email' => 'old@example.com'];
        $newValues = ['email' => 'new@example.com'];

        $this->mockAuditLogModel
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                return $data['action'] === 'update';
            }));

        $this->service->logUpdate('users', 1, $oldValues, $newValues, $context);
    }

    public function testLogUpdateDoesNotLogWhenOnlySensitiveValuesChanged(): void
    {
        $context = new SecurityContext(99);
        $oldValues = [
            'email' => 'same@example.com',
            'password' => 'old-secret',
            'profile' => ['token' => 'old-token'],
        ];
        $newValues = [
            'email' => 'same@example.com',
            'password' => 'new-secret',
            'profile' => ['token' => 'new-token'],
        ];

        $this->mockAuditLogModel
            ->expects($this->never())
            ->method('insert');

        $this->service->logUpdate('users', 1, $oldValues, $newValues, $context);
    }

    // ==================== LOG DELETE TESTS ====================

    public function testLogDeleteLogsWithEmptyNewValues(): void
    {
        $context = new SecurityContext(99);
        $oldData = ['first_name' => 'Deleted', 'email' => 'deleted@example.com'];

        $this->mockAuditLogModel
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) use ($oldData) {
                return $data['action'] === 'delete'
                    && $data['old_values'] === json_encode($oldData)
                    && $data['new_values'] === null;
            }));

        $this->service->logDelete('users', 1, $oldData, $context);
    }

    // ==================== SHOW TESTS ====================

    public function testShowReturnsAuditLog(): void
    {
        $log = $this->createAuditLogEntity([
            'id' => 1,
            'user_id' => 99,
            'action' => 'create',
            'entity_type' => 'users',
            'entity_id' => 1,
            'old_values' => null,
            'new_values' => '{"first_name":"Test"}',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'created_at' => '2024-01-01 00:00:00',
        ]);

        $this->mockAuditLogModel
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($log);

        $result = $this->service->show(1);

        $this->assertInstanceOf(\App\DTO\Response\Audit\AuditResponseDTO::class, $result);
        $data = $result->toArray();
        $this->assertEquals(1, $data['id']);
        $this->assertEquals('create', $data['action']);
    }

    public function testShowWithNonExistentIdThrowsNotFoundException(): void
    {
        $this->mockAuditLogModel
            ->method('find')
            ->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->show(999);
    }

    // ==================== BY ENTITY TESTS ====================

    public function testByEntityReturnsLogsForEntity(): void
    {
        $logs = [
            $this->createAuditLogEntity([
                'id' => 1,
                'user_id' => 99,
                'action' => 'create',
                'entity_type' => 'users',
                'entity_id' => 5,
                'old_values' => null,
                'new_values' => '{"test":"data"}',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test',
                'created_at' => '2024-01-01 00:00:00',
            ]),
            $this->createAuditLogEntity([
                'id' => 2,
                'user_id' => 99,
                'action' => 'update',
                'entity_type' => 'users',
                'entity_id' => 5,
                'old_values' => '{"old":"value"}',
                'new_values' => '{"new":"value"}',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test',
                'created_at' => '2024-01-01 01:00:00',
            ]),
        ];

        $this->mockAuditLogModel
            ->expects($this->once())
            ->method('getByEntity')
            ->with('users', 5)
            ->willReturn($logs);

        $result = $this->service->byEntity(new \App\DTO\Request\Audit\AuditByEntityRequestDTO([
            'entity_type' => 'users',
            'entity_id' => 5,
        ]));
        $payload = $result->toArray();

        $this->assertInstanceOf(\App\DTO\Response\Common\PayloadResponseDTO::class, $result);
        $this->assertCount(2, $payload);
        $this->assertIsArray($payload[0]);
        $this->assertSame('create', $payload[0]['action'] ?? null);
    }

    public function testByEntityNormalizesSingularEntityType(): void
    {
        $this->mockAuditLogModel
            ->expects($this->once())
            ->method('getByEntity')
            ->with('users', 5)
            ->willReturn([]);

        $result = $this->service->byEntity(new \App\DTO\Request\Audit\AuditByEntityRequestDTO([
            'entity_type' => 'user',
            'entity_id' => 5,
        ]));

        $this->assertSame([], $result->toArray());
    }

    public function testByEntityWithMissingParamsThrowsValidationException(): void
    {
        $this->expectException(\App\Exceptions\ValidationException::class);
        new \App\DTO\Request\Audit\AuditByEntityRequestDTO(['entity_type' => 'users']);
    }

    // ==================== HELPER METHODS ====================

    private function createAuditLogEntity(array $data): \App\Entities\AuditLogEntity
    {
        return new \App\Entities\AuditLogEntity($data);
    }
}
