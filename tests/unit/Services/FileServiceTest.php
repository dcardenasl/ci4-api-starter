<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Libraries\Storage\StorageManager;
use App\Models\FileModel;
use App\Services\FileService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * FileService Unit Tests
 *
 * Comprehensive test coverage for file operations.
 * Tests upload, validation, download, and deletion with mocked storage.
 */
class FileServiceTest extends CIUnitTestCase
{
    protected FileService $service;
    protected FileModel $mockFileModel;
    protected StorageManager $mockStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockFileModel = $this->createMock(FileModel::class);
        $this->mockStorage = $this->createMock(StorageManager::class);

        $this->service = new FileService($this->mockFileModel, $this->mockStorage);
    }

    // ==================== UPLOAD TESTS ====================

    public function testUploadRequiresFile(): void
    {
        $result = $this->service->upload(['user_id' => 1]);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('file', $result['errors']);
    }

    public function testUploadRequiresUserId(): void
    {
        $mockFile = $this->createMock(UploadedFile::class);

        $result = $this->service->upload(['file' => $mockFile]);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('errors', $result);
    }

    public function testUploadValidatesFileObject(): void
    {
        $result = $this->service->upload([
            'file' => 'not-a-file-object',
            'user_id' => 1,
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('file', $result['errors']);
    }

    public function testUploadRequiresBothParameters(): void
    {
        $result = $this->service->upload([]);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('errors', $result);
    }

    // ==================== INDEX TESTS ====================

    public function testIndexRequiresUserId(): void
    {
        $result = $this->service->index([]);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('user_id', $result['errors']);
    }

    public function testIndexReturnsEmptyArrayForUserWithNoFiles(): void
    {
        $this->mockFileModel->expects($this->once())
            ->method('getByUser')
            ->with(1)
            ->willReturn([]);

        $result = $this->service->index(['user_id' => 1]);

        $this->assertEquals('success', $result['status']);
        $this->assertIsArray($result['data']);
        $this->assertEmpty($result['data']);
    }

    public function testIndexCallsModelWithCorrectUserId(): void
    {
        $userId = 42;

        $this->mockFileModel->expects($this->once())
            ->method('getByUser')
            ->with($this->identicalTo($userId))
            ->willReturn([]);

        $this->service->index(['user_id' => $userId]);
    }

    // ==================== DOWNLOAD TESTS ====================

    public function testDownloadRequiresId(): void
    {
        $result = $this->service->download(['user_id' => 1]);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('errors', $result);
    }

    public function testDownloadRequiresUserId(): void
    {
        $result = $this->service->download(['id' => 1]);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('errors', $result);
    }

    public function testDownloadReturnErrorForNonExistentFile(): void
    {
        $this->mockFileModel->expects($this->once())
            ->method('getByIdAndUser')
            ->with(1, 1)
            ->willReturn(null);

        $result = $this->service->download(['id' => 1, 'user_id' => 1]);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(404, $result['code']);
    }

    // ==================== DELETE TESTS ====================

    public function testDeleteRequiresId(): void
    {
        $result = $this->service->delete(['user_id' => 1]);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('errors', $result);
    }

    public function testDeleteRequiresUserId(): void
    {
        $result = $this->service->delete(['id' => 1]);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('errors', $result);
    }

    public function testDeleteReturnErrorForNonExistentFile(): void
    {
        $this->mockFileModel->expects($this->once())
            ->method('getByIdAndUser')
            ->with(1, 1)
            ->willReturn(null);

        $result = $this->service->delete(['id' => 1, 'user_id' => 1]);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(404, $result['code']);
    }

    // ==================== VALIDATION TESTS ====================

    public function testUploadValidatesEmptyUserId(): void
    {
        $mockFile = $this->createMock(UploadedFile::class);

        $result = $this->service->upload([
            'file' => $mockFile,
            'user_id' => '',
        ]);

        $this->assertEquals('error', $result['status']);
    }

    public function testIndexValidatesEmptyUserId(): void
    {
        $result = $this->service->index(['user_id' => '']);

        $this->assertEquals('error', $result['status']);
    }

    public function testDownloadValidatesEmptyParameters(): void
    {
        $result = $this->service->download(['id' => '', 'user_id' => '']);

        $this->assertEquals('error', $result['status']);
    }

    public function testDeleteValidatesEmptyParameters(): void
    {
        $result = $this->service->delete(['id' => '', 'user_id' => '']);

        $this->assertEquals('error', $result['status']);
    }

    // ==================== EDGE CASES ====================

    public function testUploadHandlesZeroUserId(): void
    {
        $mockFile = $this->createMock(UploadedFile::class);

        $result = $this->service->upload([
            'file' => $mockFile,
            'user_id' => 0,
        ]);

        // Should fail validation (0 is considered empty)
        $this->assertEquals('error', $result['status']);
    }

    public function testIndexHandlesLargeUserId(): void
    {
        $largeId = PHP_INT_MAX;

        $this->mockFileModel->expects($this->once())
            ->method('getByUser')
            ->with($largeId)
            ->willReturn([]);

        $result = $this->service->index(['user_id' => $largeId]);

        $this->assertEquals('success', $result['status']);
    }

    public function testDownloadHandlesStringIds(): void
    {
        $this->mockFileModel->expects($this->once())
            ->method('getByIdAndUser')
            ->with(123, 456)
            ->willReturn(null);

        $result = $this->service->download(['id' => '123', 'user_id' => '456']);

        $this->assertEquals('error', $result['status']);
    }

    // ==================== RESPONSE FORMAT TESTS ====================

    public function testUploadReturnsCorrectErrorFormat(): void
    {
        $result = $this->service->upload([]);

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function testIndexReturnsCorrectSuccessFormat(): void
    {
        $this->mockFileModel->expects($this->once())
            ->method('getByUser')
            ->willReturn([]);

        $result = $this->service->index(['user_id' => 1]);

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertIsArray($result['data']);
    }

    public function testDownloadReturnsCorrectErrorFormat(): void
    {
        $result = $this->service->download([]);

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('errors', $result);
    }

    public function testDeleteReturnsCorrectErrorFormat(): void
    {
        $result = $this->service->delete([]);

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('errors', $result);
    }

    // ==================== SECURITY TESTS ====================

    public function testDownloadEnforcesOwnership(): void
    {
        // User 2 trying to access user 1's file
        $this->mockFileModel->expects($this->once())
            ->method('getByIdAndUser')
            ->with(1, 2)
            ->willReturn(null);

        $result = $this->service->download(['id' => 1, 'user_id' => 2]);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(404, $result['code']);
    }

    public function testDeleteEnforcesOwnership(): void
    {
        // User 2 trying to delete user 1's file
        $this->mockFileModel->expects($this->once())
            ->method('getByIdAndUser')
            ->with(1, 2)
            ->willReturn(null);

        $result = $this->service->delete(['id' => 1, 'user_id' => 2]);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(404, $result['code']);
    }

    public function testIndexOnlyReturnsUserFiles(): void
    {
        $this->mockFileModel->expects($this->once())
            ->method('getByUser')
            ->with($this->identicalTo(1))
            ->willReturn([]);

        $this->service->index(['user_id' => 1]);

        // Verify only user 1's files are requested
        $this->assertTrue(true);
    }
}
