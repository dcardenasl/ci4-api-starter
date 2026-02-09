<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\FileModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\AuthTestTrait;

class FileControllerTest extends CIUnitTestCase
{
    use AuthTestTrait;
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected FileModel $fileModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileModel = new FileModel();
    }

    public function testListFilesRequiresAuth(): void
    {
        $result = $this->get('/api/v1/files');

        $result->assertStatus(401);
    }

    public function testListFilesReturnsSuccess(): void
    {
        $email = 'files@example.com';
        $password = 'ValidPass123!';
        $userId = $this->createUser($email, $password);

        $this->createFile($userId);

        $token = $this->loginAndGetToken($email, $password);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->get('/api/v1/files');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
    }

    public function testGetFileReturnsSuccess(): void
    {
        $email = 'file-get@example.com';
        $password = 'ValidPass123!';
        $userId = $this->createUser($email, $password);

        $fileId = $this->createFile($userId);

        $token = $this->loginAndGetToken($email, $password);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->get("/api/v1/files/{$fileId}");

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
    }

    public function testDeleteFileReturnsSuccess(): void
    {
        $email = 'file-delete@example.com';
        $password = 'ValidPass123!';
        $userId = $this->createUser($email, $password);

        $fileId = $this->createFile($userId);

        $token = $this->loginAndGetToken($email, $password);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->delete("/api/v1/files/{$fileId}");

        $result->assertStatus(200);
    }

    private function createFile(int $userId): int
    {
        return (int) $this->fileModel->insert([
            'user_id' => $userId,
            'original_name' => 'example.pdf',
            'stored_name' => 'example_123.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1234,
            'storage_driver' => 's3',
            'path' => '2024/01/01/example_123.pdf',
            'url' => 'https://example.com/example_123.pdf',
            'metadata' => json_encode(['extension' => 'pdf']),
            'uploaded_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
