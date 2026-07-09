<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Internal;

use App\Models\FileModel;
use Config\Services;
use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

/**
 * InternalFileMetaController Feature Tests
 *
 * Covers GET /api/v1/internal/files/batch-meta: X-App-Key auth (missing,
 * invalid), the happy path resolving {id, url, variants}, exclusion of
 * soft-deleted files, and the 200-ID batch cap.
 */
class InternalFileMetaControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    protected FileModel $fileModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileModel = new FileModel();
    }

    public function testBatchMetaMissingAppKeyReturns401(): void
    {
        $result = $this->get('/api/v1/internal/files/batch-meta?ids[]=1');

        $result->assertStatus(401);
        $json = $this->getResponseJson($result);
        $this->assertSame('error', $json['status']);
    }

    public function testBatchMetaInvalidAppKeyReturns403(): void
    {
        $result = $this->withHeaders(['X-App-Key' => 'apk_doesnotexist0000000000000000000'])
            ->get('/api/v1/internal/files/batch-meta?ids[]=1');

        $result->assertStatus(403);
        $json = $this->getResponseJson($result);
        $this->assertSame('error', $json['status']);
    }

    public function testBatchMetaHappyPathResolvesMetadata(): void
    {
        $rawKey = $this->createActiveApiKey();
        $userId = $this->createUser('filemeta-owner@example.com', 'ValidPass123!');

        $fileId = $this->createFile($userId, [
            'variants' => json_encode([
                'thumb' => ['url' => 'https://cdn.example.com/f/thumb.jpg', 'width' => 150, 'height' => 150],
            ]),
        ]);

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->get('/api/v1/internal/files/batch-meta?' . http_build_query(['ids' => [$fileId]]));

        $result->assertStatus(200);
        $json = $this->getResponseJson($result);

        $this->assertSame('success', $json['status']);
        $this->assertArrayHasKey((string) $fileId, $json['data']);
        $meta = $json['data'][(string) $fileId];
        $this->assertSame($fileId, $meta['id']);
        $this->assertSame('https://example.com/example_123.pdf', $meta['url']);
        $this->assertSame('https://cdn.example.com/f/thumb.jpg', $meta['variants']['thumb']['url']);
    }

    public function testBatchMetaExcludesSoftDeletedFiles(): void
    {
        $rawKey = $this->createActiveApiKey();
        $userId = $this->createUser('filemeta-owner2@example.com', 'ValidPass123!');

        $fileId = $this->createFile($userId);
        $this->fileModel->delete($fileId); // soft delete

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->get('/api/v1/internal/files/batch-meta?' . http_build_query(['ids' => [$fileId]]));

        $result->assertStatus(200);
        $json = $this->getResponseJson($result);

        $this->assertArrayNotHasKey((string) $fileId, $json['data']);
    }

    public function testBatchMetaEmptyIdsReturnsEmptyData(): void
    {
        $rawKey = $this->createActiveApiKey();

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->get('/api/v1/internal/files/batch-meta');

        $result->assertStatus(200);
        $json = $this->getResponseJson($result);

        $this->assertSame('success', $json['status']);
        $this->assertSame([], $json['data']);
    }

    public function testBatchMetaCapsAt200Ids(): void
    {
        $rawKey = $this->createActiveApiKey();
        $userId = $this->createUser('filemeta-owner3@example.com', 'ValidPass123!');

        $realFileId = $this->createFile($userId);

        // 200 non-existent IDs first, then the real file ID as the 201st
        // unique entry — the batch cap must slice it off.
        $ids = range(900000, 900199);
        $ids[] = $realFileId;

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->get('/api/v1/internal/files/batch-meta?' . http_build_query(['ids' => $ids]));

        $result->assertStatus(200);
        $json = $this->getResponseJson($result);

        $this->assertArrayNotHasKey(
            (string) $realFileId,
            $json['data'],
            'The 201st unique ID must be dropped by the 200-item cap'
        );
    }

    public function testBatchMetaWithinCapReturnsRealFile(): void
    {
        $rawKey = $this->createActiveApiKey();
        $userId = $this->createUser('filemeta-owner4@example.com', 'ValidPass123!');

        $realFileId = $this->createFile($userId);

        // Real file ID first, then 199 non-existent IDs — total is exactly
        // 200 unique entries, so the real file must survive the cap.
        $ids = [$realFileId, ...range(900000, 900198)];

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->get('/api/v1/internal/files/batch-meta?' . http_build_query(['ids' => $ids]));

        $result->assertStatus(200);
        $json = $this->getResponseJson($result);

        $this->assertArrayHasKey((string) $realFileId, $json['data']);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createFile(int $userId, array $overrides = []): int
    {
        return (int) $this->fileModel->insert(array_merge([
            'user_id'        => $userId,
            'original_name'  => 'example.pdf',
            'stored_name'    => 'example_123.pdf',
            'mime_type'      => 'application/pdf',
            'size'           => 1234,
            'storage_driver' => 's3',
            'path'           => '2024/01/01/example_123.pdf',
            'url'            => 'https://example.com/example_123.pdf',
            'metadata'       => json_encode(['extension' => 'pdf']),
            'uploaded_at'    => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /**
     * Insert an API key directly and return the raw key value.
     */
    private function createActiveApiKey(string $name = 'internal-filemeta-test-key', bool $isActive = true): string
    {
        $material = Services::apiKeyMaterialService();
        $rawKey   = $material->generateRawKey();

        \Config\Database::connect()
            ->table('api_keys')
            ->insert([
                'name'                => $name,
                'key_prefix'          => substr($rawKey, 0, 8),
                'key_hash'            => $material->hash($rawKey),
                'is_active'           => $isActive ? 1 : 0,
                'rate_limit_requests' => 600,
                'rate_limit_window'   => 60,
                'user_rate_limit'     => 60,
                'ip_rate_limit'       => 200,
                'created_at'          => date('Y-m-d H:i:s'),
            ]);

        return $rawKey;
    }
}
