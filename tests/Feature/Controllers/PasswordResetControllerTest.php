<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * PasswordResetController Feature Tests
 *
 * Tests HTTP endpoints for password reset flow.
 */
class PasswordResetControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new UserModel();
    }

    // ==================== SEND RESET LINK TESTS ====================

    public function testSendResetLinkReturns200ForValidEmail(): void
    {
        // Create user
        $this->userModel->insert([
            'email' => 'test@example.com',
            'password' => password_hash('OldPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
        ]);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/forgot-password', [
                'email' => 'test@example.com',
            ]);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
    }

    public function testSendResetLinkReturns200ForNonExistentEmail(): void
    {
        // Should return success to prevent email enumeration
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/forgot-password', [
                'email' => 'nonexistent@example.com',
            ]);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
    }

    public function testSendResetLinkReturns422ForInvalidEmail(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/forgot-password', [
                'email' => 'invalid-email',
            ]);

        $result->assertStatus(422);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
        $this->assertArrayHasKey('errors', $json);
    }

    // ==================== VALIDATE TOKEN TESTS ====================

    public function testValidateTokenReturns200ForValidToken(): void
    {
        // Create user and token
        $userId = $this->userModel->insert([
            'email' => 'test@example.com',
            'password' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $token = bin2hex(random_bytes(32));
        $db = \Config\Database::connect();
        $db->table('password_resets')->insert([
            'email' => 'test@example.com',
            'token' => $token,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->get("/api/v1/auth/validate-reset-token?token={$token}&email=test@example.com");

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
        $this->assertTrue($json['data']['valid']);
    }

    public function testValidateTokenReturns400ForInvalidToken(): void
    {
        $result = $this->get('/api/v1/auth/validate-reset-token?token=invalid&email=test@example.com');

        $result->assertStatus(404);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
    }

    // ==================== RESET PASSWORD TESTS ====================

    public function testResetPasswordReturns200Successfully(): void
    {
        // Create user and token
        $this->userModel->insert([
            'email' => 'test@example.com',
            'password' => password_hash('OldPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $token = bin2hex(random_bytes(32));
        $db = \Config\Database::connect();
        $db->table('password_resets')->insert([
            'email' => 'test@example.com',
            'token' => $token,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/reset-password', [
                'email' => 'test@example.com',
                'token' => $token,
                'password' => 'NewSecure123!',
            ]);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
    }

    public function testResetPasswordReturns422ForWeakPassword(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/reset-password', [
                'email' => 'test@example.com',
                'token' => 'some-token',
                'password' => 'weak',
            ]);

        $result->assertStatus(422);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
        $this->assertArrayHasKey('errors', $json);
    }
}
