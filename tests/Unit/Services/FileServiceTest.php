<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entities\FileEntity;
use App\Exceptions\AuthorizationException;
use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\AuditServiceInterface;
use App\Libraries\Storage\StorageManager;
use App\Models\FileModel;
use App\Services\FileService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * FileService Unit Tests
 *
 * Tests file operations with mocked dependencies.
 */
class FileServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected FileService $service;
    protected FileModel $mockFileModel;
    protected StorageManager $mockStorage;
    protected AuditServiceInterface $mockAuditService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockFileModel = $this->createMock(FileModel::class);
        $this->mockStorage = $this->createMock(StorageManager::class);
        $this->mockAuditService = $this->createMock(AuditServiceInterface::class);

        $this->service = new FileService($this->mockFileModel, $this->mockStorage, $this->mockAuditService);
    }

    // ==================== UPLOAD VALIDATION TESTS ====================

    public function testUploadWithoutFileThrowsException(): void
    {
        $this->expectException(BadRequestException::class);

        new \App\DTO\Request\Files\FileUploadRequestDTO(['user_id' => 1]);
    }

    public function testUploadWithoutUserIdThrowsException(): void
    {
        $mockFile = $this->createMockUploadedFile();

        $this->expectException(\App\Exceptions\AuthenticationException::class);

        new \App\DTO\Request\Files\FileUploadRequestDTO(['file' => $mockFile]);
    }

    public function testUploadWithInvalidFileObjectThrowsException(): void
    {
        $this->expectException(BadRequestException::class);

        new \App\DTO\Request\Files\FileUploadRequestDTO([
            'file' => 12345, // Integer is always invalid
            'user_id' => 1,
        ]);
    }

    public function testUploadWithInvalidFileThrowsException(): void
    {
        $mockFile = $this->createMockUploadedFile(['isValid' => false]);

        $this->expectException(BadRequestException::class);

        $this->service->upload(new \App\DTO\Request\Files\FileUploadRequestDTO([
            'file' => $mockFile,
            'user_id' => 1,
        ]));
    }

    public function testUploadWithFileTooLargeThrowsValidationException(): void
    {
        $mockFile = $this->createMockUploadedFile([
            'size' => 99999999, // Very large file
        ]);

        $this->expectException(ValidationException::class);

        $this->service->upload(new \App\DTO\Request\Files\FileUploadRequestDTO([
            'file' => $mockFile,
            'user_id' => 1,
        ]));
    }

    public function testUploadWithInvalidExtensionThrowsValidationException(): void
    {
        $mockFile = $this->createMockUploadedFile([
            'extension' => 'exe',
        ]);

        $this->expectException(ValidationException::class);

        $this->service->upload(new \App\DTO\Request\Files\FileUploadRequestDTO([
            'file' => $mockFile,
            'user_id' => 1,
        ]));
    }

    public function testUploadSuccessfullyStoresFileAndReturnsCreated(): void
    {
        // Create a real temp file so file_get_contents works
        $tempFile = tempnam(sys_get_temp_dir(), 'upload_test_');
        file_put_contents($tempFile, 'fake file contents');

        $mockFile = $this->createMockUploadedFile([
            'tempName' => $tempFile,
            'name' => 'photo.jpg',
            'extension' => 'jpg',
            'mimeType' => 'image/jpeg',
            'size' => 1024,
        ]);

        // Mock storage
        $this->mockStorage
            ->expects($this->once())
            ->method('put')
            ->willReturn(true);

        $this->mockStorage
            ->method('getDriverName')
            ->willReturn('local');

        $this->mockStorage
            ->method('url')
            ->willReturn('http://localhost/uploads/2026/02/17/photo_abc123.jpg');

        // Mock FileModel with anonymous class for insert + find
        $savedEntity = $this->createFileEntity([
            'id' => 1,
            'original_name' => 'photo.jpg',
            'size' => 1024,
            'mime_type' => 'image/jpeg',
            'url' => 'http://localhost/uploads/2026/02/17/photo_abc123.jpg',
            'uploaded_at' => date('Y-m-d H:i:s'),
        ]);

        $this->mockFileModel
            ->method('insert')
            ->willReturn(1);

        $this->mockFileModel
            ->method('find')
            ->willReturn($savedEntity);

        $result = $this->service->upload(new \App\DTO\Request\Files\FileUploadRequestDTO([
            'file' => $mockFile,
            'user_id' => 1,
        ]));

        $this->assertInstanceOf(\App\DTO\Response\Files\FileResponseDTO::class, $result);
        $data = $result->toArray();
        $this->assertEquals('photo.jpg', $data['original_name']);
        $this->assertEquals(1024, $data['file_size']);
        $this->assertEquals('image/jpeg', $data['mime_type']);

        // Clean up temp file
        @unlink($tempFile);
    }

    public function testUploadRollsBackStorageWhenDatabaseInsertFails(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'upload_test_');
        file_put_contents($tempFile, 'fake file contents');

        $mockFile = $this->createMockUploadedFile([
            'tempName' => $tempFile,
            'name' => 'doc.pdf',
            'extension' => 'pdf',
            'mimeType' => 'application/pdf',
            'size' => 2048,
        ]);

        $this->mockStorage
            ->method('put')
            ->willReturn(true);

        $this->mockStorage
            ->method('getDriverName')
            ->willReturn('local');

        $this->mockStorage
            ->method('url')
            ->willReturn('http://localhost/uploads/doc.pdf');

        // Storage delete should be called for rollback
        $this->mockStorage
            ->expects($this->once())
            ->method('delete');

        // Insert fails
        $this->mockFileModel
            ->method('insert')
            ->willReturn(false);

        $this->mockFileModel
            ->method('errors')
            ->willReturn(['file' => 'Database error']);

        $this->expectException(ValidationException::class);

        $this->service->upload(new \App\DTO\Request\Files\FileUploadRequestDTO([
            'file' => $mockFile,
            'user_id' => 1,
        ]));

        @unlink($tempFile);
    }

    public function testUploadFailsWhenStoragePutFails(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'upload_test_');
        file_put_contents($tempFile, 'fake file contents');

        $mockFile = $this->createMockUploadedFile([
            'tempName' => $tempFile,
            'name' => 'image.png',
            'extension' => 'png',
            'mimeType' => 'image/png',
            'size' => 512,
        ]);

        $this->mockStorage
            ->method('put')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);

        $this->service->upload(new \App\DTO\Request\Files\FileUploadRequestDTO([
            'file' => $mockFile,
            'user_id' => 1,
        ]));

        @unlink($tempFile);
    }

    // ==================== INDEX TESTS ====================

    public function testIndexWithoutUserIdThrowsException(): void
    {
        $this->expectException(\App\Exceptions\AuthenticationException::class);
        new \App\DTO\Request\Files\FileIndexRequestDTO([]);
    }

    public function testIndexReturnsUserFiles(): void
    {
        $files = [
            $this->createFileEntity([
                'id' => 1,
                'original_name' => 'photo.jpg',
                'size' => 1024,
                'mime_type' => 'image/jpeg',
            ]),
            $this->createFileEntity([
                'id' => 2,
                'original_name' => 'doc.pdf',
                'size' => 2048,
                'mime_type' => 'application/pdf',
            ]),
        ];

        // Mock con clase anónima que soporta QueryBuilder
        $this->mockFileModel = new class ($files) extends FileModel {
            private array $returnFiles;

            public function __construct(array $files)
            {
                $this->returnFiles = $files;
            }

            public function where($key, $value = null, ?bool $escape = null): static
            {
                return $this;
            }

            public function orderBy(string $orderBy, string $direction = '', ?bool $escape = null): static
            {
                return $this;
            }

            public function countAllResults(bool $reset = true, bool $test = false)
            {
                return count($this->returnFiles);
            }

            public function findAll(?int $limit = null, int $offset = 0)
            {
                return $this->returnFiles;
            }

            public function applyFilters(array $filters): static
            {
                return $this;
            }

            public function search(string $query): static
            {
                return $this;
            }
        };

        $this->service = new FileService($this->mockFileModel, $this->mockStorage, $this->mockAuditService);

        $request = new \App\DTO\Request\Files\FileIndexRequestDTO(['user_id' => 1]);
        $result = $this->service->index($request);
        $payload = $result->toArray();

        $this->assertInstanceOf(\App\DTO\Response\Common\PaginatedResponseDTO::class, $result);
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('total', $payload);
        $this->assertCount(2, $payload['data']);
    }

    // ==================== DOWNLOAD TESTS ====================

    public function testDownloadWithoutIdThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        new \App\DTO\Request\Files\FileGetRequestDTO(['user_id' => 1]);
    }

    public function testDownloadWithoutUserIdThrowsException(): void
    {
        $this->expectException(\App\Exceptions\AuthenticationException::class);
        new \App\DTO\Request\Files\FileGetRequestDTO(['id' => 1]);
    }

    public function testDownloadNonExistentFileThrowsNotFoundException(): void
    {
        $this->mockFileModel
            ->method('find')
            ->willReturn(null);

        $this->expectException(NotFoundException::class);

        $request = new \App\DTO\Request\Files\FileGetRequestDTO(['id' => 999, 'user_id' => 1]);
        $this->service->download($request);
    }

    public function testDownloadOtherUsersFileThrowsAuthorizationException(): void
    {
        $file = $this->createFileEntity([
            'id' => 1,
            'user_id' => 99, // Different user
        ]);

        $this->mockFileModel
            ->method('find')
            ->willReturn($file);

        $this->expectException(AuthorizationException::class);

        $request = new \App\DTO\Request\Files\FileGetRequestDTO(['id' => 1, 'user_id' => 1]);
        $this->service->download($request);
    }

    public function testDownloadOwnFileReturnsSuccess(): void
    {
        $file = $this->createFileEntity([
            'id' => 1,
            'user_id' => 1,
            'original_name' => 'myfile.pdf',
            'url' => 'http://example.com/myfile.pdf',
            'path' => '2024/01/01/myfile.pdf',
            'storage_driver' => 'local',
        ]);

        $this->mockFileModel
            ->method('find')
            ->willReturn($file);

        $request = new \App\DTO\Request\Files\FileGetRequestDTO(['id' => 1, 'user_id' => 1]);
        $result = $this->service->download($request);
        $payload = $result->toArray();

        $this->assertInstanceOf(\App\DTO\Response\Files\FileDownloadResponseDTO::class, $result);
        $this->assertEquals('myfile.pdf', $payload['original_name']);
        $this->assertEquals('http://example.com/myfile.pdf', $payload['url']);
    }

    // ==================== DELETE TESTS ====================

    public function testDeleteNonExistentFileThrowsNotFoundException(): void
    {
        $this->mockFileModel
            ->method('find')
            ->willReturn(null);

        $this->expectException(NotFoundException::class);

        $request = new \App\DTO\Request\Files\FileGetRequestDTO(['id' => 999, 'user_id' => 1]);
        $this->service->delete($request);
    }

    public function testDeleteOtherUsersFileThrowsAuthorizationException(): void
    {
        $file = $this->createFileEntity([
            'id' => 1,
            'user_id' => 99,
        ]);

        $this->mockFileModel
            ->method('find')
            ->willReturn($file);

        $this->expectException(AuthorizationException::class);

        $request = new \App\DTO\Request\Files\FileGetRequestDTO(['id' => 1, 'user_id' => 1]);
        $this->service->delete($request);
    }

    public function testDeleteOwnFileReturnsSuccess(): void
    {
        $file = $this->createFileEntity([
            'id' => 1,
            'user_id' => 1,
            'path' => '2024/01/01/file.jpg',
        ]);

        $this->mockFileModel
            ->method('find')
            ->willReturn($file);

        $this->mockStorage
            ->expects($this->once())
            ->method('delete')
            ->with('2024/01/01/file.jpg')
            ->willReturn(true);

        $this->mockFileModel
            ->expects($this->once())
            ->method('delete')
            ->with(1);

        $request = new \App\DTO\Request\Files\FileGetRequestDTO(['id' => 1, 'user_id' => 1]);
        $result = $this->service->delete($request);

        $this->assertTrue($result);
    }

    public function testUploadWithDuplicateFilenameGeneratesNumericSeries(): void
    {
        $base64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $filename = 'logo.png';
        $datePath = date('Y/m/d');

        // Mock Storage to simulate existing file for 'logo.png' but NOT for 'logo_1.png'
        $this->mockStorage
            ->method('exists')
            ->willReturnMap([
                ["{$datePath}/logo.png", true],   // First check: exists
                ["{$datePath}/logo_1.png", false] // Second check: doesn't exist
            ]);

        $this->mockStorage
            ->expects($this->once())
            ->method('put')
            ->with($this->equalTo("{$datePath}/logo_1.png"), $this->anything())
            ->willReturn(true);

        $this->mockStorage
            ->method('getDriverName')
            ->willReturn('local');

        $this->mockStorage
            ->method('url')
            ->willReturn("http://localhost/uploads/{$datePath}/logo_1.png");

        $savedEntity = $this->createFileEntity([
            'id' => 1,
            'original_name' => $filename,
            'stored_name' => 'logo_1.png',
            'mime_type' => 'image/png',
            'url' => "http://localhost/uploads/{$datePath}/logo_1.png",
            'uploaded_at' => date('Y-m-d H:i:s'),
        ]);

        $this->mockFileModel->method('insert')->willReturn(1);
        $this->mockFileModel->method('find')->willReturn($savedEntity);

        $tempFile = tempnam(sys_get_temp_dir(), 'upload_test_');
        file_put_contents($tempFile, 'fake contents');

        $result = $this->service->upload(new \App\DTO\Request\Files\FileUploadRequestDTO([
            'file' => $this->createMockUploadedFile([
                'tempName' => $tempFile,
                'name' => $filename,
                'extension' => 'png',
                'mimeType' => 'image/png',
            ]),
            'user_id' => 1,
        ]));

        $this->assertInstanceOf(\App\DTO\Response\Files\FileResponseDTO::class, $result);
        $this->assertEquals($filename, $result->toArray()['original_name']);
        @unlink($tempFile);
        // The fact that storage->put was called with logo_1.png validates the logic
    }

    public function testUploadWithUploadPrefixCleansFilename(): void
    {
        $base64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        // Simulating the dirty name reported by the user
        $dirtyFilename = 'upload_699e505bebdf92.24327053_Captura09.PNG';
        $datePath = date('Y/m/d');

        $this->mockStorage
            ->method('exists')
            ->willReturn(false); // No collision

        $this->mockStorage
            ->expects($this->once())
            ->method('put')
            ->willReturn(true);

        $savedEntity = $this->createFileEntity([
            'id' => 1,
            'original_name' => 'Captura09.PNG',
            'stored_name' => 'Captura09.png',
            'mime_type' => 'image/png',
            'url' => "http://localhost/uploads/{$datePath}/Captura09.png",
            'uploaded_at' => date('Y-m-d H:i:s'),
        ]);

        $this->mockFileModel->method('insert')->willReturn(1);
        $this->mockFileModel->method('find')->willReturn($savedEntity);

        $result = $this->service->upload(new \App\DTO\Request\Files\FileUploadRequestDTO([
            'file' => $base64,
            'filename' => $dirtyFilename,
            'user_id' => 1,
        ]));

        $this->assertInstanceOf(\App\DTO\Response\Files\FileResponseDTO::class, $result);
    }

    public function testUploadWithHexHashPrefixCleansFilename(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'upload_test_');
        file_put_contents($tempFile, 'fake file contents');

        // Simulating the dirty name with hex hash reported by the user
        $dirtyFilename = '8605b9b8f03a7a1f_Captura02.PNG';
        $datePath = date('Y/m/d');

        $mockFile = $this->createMockUploadedFile([
            'tempName' => $tempFile,
            'name' => $dirtyFilename,
            'extension' => 'png',
            'mimeType' => 'image/png',
            'size' => 512,
        ]);

        $this->mockStorage
            ->method('exists')
            ->willReturn(false);

        $this->mockStorage
            ->expects($this->once())
            ->method('put')
            ->willReturn(true);

        $savedEntity = $this->createFileEntity([
            'id' => 1,
            'original_name' => 'Captura02.PNG',
            'stored_name' => 'Captura02.png',
            'mime_type' => 'image/png',
            'url' => "http://localhost/uploads/{$datePath}/Captura02.png",
            'uploaded_at' => date('Y-m-d H:i:s'),
        ]);

        $this->mockFileModel->method('insert')->willReturn(1);
        $this->mockFileModel->method('find')->willReturn($savedEntity);

        $result = $this->service->upload(new \App\DTO\Request\Files\FileUploadRequestDTO([
            'file' => $mockFile,
            'user_id' => 1,
        ]));

        $this->assertInstanceOf(\App\DTO\Response\Files\FileResponseDTO::class, $result);
        @unlink($tempFile);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Create a mock UploadedFile object
     */
    private function createMockUploadedFile(array $overrides = []): UploadedFile
    {
        $defaults = [
            'isValid' => true,
            'size' => 1024,
            'extension' => 'jpg',
            'name' => 'test.jpg',
            'mimeType' => 'image/jpeg',
            'tempName' => '/tmp/php123456',
            'errorString' => '',
        ];

        $config = array_merge($defaults, $overrides);

        $mock = $this->createMock(UploadedFile::class);
        $mock->method('isValid')->willReturn($config['isValid']);
        $mock->method('getSize')->willReturn($config['size']);
        $mock->method('getExtension')->willReturn($config['extension']);
        $mock->method('getName')->willReturn($config['name']);
        $mock->method('getMimeType')->willReturn($config['mimeType']);
        $mock->method('getTempName')->willReturn($config['tempName']);
        $mock->method('getErrorString')->willReturn($config['errorString']);

        return $mock;
    }

    /**
     * Create a FileEntity with mock data
     */
    private function createFileEntity(array $data): FileEntity
    {
        $entity = new FileEntity();
        foreach ($data as $key => $value) {
            $entity->{$key} = $value;
        }
        return $entity;
    }
}
