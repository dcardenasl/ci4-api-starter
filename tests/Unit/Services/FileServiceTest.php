<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entities\FileEntity;
use App\Exceptions\AuthorizationException;
use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockFileModel = $this->createMock(FileModel::class);
        $this->mockStorage = $this->createMock(StorageManager::class);

        $this->service = new FileService($this->mockFileModel, $this->mockStorage);
    }

    // ==================== UPLOAD VALIDATION TESTS ====================

    public function testUploadWithoutFileThrowsException(): void
    {
        $this->expectException(BadRequestException::class);

        $this->service->upload(['user_id' => 1]);
    }

    public function testUploadWithoutUserIdThrowsException(): void
    {
        $mockFile = $this->createMockUploadedFile();

        $this->expectException(BadRequestException::class);

        $this->service->upload(['file' => $mockFile]);
    }

    public function testUploadWithInvalidFileObjectThrowsException(): void
    {
        $this->expectException(BadRequestException::class);

        $this->service->upload([
            'file' => 'not-a-file-object',
            'user_id' => 1,
        ]);
    }

    public function testUploadWithInvalidFileThrowsException(): void
    {
        $mockFile = $this->createMockUploadedFile(['isValid' => false]);

        $this->expectException(BadRequestException::class);

        $this->service->upload([
            'file' => $mockFile,
            'user_id' => 1,
        ]);
    }

    public function testUploadWithFileTooLargeThrowsValidationException(): void
    {
        $mockFile = $this->createMockUploadedFile([
            'size' => 99999999, // Very large file
        ]);

        $this->expectException(ValidationException::class);

        $this->service->upload([
            'file' => $mockFile,
            'user_id' => 1,
        ]);
    }

    public function testUploadWithInvalidExtensionThrowsValidationException(): void
    {
        $mockFile = $this->createMockUploadedFile([
            'extension' => 'exe',
        ]);

        $this->expectException(ValidationException::class);

        $this->service->upload([
            'file' => $mockFile,
            'user_id' => 1,
        ]);
    }

    /**
     * Note: Full upload tests require real file system access.
     * These are better suited for integration tests.
     * The validation tests above cover the important error paths.
     */

    // ==================== INDEX TESTS ====================

    public function testIndexWithoutUserIdThrowsException(): void
    {
        $this->expectException(BadRequestException::class);

        $this->service->index([]);
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

        $this->service = new FileService($this->mockFileModel, $this->mockStorage);

        $result = $this->service->index(['user_id' => 1]);

        $this->assertPaginatedResponse($result);
        $this->assertCount(2, $result['data']);
    }

    // ==================== DOWNLOAD TESTS ====================

    public function testDownloadWithoutIdThrowsException(): void
    {
        $this->expectException(BadRequestException::class);

        $this->service->download(['user_id' => 1]);
    }

    public function testDownloadWithoutUserIdThrowsException(): void
    {
        $this->expectException(BadRequestException::class);

        $this->service->download(['id' => 1]);
    }

    public function testDownloadNonExistentFileThrowsNotFoundException(): void
    {
        $this->mockFileModel
            ->method('find')
            ->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->download(['id' => 999, 'user_id' => 1]);
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

        $this->service->download(['id' => 1, 'user_id' => 1]);
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

        $result = $this->service->download(['id' => 1, 'user_id' => 1]);

        $this->assertSuccessResponse($result);
        $this->assertEquals('myfile.pdf', $result['data']['original_name']);
    }

    // ==================== DELETE TESTS ====================

    public function testDeleteNonExistentFileThrowsNotFoundException(): void
    {
        $this->mockFileModel
            ->method('find')
            ->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->delete(['id' => 999, 'user_id' => 1]);
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

        $this->service->delete(['id' => 1, 'user_id' => 1]);
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

        $result = $this->service->delete(['id' => 1, 'user_id' => 1]);

        $this->assertSuccessResponse($result);
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
