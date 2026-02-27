<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\FileModel;
use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

class FileControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    protected FileModel $fileModel;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileModel = new FileModel();
        $identity = $this->actAs('user');
        $this->token = $identity['token'];
    }

    public function testListFilesRequiresAuth(): void
    {
        $this->resetState(); // Ensure clean state
        \App\Libraries\ContextHolder::flush();
        $this->clearTestRequestHeaders();
        $result = $this->get('/api/v1/files');

        $result->assertStatus(401);
    }

    public function testListFilesReturnsSuccess(): void
    {
        \App\Libraries\ContextHolder::set(new \App\DTO\SecurityContext($this->currentUserId, $this->currentUserRole));
        $this->createFile($this->currentUserId);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get('/api/v1/files');

        $result->assertStatus(200);
    }

    public function testGetFileReturnsSuccess(): void
    {
        \App\Libraries\ContextHolder::set(new \App\DTO\SecurityContext($this->currentUserId, $this->currentUserRole));
        $fileId = $this->createFile($this->currentUserId);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/files/{$fileId}");

        $result->assertStatus(200);
    }

    public function testDeleteFileReturnsSuccess(): void
    {
        \App\Libraries\ContextHolder::set(new \App\DTO\SecurityContext($this->currentUserId, $this->currentUserRole));
        $fileId = $this->createFile($this->currentUserId);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->delete("/api/v1/files/{$fileId}");

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
