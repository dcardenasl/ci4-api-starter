<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\ApiKeyModel;
use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * ApiKeyController Feature Tests
 *
 * Full HTTP request/response cycle tests for the API key management endpoints.
 * All endpoints require admin authentication.
 */
class ApiKeyControllerTest extends ApiTestCase
{
    use AuthTestTrait;
    use CustomAssertionsTrait;

    protected ApiKeyModel $apiKeyModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiKeyModel = new ApiKeyModel();
        $this->actAs('admin');

        // Ensure static context is set for background model operations (Auditable trait)
        \App\Libraries\ContextHolder::set(new \App\DTO\SecurityContext($this->currentUserId, $this->currentUserRole));
    }

    // ==================== AUTH GUARD TESTS ====================

    public function testListApiKeysRequiresAuth(): void
    {
        \App\Libraries\ContextHolder::flush();
        $this->clearTestRequestHeaders();
        $result = $this->get('/api/v1/api-keys');

        $result->assertStatus(401);
    }

    public function testListApiKeysRequiresAdminRole(): void
    {
        $this->actAs('user');

        $result = $this->get('/api/v1/api-keys');

        $result->assertStatus(403);
    }

    // ==================== LIST TESTS ====================

    public function testAdminCanListApiKeys(): void
    {
        $result = $this->get('/api/v1/api-keys');

        $result->assertStatus(200);

        $json = $this->getResponseJson($result);
        $this->assertEquals('success', $json['status']);
        $this->assertArrayHasKey('meta', $json);
    }

    // ==================== CREATE TESTS ====================

    public function testAdminCanCreateApiKey(): void
    {
        $result = $this->withBodyFormat('json')->post('/api/v1/api-keys', [
            'name' => 'My Integration App',
        ]);

        $result->assertStatus(201);

        $json = $this->getResponseJson($result);
        $this->assertEquals('success', $json['status']);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('key', $json['data'], 'Raw key must be returned at creation');
        $this->assertStringStartsWith('apk_', $json['data']['key']);
        $this->assertArrayHasKey('keyPrefix', $json['data']);
        $this->assertEquals('My Integration App', $json['data']['name']);
    }

    public function testCreateApiKeyWithCustomRateLimits(): void
    {
        $result = $this->withBodyFormat('json')->post('/api/v1/api-keys', [
            'name'                => 'High Volume App',
            'rateLimitRequests' => 1200,
            'rateLimitWindow'   => 60,
            'userRateLimit'     => 120,
            'ipRateLimit'       => 400,
        ]);

        $result->assertStatus(201);

        $json = $this->getResponseJson($result);
        $this->assertEquals(1200, $json['data']['rateLimitRequests']);
        $this->assertEquals(120, $json['data']['userRateLimit']);
        $this->assertEquals(400, $json['data']['ipRateLimit']);
    }

    public function testCreateApiKeyWithoutNameReturns422(): void
    {
        $result = $this->withBodyFormat('json')->post('/api/v1/api-keys', []);

        $result->assertStatus(422);

        $json = $this->getResponseJson($result);
        $this->assertArrayHasKey('errors', $json);
    }

    public function testNonAdminCannotCreateApiKey(): void
    {
        $this->actAs('user');

        $result = $this->withBodyFormat('json')->post('/api/v1/api-keys', [
            'name' => 'Blocked App',
        ]);

        $result->assertStatus(403);
    }

    // ==================== SHOW TESTS ====================

    public function testAdminCanGetApiKeyById(): void
    {
        // Create one first
        $createResult = $this->withBodyFormat('json')->post('/api/v1/api-keys', [
            'name' => 'Showable Key',
        ]);
        $createResult->assertStatus(201);
        $createdId = $this->getResponseJson($createResult)['data']['id'];

        // Now fetch it
        $showResult = $this->get("/api/v1/api-keys/{$createdId}");

        $showResult->assertStatus(200);

        $json = $this->getResponseJson($showResult);
        $this->assertEquals('success', $json['status']);
        $this->assertEquals($createdId, $json['data']['id']);
        // Raw key should NOT be present in show response
        $this->assertArrayNotHasKey('key', $json['data']);
    }

    public function testShowNonExistentApiKeyReturns404(): void
    {
        $result = $this->get('/api/v1/api-keys/99999');

        $result->assertStatus(404);
    }

    // ==================== UPDATE TESTS ====================

    public function testAdminCanUpdateApiKey(): void
    {
        $createResult = $this->withBodyFormat('json')->post('/api/v1/api-keys', [
            'name' => 'Original Name',
        ]);
        $createResult->assertStatus(201);
        $createdId = $this->getResponseJson($createResult)['data']['id'];

        $updateResult = $this->withBodyFormat('json')->put("/api/v1/api-keys/{$createdId}", [
            'name'      => 'Updated Name',
            'isActive' => false,
        ]);

        $updateResult->assertStatus(200);

        $json = $this->getResponseJson($updateResult);
        $this->assertEquals('Updated Name', $json['data']['name']);
        $this->assertFalse((bool) $json['data']['isActive']);
    }

    public function testUpdateWithNoFieldsReturns400(): void
    {
        // Insert directly via model
        $createdId = $this->apiKeyModel->insert([
            'name' => 'Immutable Key',
            'key_prefix' => 'apk_abc',
            'key_hash' => 'hash123',
            'is_active' => 1
        ]);

        $updateResult = $this->withBodyFormat('json')->put("/api/v1/api-keys/{$createdId}", [
            'name' => null, // Explicitly sending null should result in an empty filtered array
        ]);

        $updateResult->assertStatus(400);
    }

    // ==================== DELETE TESTS ====================

    public function testAdminCanDeleteApiKey(): void
    {
        $createResult = $this->withBodyFormat('json')->post('/api/v1/api-keys', [
            'name' => 'To Be Deleted',
        ]);
        $createResult->assertStatus(201);
        $createdId = $this->getResponseJson($createResult)['data']['id'];

        $deleteResult = $this->delete("/api/v1/api-keys/{$createdId}");

        $deleteResult->assertStatus(200);

        $json = $this->getResponseJson($deleteResult);
        $this->assertEquals('success', $json['status']);

        // Verify it's gone
        $showResult = $this->get("/api/v1/api-keys/{$createdId}");
        $showResult->assertStatus(404);
    }

    public function testDeleteNonExistentApiKeyReturns404(): void
    {
        $result = $this->delete('/api/v1/api-keys/99999');

        $result->assertStatus(404);
    }
}
