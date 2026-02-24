<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\ApiKeyModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\AuthTestTrait;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * ApiKeyController Feature Tests
 *
 * Full HTTP request/response cycle tests for the API key management endpoints.
 * All endpoints require admin authentication.
 */
class ApiKeyControllerTest extends CIUnitTestCase
{
    use AuthTestTrait;
    use CustomAssertionsTrait;
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected ApiKeyModel $apiKeyModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiKeyModel = new ApiKeyModel();
    }

    // ==================== AUTH GUARD TESTS ====================

    public function testListApiKeysRequiresAuth(): void
    {
        $result = $this->get('/api/v1/api-keys');

        $result->assertStatus(401);
    }

    public function testListApiKeysRequiresAdminRole(): void
    {
        $email    = 'user-apikeys@example.com';
        $password = 'ValidPass123!';
        $this->createUser($email, $password, 'user');

        $token = $this->loginAndGetToken($email, $password);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->get('/api/v1/api-keys');

        $result->assertStatus(403);
    }

    // ==================== LIST TESTS ====================

    public function testAdminCanListApiKeys(): void
    {
        $adminEmail    = 'admin-list-keys@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->get('/api/v1/api-keys');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
        $this->assertArrayHasKey('meta', $json);
    }

    // ==================== CREATE TESTS ====================

    public function testAdminCanCreateApiKey(): void
    {
        $adminEmail    = 'admin-create-key@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->post('/api/v1/api-keys', [
            'name' => 'My Integration App',
        ]);

        $result->assertStatus(201);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('key', $json['data'], 'Raw key must be returned at creation');
        $this->assertStringStartsWith('apk_', $json['data']['key']);
        $this->assertArrayHasKey('key_prefix', $json['data']);
        $this->assertEquals('My Integration App', $json['data']['name']);
    }

    public function testCreateApiKeyWithCustomRateLimits(): void
    {
        $adminEmail    = 'admin-custom-limits@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->post('/api/v1/api-keys', [
            'name'                => 'High Volume App',
            'rate_limit_requests' => 1200,
            'rate_limit_window'   => 60,
            'user_rate_limit'     => 120,
            'ip_rate_limit'       => 400,
        ]);

        $result->assertStatus(201);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals(1200, $json['data']['rate_limit_requests']);
        $this->assertEquals(120, $json['data']['user_rate_limit']);
        $this->assertEquals(400, $json['data']['ip_rate_limit']);
    }

    public function testCreateApiKeyWithoutNameReturns422(): void
    {
        $adminEmail    = 'admin-noname-key@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->post('/api/v1/api-keys', []);

        $result->assertStatus(422);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
    }

    public function testNonAdminCannotCreateApiKey(): void
    {
        $email    = 'user-create-key@example.com';
        $password = 'ValidPass123!';
        $this->createUser($email, $password, 'user');

        $token = $this->loginAndGetToken($email, $password);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->post('/api/v1/api-keys', [
            'name' => 'Blocked App',
        ]);

        $result->assertStatus(403);
    }

    // ==================== SHOW TESTS ====================

    public function testAdminCanGetApiKeyById(): void
    {
        $adminEmail    = 'admin-show-key@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        // Create one first
        $createResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->post('/api/v1/api-keys', [
            'name' => 'Showable Key',
        ]);
        $createResult->assertStatus(201);
        $createdId = json_decode($createResult->getJSON(), true)['data']['id'];

        // Now fetch it
        $showResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->get("/api/v1/api-keys/{$createdId}");

        $showResult->assertStatus(200);

        $json = json_decode($showResult->getJSON(), true);
        $this->assertEquals('success', $json['status']);
        $this->assertEquals($createdId, $json['data']['id']);
        // Raw key should NOT be present in show response
        $this->assertArrayNotHasKey('key', $json['data']);
    }

    public function testShowNonExistentApiKeyReturns404(): void
    {
        $adminEmail    = 'admin-404-key@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->get('/api/v1/api-keys/99999');

        $result->assertStatus(404);
    }

    // ==================== UPDATE TESTS ====================

    public function testAdminCanUpdateApiKey(): void
    {
        $adminEmail    = 'admin-update-key@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        $createResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->post('/api/v1/api-keys', [
            'name' => 'Original Name',
        ]);
        $createResult->assertStatus(201);
        $createdId = json_decode($createResult->getJSON(), true)['data']['id'];

        $updateResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->put("/api/v1/api-keys/{$createdId}", [
            'name'      => 'Updated Name',
            'is_active' => false,
        ]);

        $updateResult->assertStatus(200);

        $json = json_decode($updateResult->getJSON(), true);
        $this->assertEquals('Updated Name', $json['data']['name']);
        $this->assertFalse((bool) $json['data']['is_active']);
    }

    public function testUpdateWithNoFieldsReturns400(): void
    {
        $adminEmail    = 'admin-noop-key@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        $createResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->post('/api/v1/api-keys', [
            'name' => 'Immutable Key',
        ]);
        $createResult->assertStatus(201);
        $createdId = json_decode($createResult->getJSON(), true)['data']['id'];

        $updateResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->put("/api/v1/api-keys/{$createdId}", []);

        $updateResult->assertStatus(400);
    }

    // ==================== DELETE TESTS ====================

    public function testAdminCanDeleteApiKey(): void
    {
        $adminEmail    = 'admin-delete-key@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        $createResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->post('/api/v1/api-keys', [
            'name' => 'To Be Deleted',
        ]);
        $createResult->assertStatus(201);
        $createdId = json_decode($createResult->getJSON(), true)['data']['id'];

        $deleteResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->delete("/api/v1/api-keys/{$createdId}");

        $deleteResult->assertStatus(200);

        $json = json_decode($deleteResult->getJSON(), true);
        $this->assertEquals('success', $json['status']);

        // Verify it's gone
        $showResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->get("/api/v1/api-keys/{$createdId}");
        $showResult->assertStatus(404);
    }

    public function testDeleteNonExistentApiKeyReturns404(): void
    {
        $adminEmail    = 'admin-del404-key@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->delete('/api/v1/api-keys/99999');

        $result->assertStatus(404);
    }
}
