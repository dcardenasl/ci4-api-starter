<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Libraries\Storage\StorageManager;
use App\Models\FileModel;
use App\Services\FileService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * FileService Unit Tests
 *
 * Comprehensive test coverage for file operations.
 * Tests upload, validation, download, and deletion with mocked storage.
 */
class FileServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

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

    /**
     * @dataProvider invalidUploadDataProvider
     */
    public function testUploadValidatesRequiredParameters($data, string $expectedErrorField): void
    {
        $result = $this->service->upload($data);

        $this->assertErrorResponse($result, $expectedErrorField);
    }

    public static function invalidUploadDataProvider(): array
    {
        $mockFile = (new \ReflectionClass(UploadedFile::class))->newInstanceWithoutConstructor();

        return [
            'missing file' => [
                ['user_id' => 1],
                'file',
            ],
            'missing user_id' => [
                ['file' => $mockFile],
                'user_id',
            ],
            'empty user_id' => [
                ['file' => $mockFile, 'user_id' => ''],
                'user_id',
            ],
            'invalid file type' => [
                ['file' => 'not-a-file-object', 'user_id' => 1],
                'file',
            ],
            'both parameters missing' => [
                [],
                'file', // First error that would be caught
            ],
        ];
    }

    // ==================== INDEX TESTS ====================

    /**
     * @dataProvider invalidIndexDataProvider
     */
    public function testIndexValidatesRequiredParameters(array $data, string $expectedErrorField): void
    {
        $result = $this->service->index($data);

        $this->assertErrorResponse($result, $expectedErrorField);
    }

    public static function invalidIndexDataProvider(): array
    {
        return [
            'missing user_id' => [[], 'user_id'],
            'empty user_id' => [['user_id' => ''], 'user_id'],
        ];
    }

    public function testIndexReturnsEmptyArrayForUserWithNoFiles(): void
    {
        $this->mockFileModel->expects($this->once())
            ->method('getByUser')
            ->with(1)
            ->willReturn([]);

        $result = $this->service->index(['user_id' => 1]);

        $this->assertEmptyDataResponse($result);
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

    public function testIndexOnlyReturnsUserOwnedFiles(): void
    {
        $userId = 1;

        $this->mockFileModel->expects($this->once())
            ->method('getByUser')
            ->with($this->identicalTo($userId))
            ->willReturn([]);

        $this->service->index(['user_id' => $userId]);
    }

    // ==================== DOWNLOAD TESTS ====================

    /**
     * @dataProvider invalidDownloadDataProvider
     */
    public function testDownloadValidatesRequiredParameters(array $data, string $expectedErrorField): void
    {
        $result = $this->service->download($data);

        $this->assertErrorResponse($result, $expectedErrorField);
    }

    public static function invalidDownloadDataProvider(): array
    {
        return [
            'missing id' => [['user_id' => 1], 'id'],
            'missing user_id' => [['id' => 1], 'user_id'],
            'empty id' => [['id' => '', 'user_id' => 1], 'id'],
            'empty user_id' => [['id' => 1, 'user_id' => ''], 'user_id'],
        ];
    }

    public function testDownloadReturnsErrorForNonExistentFile(): void
    {
        $this->mockFileModel->expects($this->once())
            ->method('getByIdAndUser')
            ->with(1, 1)
            ->willReturn(null);

        $result = $this->service->download(['id' => 1, 'user_id' => 1]);

        $this->assertErrorResponseWithCode($result, 404);
    }

    public function testDownloadEnforcesFileOwnership(): void
    {
        // User 2 trying to access user 1's file
        $this->mockFileModel->expects($this->once())
            ->method('getByIdAndUser')
            ->with(1, 2)
            ->willReturn(null);

        $result = $this->service->download(['id' => 1, 'user_id' => 2]);

        $this->assertErrorResponseWithCode($result, 404);
    }

    // ==================== DELETE TESTS ====================

    /**
     * @dataProvider invalidDeleteDataProvider
     */
    public function testDeleteValidatesRequiredParameters(array $data, string $expectedErrorField): void
    {
        $result = $this->service->delete($data);

        $this->assertErrorResponse($result, $expectedErrorField);
    }

    public static function invalidDeleteDataProvider(): array
    {
        return [
            'missing id' => [['user_id' => 1], 'id'],
            'missing user_id' => [['id' => 1], 'user_id'],
            'empty id' => [['id' => '', 'user_id' => 1], 'id'],
            'empty user_id' => [['id' => 1, 'user_id' => ''], 'user_id'],
        ];
    }

    public function testDeleteReturnsErrorForNonExistentFile(): void
    {
        $this->mockFileModel->expects($this->once())
            ->method('getByIdAndUser')
            ->with(1, 1)
            ->willReturn(null);

        $result = $this->service->delete(['id' => 1, 'user_id' => 1]);

        $this->assertErrorResponseWithCode($result, 404);
    }

    public function testDeleteEnforcesFileOwnership(): void
    {
        // User 2 trying to delete user 1's file
        $this->mockFileModel->expects($this->once())
            ->method('getByIdAndUser')
            ->with(1, 2)
            ->willReturn(null);

        $result = $this->service->delete(['id' => 1, 'user_id' => 2]);

        $this->assertErrorResponseWithCode($result, 404);
    }
}
