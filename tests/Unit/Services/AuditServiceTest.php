<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Models\AuditLogModel;
use App\Services\AuditService;
use CodeIgniter\HTTP\RequestInterface;
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
    protected RequestInterface $mockRequest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockAuditLogModel = $this->createMock(AuditLogModel::class);
        $this->mockRequest = $this->createMock(RequestInterface::class);

        $this->mockRequest->method('getIPAddress')->willReturn('127.0.0.1');
        $this->mockRequest->method('getHeaderLine')->willReturn('PHPUnit/Test');

        $this->service = new AuditService($this->mockAuditLogModel);
    }

    // ==================== LOG TESTS ====================

    public function testLogInsertsAuditRecord(): void
    {
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
            99,
            $this->mockRequest
        );
    }

    public function testLogEncodesValuesAsJson(): void
    {
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
            99,
            $this->mockRequest
        );
    }

    public function testLogRemovesSensitiveFieldsFromAuditPayload(): void
    {
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
            99,
            $this->mockRequest
        );
    }

    // ==================== LOG CREATE TESTS ====================

    public function testLogCreateLogsWithEmptyOldValues(): void
    {
        $newData = ['first_name' => 'New', 'email' => 'new@example.com'];

        $this->mockAuditLogModel
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) use ($newData) {
                return $data['action'] === 'create'
                    && $data['old_values'] === null
                    && $data['new_values'] === json_encode($newData);
            }));

        $this->service->logCreate('users', 1, $newData, 99, $this->mockRequest);
    }

    // ==================== LOG UPDATE TESTS ====================

    public function testLogUpdateOnlyLogsIfValuesChanged(): void
    {
        $oldValues = ['email' => 'same@example.com'];
        $newValues = ['email' => 'same@example.com'];

        // insert should NOT be called when values are the same
        $this->mockAuditLogModel
            ->expects($this->never())
            ->method('insert');

        $this->service->logUpdate('users', 1, $oldValues, $newValues, 99, $this->mockRequest);
    }

    public function testLogUpdateLogsWhenValuesAreDifferent(): void
    {
        $oldValues = ['email' => 'old@example.com'];
        $newValues = ['email' => 'new@example.com'];

        $this->mockAuditLogModel
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                return $data['action'] === 'update';
            }));

        $this->service->logUpdate('users', 1, $oldValues, $newValues, 99, $this->mockRequest);
    }

    public function testLogUpdateDoesNotLogWhenOnlySensitiveValuesChanged(): void
    {
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

        $this->service->logUpdate('users', 1, $oldValues, $newValues, 99, $this->mockRequest);
    }

    // ==================== LOG DELETE TESTS ====================

    public function testLogDeleteLogsWithEmptyNewValues(): void
    {
        $oldData = ['first_name' => 'Deleted', 'email' => 'deleted@example.com'];

        $this->mockAuditLogModel
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) use ($oldData) {
                return $data['action'] === 'delete'
                    && $data['old_values'] === json_encode($oldData)
                    && $data['new_values'] === null;
            }));

        $this->service->logDelete('users', 1, $oldData, 99, $this->mockRequest);
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

        $result = $this->service->show(['id' => 1]);

        $this->assertInstanceOf(\App\DTO\Response\Audit\AuditResponseDTO::class, $result);
        $data = $result->toArray();
        $this->assertEquals(1, $data['id']);
        $this->assertEquals('create', $data['action']);
    }

    public function testShowWithoutIdThrowsException(): void
    {
        // validateRequiredId from trait throws BadRequestException
        $this->expectException(\App\Exceptions\BadRequestException::class);

        $this->service->show([]);
    }

    public function testShowWithNonExistentIdThrowsNotFoundException(): void
    {
        $this->mockAuditLogModel
            ->method('find')
            ->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->show(['id' => 999]);
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

        $result = $this->service->byEntity([
            'entity_type' => 'users',
            'entity_id' => 5,
        ]);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(\App\DTO\Response\Audit\AuditResponseDTO::class, $result[0]);
    }

    public function testByEntityNormalizesSingularEntityType(): void
    {
        $this->mockAuditLogModel
            ->expects($this->once())
            ->method('getByEntity')
            ->with('users', 5)
            ->willReturn([]);

        $result = $this->service->byEntity([
            'entity_type' => 'user',
            'entity_id' => 5,
        ]);

        $this->assertSame([], $result);
    }

    public function testByEntityWithMissingParamsThrowsException(): void
    {
        $this->expectException(\App\Exceptions\BadRequestException::class);

        $this->service->byEntity(['entity_type' => 'users']);
    }

    // ==================== HELPER METHODS ====================

    private function createAuditLogEntity(array $data): \App\Entities\AuditLogEntity
    {
        return new \App\Entities\AuditLogEntity($data);
    }
}
