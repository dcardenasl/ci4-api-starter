<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * AuthController Feature Tests
 *
 * Tests HTTP endpoints for authentication.
 * These tests verify the full request/response cycle.
 */
class AuthControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';  // Use app migrations

    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new UserModel();
    }

    // ==================== REGISTER ENDPOINT TESTS ====================

    public function testRegisterWithValidDataReturnsPendingApprovalMessage(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/register', [
                'email' => 'new@example.com',
                'password' => 'ValidPass123!',
            ]);

        // Register returns 201 Created (not 200)
        $result->assertStatus(201);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
        $this->assertArrayHasKey('user', $json['data']);
        $this->assertArrayNotHasKey('access_token', $json['data']);
        $this->assertArrayNotHasKey('refresh_token', $json['data']);
        $this->assertArrayHasKey('message', $json);
    }

    public function testRegisterWithInvalidDataReturnsValidationError(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/register', [
                'email' => 'invalid-email',
                'password' => 'weak',
            ]);

        $result->assertStatus(422);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
        $this->assertArrayHasKey('errors', $json);
    }

    public function testRegisterWithDuplicateEmailReturnsError(): void
    {
        // Create existing user
        $this->userModel->insert([
            'email' => 'existing@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/register', [
                'email' => 'existing@example.com',
                'password' => 'ValidPass123!',
            ]);

        $result->assertStatus(422);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
    }

    // ==================== LOGIN ENDPOINT TESTS ====================

    public function testLoginWithValidCredentialsReturnsTokens(): void
    {
        // Create user
        $this->userModel->insert([
            'email' => 'login@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => 'login@example.com',
                'password' => 'ValidPass123!',
            ]);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
        $this->assertArrayHasKey('access_token', $json['data']);
        $this->assertArrayHasKey('refresh_token', $json['data']);
    }

    public function testLoginWithPendingApprovalReturnsForbidden(): void
    {
        $this->userModel->insert([
            'email' => 'pending@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'pending_approval',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => 'pending@example.com',
                'password' => 'ValidPass123!',
            ]);

        $result->assertStatus(403);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
        $this->assertArrayHasKey('errors', $json);
        $this->assertEquals(403, $json['code']);
    }

    public function testLoginWithInvalidCredentialsReturnsUnauthorized(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => 'nonexistent@example.com',
                'password' => 'WrongPass123!',
            ]);

        $result->assertStatus(401);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
        $this->assertArrayHasKey('errors', $json);
        $this->assertEquals(401, $json['code']);
    }

    public function testLoginWithEmptyCredentialsReturnsError(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => '',
                'password' => '',
            ]);

        $result->assertStatus(401);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
        $this->assertArrayHasKey('errors', $json);
        $this->assertEquals(401, $json['code']);
    }

    // ==================== PROTECTED ENDPOINT TESTS ====================

    public function testProtectedEndpointWithoutTokenReturnsUnauthorized(): void
    {
        $result = $this->get('/api/v1/users');

        $result->assertStatus(401);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
        $this->assertArrayHasKey('errors', $json);
        $this->assertEquals(401, $json['code']);
    }

    public function testProtectedEndpointWithValidTokenReturnsData(): void
    {
        // Create user and get token
        $this->userModel->insert([
            'email' => 'auth@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $loginResult = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => 'auth@example.com',
                'password' => 'ValidPass123!',
            ]);

        $loginJson = json_decode($loginResult->getJSON(), true);
        $token = $loginJson['data']['access_token'];

        // Access protected endpoint
        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->get('/api/v1/users');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
    }

    public function testApprovePendingUserAllowsLogin(): void
    {
        $adminId = $this->userModel->insert([
            'email' => 'admin@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $pendingId = $this->userModel->insert([
            'email' => 'approve@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'pending_approval',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $loginResult = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => 'admin@example.com',
                'password' => 'ValidPass123!',
            ]);

        $loginJson = json_decode($loginResult->getJSON(), true);
        $token = $loginJson['data']['access_token'];

        $approveResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->post("/api/v1/users/{$pendingId}/approve");

        $approveResult->assertStatus(200);

        $user = $this->userModel->find($pendingId);
        $this->assertEquals('active', $user->status);

        $loginPending = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => 'approve@example.com',
                'password' => 'ValidPass123!',
            ]);

        $loginPending->assertStatus(200);
    }

    // ==================== HEALTH CHECK TESTS ====================

    public function testHealthEndpointResponds(): void
    {
        $result = $this->get('/health');

        // Health returns 200 if all services OK, 503 if some fail
        // In test environment, some services may not be available
        $this->assertTrue(in_array($result->response()->getStatusCode(), [200, 503], true));
    }

    public function testPingEndpointReturnsOk(): void
    {
        $result = $this->get('/ping');

        $result->assertStatus(200);
    }
}
