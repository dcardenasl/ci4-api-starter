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
                    && $data['entity_type'] === 'user'
                    && $data['entity_id'] === 1
                    && $data['user_id'] === 99
                    && $data['ip_address'] === '127.0.0.1';
            }));

        $this->service->log(
            'create',
            'user',
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
            'user',
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

        $this->service->logCreate('user', 1, $newData, 99, $this->mockRequest);
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

        $this->service->logUpdate('user', 1, $oldValues, $newValues, 99, $this->mockRequest);
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

        $this->service->logUpdate('user', 1, $oldValues, $newValues, 99, $this->mockRequest);
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

        $this->service->logDelete('user', 1, $oldData, 99, $this->mockRequest);
    }

    // ==================== SHOW TESTS ====================

    public function testShowReturnsAuditLog(): void
    {
        $log = $this->createAuditLogEntity([
            'id' => 1,
            'user_id' => 99,
            'action' => 'create',
            'entity_type' => 'user',
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

        $this->assertSuccessResponse($result);
        $this->assertEquals(1, $result['data']['id']);
        $this->assertEquals('create', $result['data']['action']);
    }

    public function testShowWithoutIdThrowsException(): void
    {
        $this->expectException(BadRequestException::class);

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
                'entity_type' => 'user',
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
                'entity_type' => 'user',
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
            ->with('user', 5)
            ->willReturn($logs);

        $result = $this->service->byEntity([
            'entity_type' => 'user',
            'entity_id' => 5,
        ]);

        $this->assertSuccessResponse($result);
        $this->assertCount(2, $result['data']);
    }

    public function testByEntityWithMissingParamsThrowsException(): void
    {
        $this->expectException(BadRequestException::class);

        $this->service->byEntity(['entity_type' => 'user']);
    }

    // ==================== HELPER METHODS ====================

    private function createAuditLogEntity(array $data): \stdClass
    {
        $entity = new \stdClass();
        foreach ($data as $key => $value) {
            $entity->{$key} = $value;
        }
        return $entity;
    }
}
