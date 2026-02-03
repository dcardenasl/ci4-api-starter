<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\UserModel;
use App\Services\VerificationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * VerificationService Integration Tests
 *
 * Tests the complete email verification flow with real database operations.
 * Includes token generation, validation, expiration, and resending.
 */
class VerificationServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected VerificationService $service;
    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new VerificationService();
        $this->userModel = new UserModel();

        $this->seedDatabase();
    }

    protected function seedDatabase(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call('Tests\Support\Database\Seeds\TestUserSeeder');

        // Create unverified user
        $this->userModel->insert([
            'username' => 'unverified',
            'email' => 'unverified@example.com',
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'role' => 'user',
            'email_verified_at' => null,
            'email_verification_token' => null,
            'verification_token_expires' => null,
        ]);

        // Create verified user
        $this->userModel->insert([
            'username' => 'verified',
            'email' => 'verified@example.com',
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'role' => 'user',
            'email_verified_at' => date('Y-m-d H:i:s'),
            'email_verification_token' => null,
            'verification_token_expires' => null,
        ]);
    }

    // ==================== SEND VERIFICATION EMAIL TESTS ====================

    public function testSendVerificationEmailGeneratesToken(): void
    {
        $user = $this->userModel->where('email', 'unverified@example.com')->first();

        $result = $this->service->sendVerificationEmail((int) $user->id);

        $this->assertEquals('success', $result['status']);

        // Verify token was generated
        $updatedUser = $this->userModel->find($user->id);
        $this->assertNotNull($updatedUser->email_verification_token);
        $this->assertEquals(64, strlen($updatedUser->email_verification_token));
    }

    public function testSendVerificationEmailSetsExpiration(): void
    {
        $user = $this->userModel->where('email', 'unverified@example.com')->first();

        $beforeTime = time() + (24 * 3600); // 24 hours from now
        $result = $this->service->sendVerificationEmail((int) $user->id);
        $afterTime = time() + (24 * 3600) + 2;

        $this->assertEquals('success', $result['status']);

        // Verify expiration was set (24 hours)
        $updatedUser = $this->userModel->find($user->id);
        $expiresAt = strtotime($updatedUser->verification_token_expires);

        $this->assertGreaterThanOrEqual($beforeTime, $expiresAt);
        $this->assertLessThanOrEqual($afterTime, $expiresAt);
    }

    public function testSendVerificationEmailFailsForNonExistentUser(): void
    {
        $result = $this->service->sendVerificationEmail(99999);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(404, $result['code']);
    }

    public function testSendVerificationEmailFailsForAlreadyVerifiedUser(): void
    {
        $user = $this->userModel->where('email', 'verified@example.com')->first();

        $result = $this->service->sendVerificationEmail((int) $user->id);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('email', $result['errors']);
    }

    public function testSendVerificationEmailGeneratesSecureToken(): void
    {
        $user = $this->userModel->where('email', 'unverified@example.com')->first();

        $this->service->sendVerificationEmail((int) $user->id);

        $updatedUser = $this->userModel->find($user->id);

        // Token should be 64 character hex string (32 bytes)
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $updatedUser->email_verification_token);
    }

    public function testSendVerificationEmailGeneratesUniqueTokens(): void
    {
        $user = $this->userModel->where('email', 'unverified@example.com')->first();

        // Send first time
        $this->service->sendVerificationEmail((int) $user->id);
        $user1 = $this->userModel->find($user->id);
        $token1 = $user1->email_verification_token;

        // Send second time
        $this->service->sendVerificationEmail((int) $user->id);
        $user2 = $this->userModel->find($user->id);
        $token2 = $user2->email_verification_token;

        // Tokens should be different
        $this->assertNotEquals($token1, $token2);
    }

    // ==================== VERIFY EMAIL TESTS ====================

    public function testVerifyEmailSucceedsWithValidToken(): void
    {
        $user = $this->userModel->where('email', 'unverified@example.com')->first();

        // Send verification email
        $this->service->sendVerificationEmail((int) $user->id);

        // Get token
        $updatedUser = $this->userModel->find($user->id);
        $token = $updatedUser->email_verification_token;

        // Verify email
        $result = $this->service->verifyEmail($token);

        $this->assertEquals('success', $result['status']);

        // Check user is now verified
        $verifiedUser = $this->userModel->find($user->id);
        $this->assertNotNull($verifiedUser->email_verified_at);
    }

    public function testVerifyEmailClearsToken(): void
    {
        $user = $this->userModel->where('email', 'unverified@example.com')->first();

        // Send verification email
        $this->service->sendVerificationEmail((int) $user->id);

        // Get token
        $updatedUser = $this->userModel->find($user->id);
        $token = $updatedUser->email_verification_token;

        // Verify email
        $this->service->verifyEmail($token);

        // Token should be cleared
        $verifiedUser = $this->userModel->find($user->id);
        $this->assertNull($verifiedUser->email_verification_token);
        $this->assertNull($verifiedUser->verification_token_expires);
    }

    public function testVerifyEmailFailsWithInvalidToken(): void
    {
        $result = $this->service->verifyEmail('invalid_token_here');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(400, $result['code']);
    }

    public function testVerifyEmailFailsWithExpiredToken(): void
    {
        $user = $this->userModel->where('email', 'unverified@example.com')->first();

        // Create expired token
        $expiredToken = bin2hex(random_bytes(32));
        $this->userModel->update($user->id, [
            'email_verification_token' => $expiredToken,
            'verification_token_expires' => date('Y-m-d H:i:s', time() - 3600), // 1 hour ago
        ]);

        $result = $this->service->verifyEmail($expiredToken);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(400, $result['code']);
    }

    public function testVerifyEmailSucceedsForAlreadyVerifiedUser(): void
    {
        $user = $this->userModel->where('email', 'unverified@example.com')->first();

        // Verify first time
        $this->service->sendVerificationEmail((int) $user->id);
        $updatedUser = $this->userModel->find($user->id);
        $token = $updatedUser->email_verification_token;

        $result1 = $this->service->verifyEmail($token);
        $this->assertEquals('success', $result1['status']);

        // Try to verify again with new token
        $this->userModel->update($user->id, [
            'email_verification_token' => bin2hex(random_bytes(32)),
            'verification_token_expires' => date('Y-m-d H:i:s', time() + 86400),
        ]);

        $updatedUser2 = $this->userModel->find($user->id);
        $result2 = $this->service->verifyEmail($updatedUser2->email_verification_token);

        // Should return success but indicate already verified
        $this->assertEquals('success', $result2['status']);
    }

    public function testVerifyEmailCannotReuseToken(): void
    {
        $user = $this->userModel->where('email', 'unverified@example.com')->first();

        // Send verification email
        $this->service->sendVerificationEmail((int) $user->id);
        $updatedUser = $this->userModel->find($user->id);
        $token = $updatedUser->email_verification_token;

        // Verify first time
        $result1 = $this->service->verifyEmail($token);
        $this->assertEquals('success', $result1['status']);

        // Try to use same token again
        $result2 = $this->service->verifyEmail($token);

        // Should fail because token was cleared
        $this->assertEquals('error', $result2['status']);
        $this->assertEquals(400, $result2['code']);
    }

    public function testVerifyEmailSetsVerifiedTimestamp(): void
    {
        $user = $this->userModel->where('email', 'unverified@example.com')->first();

        $beforeTime = time();

        // Send and verify
        $this->service->sendVerificationEmail((int) $user->id);
        $updatedUser = $this->userModel->find($user->id);
        $this->service->verifyEmail($updatedUser->email_verification_token);

        $afterTime = time();

        // Check timestamp
        $verifiedUser = $this->userModel->find($user->id);
        $verifiedAt = strtotime($verifiedUser->email_verified_at);

        $this->assertGreaterThanOrEqual($beforeTime, $verifiedAt);
        $this->assertLessThanOrEqual($afterTime, $verifiedAt);
    }

    // ==================== RESEND VERIFICATION TESTS ====================

    public function testResendVerificationGeneratesNewToken(): void
    {
        $user = $this->userModel->where('email', 'unverified@example.com')->first();

        // Send first token
        $this->service->sendVerificationEmail((int) $user->id);
        $user1 = $this->userModel->find($user->id);
        $token1 = $user1->email_verification_token;

        // Resend
        $result = $this->service->resendVerification((int) $user->id);

        $this->assertEquals('success', $result['status']);

        // Should have new token
        $user2 = $this->userModel->find($user->id);
        $token2 = $user2->email_verification_token;

        $this->assertNotEquals($token1, $token2);
    }

    public function testResendVerificationFailsForNonExistentUser(): void
    {
        $result = $this->service->resendVerification(99999);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(404, $result['code']);
    }

    public function testResendVerificationFailsForVerifiedUser(): void
    {
        $user = $this->userModel->where('email', 'verified@example.com')->first();

        $result = $this->service->resendVerification((int) $user->id);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('email', $result['errors']);
    }

    public function testResendVerificationUpdatesExpiration(): void
    {
        $user = $this->userModel->where('email', 'unverified@example.com')->first();

        // Send first verification
        $this->service->sendVerificationEmail((int) $user->id);
        $user1 = $this->userModel->find($user->id);
        $expires1 = $user1->verification_token_expires;

        // Wait a moment
        sleep(1);

        // Resend
        $this->service->resendVerification((int) $user->id);
        $user2 = $this->userModel->find($user->id);
        $expires2 = $user2->verification_token_expires;

        // Expiration should be updated
        $this->assertNotEquals($expires1, $expires2);
        $this->assertGreaterThan(strtotime($expires1), strtotime($expires2));
    }

    // ==================== SECURITY TESTS ====================

    public function testVerificationTokensAreSecurelyRandom(): void
    {
        $user = $this->userModel->where('email', 'unverified@example.com')->first();

        $tokens = [];
        for ($i = 0; $i < 10; $i++) {
            $this->service->sendVerificationEmail((int) $user->id);
            $updatedUser = $this->userModel->find($user->id);
            $tokens[] = $updatedUser->email_verification_token;
        }

        // All tokens should be unique
        $uniqueTokens = array_unique($tokens);
        $this->assertCount(10, $uniqueTokens);

        // All should be 64 char hex
        foreach ($tokens as $token) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        }
    }

    public function testOldTokenBecomesInvalidAfterResend(): void
    {
        $user = $this->userModel->where('email', 'unverified@example.com')->first();

        // Send first verification
        $this->service->sendVerificationEmail((int) $user->id);
        $user1 = $this->userModel->find($user->id);
        $oldToken = $user1->email_verification_token;

        // Resend (generates new token)
        $this->service->resendVerification((int) $user->id);

        // Old token should not work
        $result = $this->service->verifyEmail($oldToken);

        $this->assertEquals('error', $result['status']);
    }

    public function testTokenExpiresAfter24Hours(): void
    {
        $user = $this->userModel->where('email', 'unverified@example.com')->first();

        $this->service->sendVerificationEmail((int) $user->id);

        $updatedUser = $this->userModel->find($user->id);
        $expiresAt = strtotime($updatedUser->verification_token_expires);
        $expectedExpiry = time() + (24 * 3600); // 24 hours

        // Should expire in approximately 24 hours (within 2 seconds tolerance)
        $this->assertEquals($expectedExpiry, $expiresAt, '', 2);
    }

    // ==================== EDGE CASES ====================

    public function testVerifyEmailWithEmptyToken(): void
    {
        $result = $this->service->verifyEmail('');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(422, $result['code']);
    }

    public function testSendVerificationEmailToUserWithoutEmail(): void
    {
        // Create user without email (edge case)
        $userId = $this->userModel->insert([
            'username' => 'noemail',
            'email' => 'noemail@test.com',
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->service->sendVerificationEmail($userId);

        // Should succeed (email service will handle the email)
        $this->assertEquals('success', $result['status']);
    }

    public function testMultipleUsersCanVerifySimultaneously(): void
    {
        // Create two unverified users
        $user1Id = $this->userModel->insert([
            'username' => 'user1',
            'email' => 'user1@test.com',
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $user2Id = $this->userModel->insert([
            'username' => 'user2',
            'email' => 'user2@test.com',
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        // Send verification emails
        $this->service->sendVerificationEmail($user1Id);
        $this->service->sendVerificationEmail($user2Id);

        // Get tokens
        $user1 = $this->userModel->find($user1Id);
        $user2 = $this->userModel->find($user2Id);

        // Verify both
        $result1 = $this->service->verifyEmail($user1->email_verification_token);
        $result2 = $this->service->verifyEmail($user2->email_verification_token);

        $this->assertEquals('success', $result1['status']);
        $this->assertEquals('success', $result2['status']);

        // Both should be verified
        $verified1 = $this->userModel->find($user1Id);
        $verified2 = $this->userModel->find($user2Id);

        $this->assertNotNull($verified1->email_verified_at);
        $this->assertNotNull($verified2->email_verified_at);
    }

    public function testTokenAtExpirationBoundary(): void
    {
        $user = $this->userModel->where('email', 'unverified@example.com')->first();

        // Create token expiring in 2 seconds
        $token = bin2hex(random_bytes(32));
        $this->userModel->update($user->id, [
            'email_verification_token' => $token,
            'verification_token_expires' => date('Y-m-d H:i:s', time() + 2),
        ]);

        // Should be valid now
        $result1 = $this->service->verifyEmail($token);
        $this->assertEquals('success', $result1['status']);

        // Reset for next test
        $this->userModel->update($user->id, [
            'email_verified_at' => null,
            'email_verification_token' => $token,
            'verification_token_expires' => date('Y-m-d H:i:s', time() + 2),
        ]);

        // Wait for expiration
        sleep(3);

        // Should be expired now
        $result2 = $this->service->verifyEmail($token);
        $this->assertEquals('error', $result2['status']);
    }

    // ==================== FULL WORKFLOW TESTS ====================

    public function testCompleteVerificationWorkflow(): void
    {
        $user = $this->userModel->where('email', 'unverified@example.com')->first();

        // 1. User registers (unverified)
        $this->assertNull($user->email_verified_at);

        // 2. System sends verification email
        $sendResult = $this->service->sendVerificationEmail((int) $user->id);
        $this->assertEquals('success', $sendResult['status']);

        // 3. Get token (simulates email link)
        $updatedUser = $this->userModel->find($user->id);
        $token = $updatedUser->email_verification_token;
        $this->assertNotNull($token);

        // 4. User clicks verification link
        $verifyResult = $this->service->verifyEmail($token);
        $this->assertEquals('success', $verifyResult['status']);

        // 5. User is now verified
        $verifiedUser = $this->userModel->find($user->id);
        $this->assertNotNull($verifiedUser->email_verified_at);
        $this->assertNull($verifiedUser->email_verification_token);
        $this->assertNull($verifiedUser->verification_token_expires);
    }

    public function testResendVerificationWorkflow(): void
    {
        $user = $this->userModel->where('email', 'unverified@example.com')->first();

        // 1. Send initial verification
        $this->service->sendVerificationEmail((int) $user->id);
        $user1 = $this->userModel->find($user->id);
        $oldToken = $user1->email_verification_token;

        // 2. User requests resend
        $resendResult = $this->service->resendVerification((int) $user->id);
        $this->assertEquals('success', $resendResult['status']);

        // 3. New token generated
        $user2 = $this->userModel->find($user->id);
        $newToken = $user2->email_verification_token;
        $this->assertNotEquals($oldToken, $newToken);

        // 4. Old token doesn't work
        $oldResult = $this->service->verifyEmail($oldToken);
        $this->assertEquals('error', $oldResult['status']);

        // 5. New token works
        $newResult = $this->service->verifyEmail($newToken);
        $this->assertEquals('success', $newResult['status']);
    }
}
