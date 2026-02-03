<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\FileModel;
use Tests\Support\DatabaseTestCase;

/**
 * FileModel Integration Tests
 *
 * Tests database operations for file metadata including
 * creation, retrieval, ownership checks, and deletion.
 */
class FileModelTest extends DatabaseTestCase
{
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected FileModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new FileModel();
        $this->seedDatabase();
    }

    protected function seedDatabase(): void
    {
        // Create test users
        $seeder = \Config\Database::seeder();
        $seeder->call('Tests\Support\Database\Seeds\TestUserSeeder');

        // Insert test files
        $this->db->table('files')->insertBatch([
            [
                'user_id' => 1,
                'original_name' => 'document.pdf',
                'stored_name' => 'document_abc123.pdf',
                'mime_type' => 'application/pdf',
                'size' => 1024000,
                'storage_driver' => 'local',
                'path' => '2026/01/15/document_abc123.pdf',
                'url' => 'http://localhost/storage/2026/01/15/document_abc123.pdf',
                'metadata' => '{"extension":"pdf","uploaded_by":1}',
                'uploaded_at' => date('Y-m-d H:i:s'),
            ],
            [
                'user_id' => 1,
                'original_name' => 'image.jpg',
                'stored_name' => 'image_def456.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 512000,
                'storage_driver' => 'local',
                'path' => '2026/01/16/image_def456.jpg',
                'url' => 'http://localhost/storage/2026/01/16/image_def456.jpg',
                'metadata' => '{"extension":"jpg","uploaded_by":1}',
                'uploaded_at' => date('Y-m-d H:i:s'),
            ],
            [
                'user_id' => 2,
                'original_name' => 'user2file.txt',
                'stored_name' => 'user2file_ghi789.txt',
                'mime_type' => 'text/plain',
                'size' => 256000,
                'storage_driver' => 'local',
                'path' => '2026/01/17/user2file_ghi789.txt',
                'url' => 'http://localhost/storage/2026/01/17/user2file_ghi789.txt',
                'metadata' => '{"extension":"txt","uploaded_by":2}',
                'uploaded_at' => date('Y-m-d H:i:s', time() - 3600),
            ],
        ]);
    }

    // ==================== VALIDATION TESTS ====================

    public function testValidationRequiresUserId(): void
    {
        $data = [
            'original_name' => 'test.pdf',
            'stored_name' => 'test_123.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'storage_driver' => 'local',
            'path' => '2026/01/test_123.pdf',
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $errors = $this->model->errors();
        $this->assertArrayHasKey('user_id', $errors);
    }

    public function testValidationRequiresOriginalName(): void
    {
        $data = [
            'user_id' => 1,
            'stored_name' => 'test_123.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'storage_driver' => 'local',
            'path' => '2026/01/test_123.pdf',
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $errors = $this->model->errors();
        $this->assertArrayHasKey('original_name', $errors);
    }

    public function testValidationRequiresStoredName(): void
    {
        $data = [
            'user_id' => 1,
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'storage_driver' => 'local',
            'path' => '2026/01/test_123.pdf',
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $errors = $this->model->errors();
        $this->assertArrayHasKey('stored_name', $errors);
    }

    public function testValidationRequiresMimeType(): void
    {
        $data = [
            'user_id' => 1,
            'original_name' => 'test.pdf',
            'stored_name' => 'test_123.pdf',
            'size' => 1000,
            'storage_driver' => 'local',
            'path' => '2026/01/test_123.pdf',
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $errors = $this->model->errors();
        $this->assertArrayHasKey('mime_type', $errors);
    }

    public function testValidationRequiresSize(): void
    {
        $data = [
            'user_id' => 1,
            'original_name' => 'test.pdf',
            'stored_name' => 'test_123.pdf',
            'mime_type' => 'application/pdf',
            'storage_driver' => 'local',
            'path' => '2026/01/test_123.pdf',
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $errors = $this->model->errors();
        $this->assertArrayHasKey('size', $errors);
    }

    public function testValidationRequiresStorageDriver(): void
    {
        $data = [
            'user_id' => 1,
            'original_name' => 'test.pdf',
            'stored_name' => 'test_123.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'path' => '2026/01/test_123.pdf',
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $errors = $this->model->errors();
        $this->assertArrayHasKey('storage_driver', $errors);
    }

    public function testValidationRequiresPath(): void
    {
        $data = [
            'user_id' => 1,
            'original_name' => 'test.pdf',
            'stored_name' => 'test_123.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'storage_driver' => 'local',
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $errors = $this->model->errors();
        $this->assertArrayHasKey('path', $errors);
    }

    public function testInsertValidFileRecord(): void
    {
        $data = [
            'user_id' => 1,
            'original_name' => 'newfile.pdf',
            'stored_name' => 'newfile_xyz.pdf',
            'mime_type' => 'application/pdf',
            'size' => 2048,
            'storage_driver' => 'local',
            'path' => '2026/01/18/newfile_xyz.pdf',
            'url' => 'http://localhost/storage/newfile_xyz.pdf',
            'metadata' => '{"extension":"pdf"}',
            'uploaded_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    // ==================== GET BY USER TESTS ====================

    public function testGetByUserReturnsUserFiles(): void
    {
        $files = $this->model->getByUser(1);

        $this->assertIsArray($files);
        $this->assertCount(2, $files);

        foreach ($files as $file) {
            $this->assertEquals(1, $file->user_id);
        }
    }

    public function testGetByUserReturnsEmptyForUserWithNoFiles(): void
    {
        $files = $this->model->getByUser(999);

        $this->assertIsArray($files);
        $this->assertEmpty($files);
    }

    public function testGetByUserOrdersByUploadedAtDesc(): void
    {
        $files = $this->model->getByUser(1);

        $this->assertGreaterThanOrEqual(
            strtotime($files[1]->uploaded_at),
            strtotime($files[0]->uploaded_at)
        );
    }

    public function testGetByUserDoesNotReturnOtherUsersFiles(): void
    {
        $user1Files = $this->model->getByUser(1);
        $user2Files = $this->model->getByUser(2);

        $this->assertCount(2, $user1Files);
        $this->assertCount(1, $user2Files);

        // Verify no overlap
        foreach ($user1Files as $file) {
            $this->assertEquals(1, $file->user_id);
        }

        foreach ($user2Files as $file) {
            $this->assertEquals(2, $file->user_id);
        }
    }

    // ==================== GET BY ID AND USER TESTS ====================

    public function testGetByIdAndUserReturnsFileForOwner(): void
    {
        $allFiles = $this->db->table('files')->where('user_id', 1)->get()->getResult();
        $fileId = $allFiles[0]->id;

        $file = $this->model->getByIdAndUser($fileId, 1);

        $this->assertNotNull($file);
        $this->assertEquals($fileId, $file->id);
        $this->assertEquals(1, $file->user_id);
    }

    public function testGetByIdAndUserReturnsNullForWrongUser(): void
    {
        $allFiles = $this->db->table('files')->where('user_id', 1)->get()->getResult();
        $fileId = $allFiles[0]->id;

        // User 2 trying to access user 1's file
        $file = $this->model->getByIdAndUser($fileId, 2);

        $this->assertNull($file);
    }

    public function testGetByIdAndUserReturnsNullForNonExistentFile(): void
    {
        $file = $this->model->getByIdAndUser(99999, 1);

        $this->assertNull($file);
    }

    public function testGetByIdAndUserEnforcesOwnership(): void
    {
        $user1Files = $this->db->table('files')->where('user_id', 1)->get()->getResult();
        $user1FileId = $user1Files[0]->id;

        // User 2 cannot access user 1's file
        $file = $this->model->getByIdAndUser($user1FileId, 2);
        $this->assertNull($file);

        // User 1 can access their own file
        $file = $this->model->getByIdAndUser($user1FileId, 1);
        $this->assertNotNull($file);
    }

    // ==================== DELETE BY ID AND USER TESTS ====================

    public function testDeleteByIdAndUserDeletesOwnFile(): void
    {
        $files = $this->db->table('files')->where('user_id', 1)->get()->getResult();
        $fileId = $files[0]->id;

        $result = $this->model->deleteByIdAndUser($fileId, 1);

        $this->assertTrue($result);

        // Verify file is deleted
        $deletedFile = $this->db->table('files')->where('id', $fileId)->get()->getFirstRow();
        $this->assertNull($deletedFile);
    }

    public function testDeleteByIdAndUserReturnsFalseForWrongUser(): void
    {
        $files = $this->db->table('files')->where('user_id', 1)->get()->getResult();
        $fileId = $files[0]->id;

        // User 2 trying to delete user 1's file
        $result = $this->model->deleteByIdAndUser($fileId, 2);

        $this->assertFalse($result);

        // Verify file still exists
        $file = $this->db->table('files')->where('id', $fileId)->get()->getFirstRow();
        $this->assertNotNull($file);
    }

    public function testDeleteByIdAndUserReturnsFalseForNonExistentFile(): void
    {
        $result = $this->model->deleteByIdAndUser(99999, 1);

        $this->assertFalse($result);
    }

    // ==================== EDGE CASES ====================

    public function testInsertWithLongFilenames(): void
    {
        $longName = str_repeat('a', 255);

        $data = [
            'user_id' => 1,
            'original_name' => $longName,
            'stored_name' => $longName,
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'storage_driver' => 'local',
            'path' => '2026/01/' . $longName,
        ];

        $result = $this->model->insert($data);

        $this->assertIsInt($result);
    }

    public function testInsertWithLargeFileSize(): void
    {
        $data = [
            'user_id' => 1,
            'original_name' => 'large.zip',
            'stored_name' => 'large_xyz.zip',
            'mime_type' => 'application/zip',
            'size' => PHP_INT_MAX,
            'storage_driver' => 'local',
            'path' => '2026/01/large_xyz.zip',
            'uploaded_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertIsInt($result);
    }

    public function testInsertWithJsonMetadata(): void
    {
        $metadata = json_encode([
            'extension' => 'pdf',
            'uploaded_by' => 1,
            'custom_field' => 'custom_value',
        ]);

        $data = [
            'user_id' => 1,
            'original_name' => 'meta.pdf',
            'stored_name' => 'meta_123.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'storage_driver' => 'local',
            'path' => '2026/01/meta_123.pdf',
            'metadata' => $metadata,
            'uploaded_at' => date('Y-m-d H:i:s'),
        ];

        $id = $this->model->insert($data);

        $file = $this->model->find($id);
        $this->assertIsArray($file->metadata);
        $this->assertEquals('custom_value', $file->metadata['custom_field']);
    }

    public function testGetByUserHandlesMultipleFiles(): void
    {
        // Add more files for user 1
        for ($i = 0; $i < 10; $i++) {
            $this->db->table('files')->insert([
                'user_id' => 1,
                'original_name' => "file{$i}.pdf",
                'stored_name' => "file{$i}_xyz.pdf",
                'mime_type' => 'application/pdf',
                'size' => 1000,
                'storage_driver' => 'local',
                'path' => "2026/01/file{$i}_xyz.pdf",
                'uploaded_at' => date('Y-m-d H:i:s', time() - ($i * 60)),
            ]);
        }

        $files = $this->model->getByUser(1);

        $this->assertGreaterThanOrEqual(12, count($files)); // Original 2 + 10 new
    }

    public function testGetByIdAndUserHandlesZeroUserId(): void
    {
        $files = $this->db->table('files')->where('user_id', 1)->get()->getResult();
        $fileId = $files[0]->id;

        $file = $this->model->getByIdAndUser($fileId, 0);

        $this->assertNull($file);
    }

    // ==================== DATA INTEGRITY TESTS ====================

    public function testFileEntityIsReturned(): void
    {
        $files = $this->model->getByUser(1);

        $this->assertNotEmpty($files);
        $this->assertInstanceOf(\App\Entities\FileEntity::class, $files[0]);
    }

    public function testMetadataIsDecodedAsArray(): void
    {
        $files = $this->model->getByUser(1);

        $this->assertNotEmpty($files);
        $this->assertIsArray($files[0]->metadata);
    }

    public function testUploadedAtIsStored(): void
    {
        $data = [
            'user_id' => 1,
            'original_name' => 'timestamp.pdf',
            'stored_name' => 'timestamp_123.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'storage_driver' => 'local',
            'path' => '2026/01/timestamp_123.pdf',
            'uploaded_at' => date('Y-m-d H:i:s'),
        ];

        $id = $this->model->insert($data);
        $file = $this->model->find($id);

        $this->assertNotNull($file->uploaded_at);
    }

    public function testAutoIncrementIdWorks(): void
    {
        $data1 = [
            'user_id' => 1,
            'original_name' => 'file1.pdf',
            'stored_name' => 'file1_123.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'storage_driver' => 'local',
            'path' => '2026/01/file1_123.pdf',
            'uploaded_at' => date('Y-m-d H:i:s'),
        ];

        $data2 = [
            'user_id' => 1,
            'original_name' => 'file2.pdf',
            'stored_name' => 'file2_123.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'storage_driver' => 'local',
            'path' => '2026/01/file2_123.pdf',
            'uploaded_at' => date('Y-m-d H:i:s'),
        ];

        $id1 = $this->model->insert($data1);
        $id2 = $this->model->insert($data2);

        $this->assertNotEquals($id1, $id2);
        $this->assertGreaterThan($id1, $id2);
    }

    // ==================== SECURITY TESTS ====================

    public function testUserCannotAccessOtherUsersFiles(): void
    {
        $user1Files = $this->model->getByUser(1);
        $user2Files = $this->model->getByUser(2);

        // No file should appear in both lists
        $user1Ids = array_column($user1Files, 'id');
        $user2Ids = array_column($user2Files, 'id');

        $intersection = array_intersect($user1Ids, $user2Ids);
        $this->assertEmpty($intersection);
    }

    public function testDeleteEnforcesOwnership(): void
    {
        $user1Files = $this->db->table('files')->where('user_id', 1)->get()->getResult();
        $user1FileId = $user1Files[0]->id;

        // User 2 cannot delete user 1's file
        $result = $this->model->deleteByIdAndUser($user1FileId, 2);
        $this->assertFalse($result);

        // File should still exist
        $file = $this->db->table('files')->where('id', $user1FileId)->get()->getFirstRow();
        $this->assertNotNull($file);
    }
}
