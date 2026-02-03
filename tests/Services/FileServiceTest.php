<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Libraries\Storage\StorageManager;
use App\Models\FileModel;
use App\Services\FileService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * FileService Integration Tests
 *
 * Tests the complete file management flow with real database operations.
 * Includes upload, download, delete, and validation with mocked storage.
 */
class FileServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected FileService $service;
    protected FileModel $model;
    protected StorageManager $mockStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new FileModel();
        $this->mockStorage = $this->createMock(StorageManager::class);

        // Configure mock storage default behavior
        $this->mockStorage->method('getDriverName')->willReturn('local');
        $this->mockStorage->method('url')->willReturnCallback(
            fn ($path) => "http://localhost/storage/{$path}"
        );

        $this->service = new FileService($this->model, $this->mockStorage);

        $this->seedDatabase();
    }

    protected function seedDatabase(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call('Tests\Support\Database\Seeds\TestUserSeeder');
    }

    // ==================== UPLOAD INTEGRATION TESTS ====================

    public function testUploadCreatesFileRecord(): void
    {
        $mockFile = $this->createMockUploadedFile('test.pdf', 'application/pdf', 1024, 'pdf');

        $this->mockStorage->expects($this->once())
            ->method('put')
            ->willReturn(true);

        $result = $this->service->upload([
            'file' => $mockFile,
            'user_id' => 1,
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(201, $result['code']);
        $this->assertArrayHasKey('id', $result['data']);
    }

    public function testUploadStoresFileInStorage(): void
    {
        $mockFile = $this->createMockUploadedFile('document.pdf', 'application/pdf', 2048, 'pdf');

        $this->mockStorage->expects($this->once())
            ->method('put')
            ->with(
                $this->matchesRegularExpression('/^\d{4}\/\d{2}\/\d{2}\/.+\.pdf$/'),
                $this->anything()
            )
            ->willReturn(true);

        $result = $this->service->upload([
            'file' => $mockFile,
            'user_id' => 1,
        ]);

        $this->assertEquals('success', $result['status']);
    }

    public function testUploadRollbacksOnDatabaseError(): void
    {
        // Create a file that will fail validation (missing required field)
        $mockFile = $this->createMockUploadedFile('', 'application/pdf', 1024, 'pdf');
        $mockFile->method('getName')->willReturn(''); // Empty name will fail

        $this->mockStorage->expects($this->once())
            ->method('put')
            ->willReturn(true);

        $this->mockStorage->expects($this->once())
            ->method('delete')
            ->with($this->anything());

        $result = $this->service->upload([
            'file' => $mockFile,
            'user_id' => 1,
        ]);

        $this->assertEquals('error', $result['status']);
    }

    public function testUploadFailsWhenStorageFails(): void
    {
        $mockFile = $this->createMockUploadedFile('test.pdf', 'application/pdf', 1024, 'pdf');

        $this->mockStorage->expects($this->once())
            ->method('put')
            ->willReturn(false);

        $result = $this->service->upload([
            'file' => $mockFile,
            'user_id' => 1,
        ]);

        $this->assertEquals('error', $result['status']);

        // No file should be in database
        $files = $this->model->getByUser(1);
        $this->assertEmpty($files);
    }

    public function testUploadValidatesFileSize(): void
    {
        putenv('FILE_MAX_SIZE=1024'); // 1KB

        $mockFile = $this->createMockUploadedFile('large.pdf', 'application/pdf', 2048, 'pdf');

        $result = $this->service->upload([
            'file' => $mockFile,
            'user_id' => 1,
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('file', $result['errors']);

        putenv('FILE_MAX_SIZE'); // Clear
    }

    public function testUploadValidatesFileType(): void
    {
        putenv('FILE_ALLOWED_TYPES=pdf,jpg');

        $mockFile = $this->createMockUploadedFile('script.exe', 'application/x-msdownload', 1024, 'exe');

        $result = $this->service->upload([
            'file' => $mockFile,
            'user_id' => 1,
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('file', $result['errors']);

        putenv('FILE_ALLOWED_TYPES'); // Clear
    }

    public function testUploadGeneratesUniqueFilename(): void
    {
        $mockFile1 = $this->createMockUploadedFile('test.pdf', 'application/pdf', 1024, 'pdf');
        $mockFile2 = $this->createMockUploadedFile('test.pdf', 'application/pdf', 1024, 'pdf');

        $this->mockStorage->method('put')->willReturn(true);

        $result1 = $this->service->upload(['file' => $mockFile1, 'user_id' => 1]);
        $result2 = $this->service->upload(['file' => $mockFile2, 'user_id' => 1]);

        $this->assertNotEquals($result1['data']['id'], $result2['data']['id']);

        // Get files and verify stored names are different
        $file1 = $this->model->find($result1['data']['id']);
        $file2 = $this->model->find($result2['data']['id']);

        $this->assertNotEquals($file1->stored_name, $file2->stored_name);
    }

    // ==================== INDEX INTEGRATION TESTS ====================

    public function testIndexReturnsUserFiles(): void
    {
        // Upload some files
        $this->mockStorage->method('put')->willReturn(true);

        $mockFile1 = $this->createMockUploadedFile('file1.pdf', 'application/pdf', 1024, 'pdf');
        $mockFile2 = $this->createMockUploadedFile('file2.pdf', 'application/pdf', 2048, 'pdf');

        $this->service->upload(['file' => $mockFile1, 'user_id' => 1]);
        $this->service->upload(['file' => $mockFile2, 'user_id' => 1]);

        $result = $this->service->index(['user_id' => 1]);

        $this->assertEquals('success', $result['status']);
        $this->assertCount(2, $result['data']);
    }

    public function testIndexReturnsEmptyForUserWithNoFiles(): void
    {
        $result = $this->service->index(['user_id' => 999]);

        $this->assertEquals('success', $result['status']);
        $this->assertEmpty($result['data']);
    }

    public function testIndexOnlyReturnsOwnFiles(): void
    {
        $this->mockStorage->method('put')->willReturn(true);

        // User 1 uploads files
        $file1 = $this->createMockUploadedFile('user1.pdf', 'application/pdf', 1024, 'pdf');
        $this->service->upload(['file' => $file1, 'user_id' => 1]);

        // User 2 uploads files
        $file2 = $this->createMockUploadedFile('user2.pdf', 'application/pdf', 1024, 'pdf');
        $this->service->upload(['file' => $file2, 'user_id' => 2]);

        // User 1 should only see their file
        $result = $this->service->index(['user_id' => 1]);

        $this->assertEquals('success', $result['status']);
        $this->assertCount(1, $result['data']);
        $this->assertEquals('user1.pdf', $result['data'][0]['original_name']);
    }

    public function testIndexIncludesFileMetadata(): void
    {
        $this->mockStorage->method('put')->willReturn(true);

        $mockFile = $this->createMockUploadedFile('test.pdf', 'application/pdf', 1024000, 'pdf');
        $this->service->upload(['file' => $mockFile, 'user_id' => 1]);

        $result = $this->service->index(['user_id' => 1]);

        $file = $result['data'][0];

        $this->assertArrayHasKey('id', $file);
        $this->assertArrayHasKey('original_name', $file);
        $this->assertArrayHasKey('size', $file);
        $this->assertArrayHasKey('human_size', $file);
        $this->assertArrayHasKey('mime_type', $file);
        $this->assertArrayHasKey('url', $file);
        $this->assertArrayHasKey('uploaded_at', $file);
        $this->assertArrayHasKey('is_image', $file);
    }

    // ==================== DOWNLOAD INTEGRATION TESTS ====================

    public function testDownloadReturnsFileInfo(): void
    {
        $this->mockStorage->method('put')->willReturn(true);

        $mockFile = $this->createMockUploadedFile('download.pdf', 'application/pdf', 1024, 'pdf');
        $uploadResult = $this->service->upload(['file' => $mockFile, 'user_id' => 1]);

        $result = $this->service->download([
            'id' => $uploadResult['data']['id'],
            'user_id' => 1,
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('url', $result['data']);
        $this->assertArrayHasKey('path', $result['data']);
        $this->assertArrayHasKey('storage_driver', $result['data']);
    }

    public function testDownloadEnforcesOwnership(): void
    {
        $this->mockStorage->method('put')->willReturn(true);

        // User 1 uploads file
        $mockFile = $this->createMockUploadedFile('private.pdf', 'application/pdf', 1024, 'pdf');
        $uploadResult = $this->service->upload(['file' => $mockFile, 'user_id' => 1]);

        // User 2 tries to download
        $result = $this->service->download([
            'id' => $uploadResult['data']['id'],
            'user_id' => 2,
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(404, $result['code']);
    }

    // ==================== DELETE INTEGRATION TESTS ====================

    public function testDeleteRemovesFileFromDatabase(): void
    {
        $this->mockStorage->method('put')->willReturn(true);
        $this->mockStorage->method('delete')->willReturn(true);

        $mockFile = $this->createMockUploadedFile('todelete.pdf', 'application/pdf', 1024, 'pdf');
        $uploadResult = $this->service->upload(['file' => $mockFile, 'user_id' => 1]);

        $fileId = $uploadResult['data']['id'];

        $result = $this->service->delete([
            'id' => $fileId,
            'user_id' => 1,
        ]);

        $this->assertEquals('success', $result['status']);

        // Verify file is deleted
        $deletedFile = $this->model->find($fileId);
        $this->assertNull($deletedFile);
    }

    public function testDeleteRemovesFileFromStorage(): void
    {
        $this->mockStorage->method('put')->willReturn(true);

        $mockFile = $this->createMockUploadedFile('storage.pdf', 'application/pdf', 1024, 'pdf');
        $uploadResult = $this->service->upload(['file' => $mockFile, 'user_id' => 1]);

        $fileId = $uploadResult['data']['id'];
        $file = $this->model->find($fileId);

        $this->mockStorage->expects($this->once())
            ->method('delete')
            ->with($file->path)
            ->willReturn(true);

        $this->service->delete([
            'id' => $fileId,
            'user_id' => 1,
        ]);
    }

    public function testDeleteEnforcesOwnership(): void
    {
        $this->mockStorage->method('put')->willReturn(true);

        // User 1 uploads file
        $mockFile = $this->createMockUploadedFile('protected.pdf', 'application/pdf', 1024, 'pdf');
        $uploadResult = $this->service->upload(['file' => $mockFile, 'user_id' => 1]);

        // User 2 tries to delete
        $result = $this->service->delete([
            'id' => $uploadResult['data']['id'],
            'user_id' => 2,
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(404, $result['code']);

        // File should still exist
        $file = $this->model->find($uploadResult['data']['id']);
        $this->assertNotNull($file);
    }

    public function testDeleteContinuesEvenIfStorageFailsis(): void
    {
        $this->mockStorage->method('put')->willReturn(true);

        $mockFile = $this->createMockUploadedFile('file.pdf', 'application/pdf', 1024, 'pdf');
        $uploadResult = $this->service->upload(['file' => $mockFile, 'user_id' => 1]);

        $fileId = $uploadResult['data']['id'];

        // Storage delete fails
        $this->mockStorage->expects($this->once())
            ->method('delete')
            ->willReturn(false);

        $result = $this->service->delete([
            'id' => $fileId,
            'user_id' => 1,
        ]);

        // Should still succeed (graceful degradation)
        $this->assertEquals('success', $result['status']);

        // File should be deleted from database
        $deletedFile = $this->model->find($fileId);
        $this->assertNull($deletedFile);
    }

    // ==================== SECURITY TESTS ====================

    public function testUploadSanitizesFilename(): void
    {
        $this->mockStorage->method('put')->willReturn(true);

        $mockFile = $this->createMockUploadedFile(
            '../../../etc/passwd',
            'text/plain',
            1024,
            'txt'
        );

        $result = $this->service->upload([
            'file' => $mockFile,
            'user_id' => 1,
        ]);

        // Should still fail on extension validation (txt not in default allowed)
        // but filename should be sanitized
        $this->assertEquals('error', $result['status']);
    }

    public function testMultipleUsersCannotAccessEachOthersFiles(): void
    {
        $this->mockStorage->method('put')->willReturn(true);

        $file1 = $this->createMockUploadedFile('user1.pdf', 'application/pdf', 1024, 'pdf');
        $file2 = $this->createMockUploadedFile('user2.pdf', 'application/pdf', 1024, 'pdf');

        $result1 = $this->service->upload(['file' => $file1, 'user_id' => 1]);
        $result2 = $this->service->upload(['file' => $file2, 'user_id' => 2]);

        // User 1 tries to access user 2's file
        $download = $this->service->download([
            'id' => $result2['data']['id'],
            'user_id' => 1,
        ]);

        $this->assertEquals('error', $download['status']);
        $this->assertEquals(404, $download['code']);
    }

    // ==================== HELPER METHOD ====================

    protected function createMockUploadedFile(
        string $name,
        string $mimeType,
        int $size,
        string $extension
    ): UploadedFile {
        $mock = $this->createMock(UploadedFile::class);

        $mock->method('isValid')->willReturn(true);
        $mock->method('getName')->willReturn($name);
        $mock->method('getMimeType')->willReturn($mimeType);
        $mock->method('getSize')->willReturn($size);
        $mock->method('getExtension')->willReturn($extension);
        $mock->method('getTempName')->willReturn('/tmp/phptest');
        $mock->method('getErrorString')->willReturn('');

        return $mock;
    }
}
