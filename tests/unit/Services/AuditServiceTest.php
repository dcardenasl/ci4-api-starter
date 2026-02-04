<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\NotFoundException;
use App\Models\AuditLogModel;
use App\Services\AuditService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * AuditService Unit Tests
 *
 * Comprehensive test coverage for audit logging operations.
 * Tests logging, retrieval, and filtering with mocked database.
 */
class AuditServiceTest extends CIUnitTestCase
{
    protected AuditService $service;
    protected AuditLogModel $mockModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockModel = $this->createMock(AuditLogModel::class);
        $this->service = new AuditService($this->mockModel);
    }

    // ==================== LOG TESTS ====================

    public function testLogCallsModelInsert(): void
    {
        $this->mockModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                return $data['action'] === 'create'
                    && $data['entity_type'] === 'user'
                    && $data['entity_id'] === 1;
            }));

        $this->service->log('create', 'user', 1, [], ['name' => 'Test'], 1);
    }

    public function testLogStoresJsonEncodedValues(): void
    {
        $oldValues = ['name' => 'Old Name'];
        $newValues = ['name' => 'New Name'];

        $this->mockModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) use ($oldValues, $newValues) {
                return $data['old_values'] === json_encode($oldValues)
                    && $data['new_values'] === json_encode($newValues);
            }));

        $this->service->log('update', 'user', 1, $oldValues, $newValues, 1);
    }

    public function testLogHandlesNullEntityId(): void
    {
        $this->mockModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                return $data['entity_id'] === null;
            }));

        $this->service->log('create', 'user', null, [], ['name' => 'Test'], 1);
    }

    public function testLogHandlesNullUserId(): void
    {
        $this->mockModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                return $data['user_id'] === null;
            }));

        $this->service->log('create', 'user', 1, [], ['name' => 'Test'], null);
    }

    public function testLogHandlesEmptyArrays(): void
    {
        $this->mockModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                return $data['old_values'] === null
                    && $data['new_values'] === null;
            }));

        $this->service->log('delete', 'user', 1, [], [], 1);
    }

    // ==================== LOG CREATE TESTS ====================

    public function testLogCreateCallsLogWithCorrectParameters(): void
    {
        $data = ['name' => 'John', 'email' => 'john@example.com'];

        $this->mockModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($insertData) use ($data) {
                return $insertData['action'] === 'create'
                    && $insertData['entity_type'] === 'user'
                    && $insertData['entity_id'] === 1
                    && $insertData['old_values'] === null
                    && $insertData['new_values'] === json_encode($data);
            }));

        $this->service->logCreate('user', 1, $data, 1);
    }

    // ==================== LOG UPDATE TESTS ====================

    public function testLogUpdateOnlyLogsWhenThereAreChanges(): void
    {
        $oldValues = ['name' => 'John'];
        $newValues = ['name' => 'Jane']; // Changed

        $this->mockModel->expects($this->once())
            ->method('insert');

        $this->service->logUpdate('user', 1, $oldValues, $newValues, 1);
    }

    public function testLogUpdateDoesNotLogWhenNoChanges(): void
    {
        $values = ['name' => 'John', 'email' => 'john@example.com'];

        $this->mockModel->expects($this->never())
            ->method('insert');

        $this->service->logUpdate('user', 1, $values, $values, 1);
    }

    public function testLogUpdateDetectsPartialChanges(): void
    {
        $oldValues = ['name' => 'John', 'email' => 'john@example.com', 'age' => 30];
        $newValues = ['name' => 'John', 'email' => 'newemail@example.com', 'age' => 30];

        $this->mockModel->expects($this->once())
            ->method('insert');

        $this->service->logUpdate('user', 1, $oldValues, $newValues, 1);
    }

    // ==================== LOG DELETE TESTS ====================

    public function testLogDeleteCallsLogWithCorrectParameters(): void
    {
        $data = ['name' => 'John', 'email' => 'john@example.com'];

        $this->mockModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($insertData) use ($data) {
                return $insertData['action'] === 'delete'
                    && $insertData['entity_type'] === 'user'
                    && $insertData['entity_id'] === 1
                    && $insertData['old_values'] === json_encode($data)
                    && $insertData['new_values'] === null;
            }));

        $this->service->logDelete('user', 1, $data, 1);
    }

    // ==================== SHOW TESTS ====================

    public function testShowRequiresId(): void
    {
        $result = $this->service->show([]);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('errors', $result);
    }

    public function testShowReturnsErrorForNonExistentLog(): void
    {
        $this->mockModel->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->show(['id' => 999]);
    }

    // ==================== BY ENTITY TESTS ====================

    public function testByEntityRequiresEntityType(): void
    {
        $result = $this->service->byEntity(['entity_id' => 1]);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('errors', $result);
    }

    public function testByEntityRequiresEntityId(): void
    {
        $result = $this->service->byEntity(['entity_type' => 'user']);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('errors', $result);
    }

    public function testByEntityCallsModelWithCorrectParameters(): void
    {
        $this->mockModel->expects($this->once())
            ->method('getByEntity')
            ->with('user', 1)
            ->willReturn([]);

        $this->service->byEntity(['entity_type' => 'user', 'entity_id' => 1]);
    }

    // ==================== EDGE CASES ====================

    public function testLogHandlesSpecialCharactersInValues(): void
    {
        $data = [
            'name' => 'Test "quotes" and \'apostrophes\'',
            'description' => 'Special chars: <>&"\'',
        ];

        $this->mockModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($insertData) {
                $decoded = json_decode($insertData['new_values'], true);
                return is_array($decoded);
            }));

        $this->service->logCreate('user', 1, $data, 1);
    }

    public function testLogHandlesLargeDataSets(): void
    {
        $largeData = [];
        for ($i = 0; $i < 100; $i++) {
            $largeData["field_{$i}"] = str_repeat('x', 100);
        }

        $this->mockModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                return strlen($data['new_values']) > 10000;
            }));

        $this->service->logCreate('user', 1, $largeData, 1);
    }

    public function testLogHandlesUnicodeCharacters(): void
    {
        $data = [
            'name' => 'José García',
            'description' => '中文字符 and émojis 🎉',
        ];

        $this->mockModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($insertData) {
                $decoded = json_decode($insertData['new_values'], true);
                return $decoded !== null;
            }));

        $this->service->logCreate('user', 1, $data, 1);
    }

    public function testLogUpdateHandlesEmptyArrays(): void
    {
        $this->mockModel->expects($this->never())
            ->method('insert');

        $this->service->logUpdate('user', 1, [], [], 1);
    }

    public function testByEntityHandlesStringEntityId(): void
    {
        $this->mockModel->expects($this->once())
            ->method('getByEntity')
            ->with('user', 123)
            ->willReturn([]);

        $this->service->byEntity(['entity_type' => 'user', 'entity_id' => '123']);
    }

    // ==================== RESPONSE FORMAT TESTS ====================

    public function testShowReturnsCorrectErrorFormat(): void
    {
        $result = $this->service->show([]);

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testByEntityReturnsCorrectErrorFormat(): void
    {
        $result = $this->service->byEntity([]);

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testByEntityReturnsSuccessWithEmptyArray(): void
    {
        $this->mockModel->expects($this->once())
            ->method('getByEntity')
            ->willReturn([]);

        $result = $this->service->byEntity(['entity_type' => 'user', 'entity_id' => 1]);

        $this->assertEquals('success', $result['status']);
        $this->assertIsArray($result['data']);
        $this->assertEmpty($result['data']);
    }
}
