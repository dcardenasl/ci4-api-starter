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

    protected $migrate = true;
    protected $refresh = true;

    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new UserModel();
    }

    // ==================== REGISTER ENDPOINT TESTS ====================

    public function testRegisterWithValidDataReturnsTokens(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/register', [
                'username' => 'newuser',
                'email' => 'new@example.com',
                'password' => 'ValidPass123!',
            ]);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
        $this->assertArrayHasKey('access_token', $json['data']);
        $this->assertArrayHasKey('refresh_token', $json['data']);
        $this->assertArrayHasKey('user', $json['data']);
    }

    public function testRegisterWithInvalidDataReturnsValidationError(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/register', [
                'username' => 'ab', // Too short
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
            'username' => 'existing',
            'email' => 'existing@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/register', [
                'username' => 'newuser',
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
            'username' => 'logintest',
            'email' => 'login@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'logintest',
                'password' => 'ValidPass123!',
            ]);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
        $this->assertArrayHasKey('access_token', $json['data']);
        $this->assertArrayHasKey('refresh_token', $json['data']);
    }

    public function testLoginWithInvalidCredentialsReturnsUnauthorized(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'nonexistent',
                'password' => 'WrongPass123!',
            ]);

        $result->assertStatus(401);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
    }

    public function testLoginWithEmptyCredentialsReturnsError(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => '',
                'password' => '',
            ]);

        $result->assertStatus(401);
    }

    // ==================== PROTECTED ENDPOINT TESTS ====================

    public function testProtectedEndpointWithoutTokenReturnsUnauthorized(): void
    {
        $result = $this->get('/api/v1/users');

        $result->assertStatus(401);
    }

    public function testProtectedEndpointWithValidTokenReturnsData(): void
    {
        // Create user and get token
        $this->userModel->insert([
            'username' => 'authtest',
            'email' => 'auth@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'admin',
        ]);

        $loginResult = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'authtest',
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

    // ==================== HEALTH CHECK TESTS ====================

    public function testHealthEndpointReturnsOk(): void
    {
        $result = $this->get('/health');

        $result->assertStatus(200);
    }

    public function testPingEndpointReturnsOk(): void
    {
        $result = $this->get('/ping');

        $result->assertStatus(200);
    }
}
