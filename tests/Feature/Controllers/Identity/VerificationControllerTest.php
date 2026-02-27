<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\UserModel;
use Tests\Support\ApiTestCase;

/**
 * VerificationController Feature Tests
 */
class VerificationControllerTest extends ApiTestCase
{
    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new UserModel();
    }

    public function testVerifyEmailReturns200Successfully(): void
    {
        $token = bin2hex(random_bytes(32));
        $email = 'test-final-verify+' . uniqid('', true) . '@example.com';

        $this->userModel->insert([
            'email' => $email,
            'password' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'email_verified_at' => null,
            'email_verification_token' => $token,
            'verification_token_expires' => date('Y-m-d H:i:s', strtotime('+24 hours')),
        ]);

        $result = $this->get("/api/v1/auth/verify-email?token={$token}");

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
    }

    public function testVerifyEmailReturns404ForInvalidToken(): void
    {
        $result = $this->get('/api/v1/auth/verify-email?token=token-that-does-not-exist-in-db-and-is-long-enough-xyz');

        $result->assertStatus(404);
        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
    }

    public function testVerifyEmailReturns422ForTooShortToken(): void
    {
        $result = $this->get('/api/v1/auth/verify-email?token=short');

        $result->assertStatus(422);
    }
}
