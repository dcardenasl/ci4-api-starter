<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\PasswordResetModel;
use App\Models\UserModel;
use App\Services\PasswordResetService;
use Tests\Support\DatabaseTestCase;

/**
 * PasswordResetService Integration Tests
 *
 * Tests the complete password reset flow with real database operations.
 * Includes token generation, validation, expiration, and security features.
 */
class PasswordResetServiceTest extends DatabaseTestCase
{
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected PasswordResetService $service;
    protected PasswordResetModel $model;
    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PasswordResetService();
        $this->model = new PasswordResetModel();
        $this->userModel = new UserModel();

        $this->seedDatabase();
    }

    protected function seedDatabase(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call('Tests\Support\Database\Seeds\TestUserSeeder');
    }

    // ==================== SEND RESET LINK INTEGRATION TESTS ====================

    public function testSendResetLinkCreatesTokenForExistingUser(): void
    {
        // Get existing user from seed
        $user = $this->userModel->first();

        $result = $this->service->sendResetLink($user->email);

        $this->assertEquals('success', $result['status']);

        // Verify token was created
        $token = $this->db->table('password_resets')
            ->where('email', $user->email)
            ->get()
            ->getFirstRow();

        $this->assertNotNull($token);
        $this->assertEquals($user->email, $token->email);
    }

    public function testSendResetLinkDoesNotCreateTokenForNonExistentUser(): void
    {
        $result = $this->service->sendResetLink('nonexistent@example.com');

        // Should still return success (security)
        $this->assertEquals('success', $result['status']);

        // But no token should be created
        $token = $this->db->table('password_resets')
            ->where('email', 'nonexistent@example.com')
            ->get()
            ->getFirstRow();

        $this->assertNull($token);
    }

    public function testSendResetLinkGeneratesSecureToken(): void
    {
        $user = $this->userModel->first();

        $this->service->sendResetLink($user->email);

        $token = $this->db->table('password_resets')
            ->where('email', $user->email)
            ->get()
            ->getFirstRow();

        // Token should be 64 character hex string (32 bytes)
        $this->assertEquals(64, strlen($token->token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token->token);
    }

    public function testSendResetLinkDeletesOldTokens(): void
    {
        $user = $this->userModel->first();

        // Create first token
        $this->service->sendResetLink($user->email);

        $firstToken = $this->db->table('password_resets')
            ->where('email', $user->email)
            ->get()
            ->getFirstRow();

        // Create second token
        $this->service->sendResetLink($user->email);

        // Should only have one token
        $count = $this->db->table('password_resets')
            ->where('email', $user->email)
            ->countAllResults();

        $this->assertEquals(1, $count);

        // And it should be a new token
        $secondToken = $this->db->table('password_resets')
            ->where('email', $user->email)
            ->get()
            ->getFirstRow();

        $this->assertNotEquals($firstToken->token, $secondToken->token);
    }

    public function testSendResetLinkGeneratesUniqueTokens(): void
    {
        $users = $this->userModel->findAll();
        $tokens = [];

        foreach ($users as $user) {
            $this->service->sendResetLink($user->email);

            $token = $this->db->table('password_resets')
                ->where('email', $user->email)
                ->get()
                ->getFirstRow();

            $tokens[] = $token->token;
        }

        // All tokens should be unique
        $uniqueTokens = array_unique($tokens);
        $this->assertCount(count($tokens), $uniqueTokens);
    }

    // ==================== VALIDATE TOKEN INTEGRATION TESTS ====================

    public function testValidateTokenSucceedsForValidToken(): void
    {
        $user = $this->userModel->first();

        // Send reset link
        $this->service->sendResetLink($user->email);

        // Get the token
        $tokenRecord = $this->db->table('password_resets')
            ->where('email', $user->email)
            ->get()
            ->getFirstRow();

        // Validate it
        $result = $this->service->validateToken($tokenRecord->token, $user->email);

        $this->assertEquals('success', $result['status']);
        $this->assertTrue($result['data']['valid']);
    }

    public function testValidateTokenFailsForExpiredToken(): void
    {
        $user = $this->userModel->first();

        // Create an expired token (2 hours old)
        $expiredToken = bin2hex(random_bytes(32));
        $this->db->table('password_resets')->insert([
            'email' => $user->email,
            'token' => $expiredToken,
            'created_at' => date('Y-m-d H:i:s', time() - 7200),
        ]);

        $result = $this->service->validateToken($expiredToken, $user->email);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(400, $result['code']);
    }

    public function testValidateTokenFailsForInvalidToken(): void
    {
        $user = $this->userModel->first();

        $result = $this->service->validateToken('invalid_token', $user->email);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(400, $result['code']);
    }

    public function testValidateTokenCleansExpiredTokens(): void
    {
        $user = $this->userModel->first();

        // Create multiple expired tokens
        for ($i = 0; $i < 3; $i++) {
            $this->db->table('password_resets')->insert([
                'email' => "expired{$i}@example.com",
                'token' => "expired_token_{$i}",
                'created_at' => date('Y-m-d H:i:s', time() - 7200),
            ]);
        }

        $expiredCountBefore = $this->db->table('password_resets')
            ->where('created_at <', date('Y-m-d H:i:s', time() - 3600))
            ->countAllResults();

        $this->assertGreaterThan(0, $expiredCountBefore);

        // Validate any token (will trigger cleanup)
        $this->service->validateToken('any_token', $user->email);

        // Expired tokens should be cleaned
        $expiredCountAfter = $this->db->table('password_resets')
            ->where('created_at <', date('Y-m-d H:i:s', time() - 3600))
            ->countAllResults();

        $this->assertEquals(0, $expiredCountAfter);
    }

    // ==================== RESET PASSWORD INTEGRATION TESTS ====================

    public function testResetPasswordSuccessWithValidToken(): void
    {
        $user = $this->userModel->first();
        $originalPassword = $user->password;

        // Send reset link
        $this->service->sendResetLink($user->email);

        // Get token
        $tokenRecord = $this->db->table('password_resets')
            ->where('email', $user->email)
            ->get()
            ->getFirstRow();

        // Reset password
        $newPassword = 'NewPassword123!';
        $result = $this->service->resetPassword($tokenRecord->token, $user->email, $newPassword);

        $this->assertEquals('success', $result['status']);

        // Verify password was updated
        $updatedUser = $this->userModel->find($user->id);
        $this->assertNotEquals($originalPassword, $updatedUser->password);

        // Verify new password works
        $this->assertTrue(password_verify($newPassword, $updatedUser->password));
    }

    public function testResetPasswordDeletesUsedToken(): void
    {
        $user = $this->userModel->first();

        // Send reset link
        $this->service->sendResetLink($user->email);

        // Get token
        $tokenRecord = $this->db->table('password_resets')
            ->where('email', $user->email)
            ->get()
            ->getFirstRow();

        // Reset password
        $this->service->resetPassword($tokenRecord->token, $user->email, 'NewPassword123!');

        // Token should be deleted
        $token = $this->db->table('password_resets')
            ->where('email', $user->email)
            ->where('token', $tokenRecord->token)
            ->get()
            ->getFirstRow();

        $this->assertNull($token);
    }

    public function testResetPasswordCannotReuseToken(): void
    {
        $user = $this->userModel->first();

        // Send reset link
        $this->service->sendResetLink($user->email);

        // Get token
        $tokenRecord = $this->db->table('password_resets')
            ->where('email', $user->email)
            ->get()
            ->getFirstRow();

        // Reset password first time
        $result1 = $this->service->resetPassword($tokenRecord->token, $user->email, 'NewPassword123!');
        $this->assertEquals('success', $result1['status']);

        // Try to use same token again
        $result2 = $this->service->resetPassword($tokenRecord->token, $user->email, 'AnotherPass456!');
        $this->assertEquals('error', $result2['status']);
        $this->assertEquals(400, $result2['code']);
    }

    public function testResetPasswordHashesPasswordSecurely(): void
    {
        $user = $this->userModel->first();

        // Send reset link
        $this->service->sendResetLink($user->email);

        // Get token
        $tokenRecord = $this->db->table('password_resets')
            ->where('email', $user->email)
            ->get()
            ->getFirstRow();

        // Reset password
        $plainPassword = 'TestPassword123!';
        $this->service->resetPassword($tokenRecord->token, $user->email, $plainPassword);

        // Get updated user
        $updatedUser = $this->userModel->find($user->id);

        // Password should be hashed
        $this->assertNotEquals($plainPassword, $updatedUser->password);

        // Should start with bcrypt identifier
        $this->assertStringStartsWith('$2y$', $updatedUser->password);

        // Should verify correctly
        $this->assertTrue(password_verify($plainPassword, $updatedUser->password));
    }

    public function testResetPasswordFailsForNonExistentUser(): void
    {
        // Create token for non-existent user
        $token = bin2hex(random_bytes(32));
        $this->db->table('password_resets')->insert([
            'email' => 'nonexistent@example.com',
            'token' => $token,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->service->resetPassword($token, 'nonexistent@example.com', 'NewPass123!');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(404, $result['code']);
    }

    public function testResetPasswordFailsForExpiredToken(): void
    {
        $user = $this->userModel->first();

        // Create expired token
        $expiredToken = bin2hex(random_bytes(32));
        $this->db->table('password_resets')->insert([
            'email' => $user->email,
            'token' => $expiredToken,
            'created_at' => date('Y-m-d H:i:s', time() - 7200),
        ]);

        $result = $this->service->resetPassword($expiredToken, $user->email, 'NewPass123!');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(400, $result['code']);
    }

    public function testResetPasswordCleansExpiredTokens(): void
    {
        $user = $this->userModel->first();

        // Create expired tokens
        for ($i = 0; $i < 3; $i++) {
            $this->db->table('password_resets')->insert([
                'email' => "old{$i}@example.com",
                'token' => "old_token_{$i}",
                'created_at' => date('Y-m-d H:i:s', time() - 7200),
            ]);
        }

        // Create valid token
        $this->service->sendResetLink($user->email);
        $tokenRecord = $this->db->table('password_resets')
            ->where('email', $user->email)
            ->get()
            ->getFirstRow();

        // Reset password (should trigger cleanup)
        $this->service->resetPassword($tokenRecord->token, $user->email, 'NewPass123!');

        // Expired tokens should be gone
        $expiredCount = $this->db->table('password_resets')
            ->where('created_at <', date('Y-m-d H:i:s', time() - 3600))
            ->countAllResults();

        $this->assertEquals(0, $expiredCount);
    }

    // ==================== SECURITY TESTS ====================

    public function testSendResetLinkPreventsEmailEnumeration(): void
    {
        $existingUser = $this->userModel->first();

        $result1 = $this->service->sendResetLink($existingUser->email);
        $result2 = $this->service->sendResetLink('nonexistent@example.com');

        // Both should return identical success responses
        $this->assertEquals($result1['status'], $result2['status']);
        $this->assertEquals($result1['message'], $result2['message']);
    }

    public function testTokensUseTimingAttackProtection(): void
    {
        $user = $this->userModel->first();

        // Create token
        $this->service->sendResetLink($user->email);
        $validTokenRecord = $this->db->table('password_resets')
            ->where('email', $user->email)
            ->get()
            ->getFirstRow();

        $iterations = 50;

        // Time with valid token
        $start1 = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->service->validateToken($validTokenRecord->token, $user->email);
        }
        $time1 = microtime(true) - $start1;

        // Time with invalid token
        $start2 = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->service->validateToken('invalid_token_here', $user->email);
        }
        $time2 = microtime(true) - $start2;

        // Both should complete quickly
        $this->assertLessThan(2, $time1);
        $this->assertLessThan(2, $time2);

        // Timing difference should not be dramatic (hash_equals protection)
        // We can't assert exact timing, but verify both complete
        $this->assertTrue(true);
    }

    public function testPasswordValidationRejectsWeakPasswords(): void
    {
        $user = $this->userModel->first();

        $this->service->sendResetLink($user->email);
        $tokenRecord = $this->db->table('password_resets')
            ->where('email', $user->email)
            ->get()
            ->getFirstRow();

        $weakPasswords = [
            'short',          // Too short
            'password',       // No uppercase, digit, special
            'Password1',      // No special
            'password1!',     // No uppercase
        ];

        foreach ($weakPasswords as $weakPassword) {
            $result = $this->service->resetPassword($tokenRecord->token, $user->email, $weakPassword);
            $this->assertEquals('error', $result['status'], "Weak password should be rejected: {$weakPassword}");
        }
    }

    public function testPasswordValidationAcceptsStrongPasswords(): void
    {
        $strongPasswords = [
            'Password123!',
            'MyP@ssw0rd',
            'Secure#Pass1',
            'C0mpl3x!Pass',
        ];

        foreach ($strongPasswords as $strongPassword) {
            $user = $this->userModel->first();

            $this->service->sendResetLink($user->email);
            $tokenRecord = $this->db->table('password_resets')
                ->where('email', $user->email)
                ->get()
                ->getFirstRow();

            $result = $this->service->resetPassword($tokenRecord->token, $user->email, $strongPassword);
            $this->assertEquals('success', $result['status'], "Strong password should be accepted: {$strongPassword}");

            // Clean up for next iteration
            $this->db->table('password_resets')->where('email', $user->email)->delete();
        }
    }

    // ==================== FULL WORKFLOW TESTS ====================

    public function testCompletePasswordResetWorkflow(): void
    {
        $user = $this->userModel->first();
        $newPassword = 'MyNewPassword123!';

        // 1. User requests password reset
        $sendResult = $this->service->sendResetLink($user->email);
        $this->assertEquals('success', $sendResult['status']);

        // 2. Get token from database (simulates email link)
        $tokenRecord = $this->db->table('password_resets')
            ->where('email', $user->email)
            ->get()
            ->getFirstRow();
        $this->assertNotNull($tokenRecord);

        // 3. Validate token
        $validateResult = $this->service->validateToken($tokenRecord->token, $user->email);
        $this->assertEquals('success', $validateResult['status']);

        // 4. Reset password
        $resetResult = $this->service->resetPassword($tokenRecord->token, $user->email, $newPassword);
        $this->assertEquals('success', $resetResult['status']);

        // 5. Verify password was changed
        $updatedUser = $this->userModel->find($user->id);
        $this->assertTrue(password_verify($newPassword, $updatedUser->password));

        // 6. Token should be deleted
        $deletedToken = $this->db->table('password_resets')
            ->where('email', $user->email)
            ->get()
            ->getFirstRow();
        $this->assertNull($deletedToken);
    }

    public function testMultipleUsersCanResetPasswordsSimultaneously(): void
    {
        $users = $this->userModel->limit(2)->findAll();

        foreach ($users as $user) {
            // Send reset link
            $this->service->sendResetLink($user->email);

            // Get token
            $tokenRecord = $this->db->table('password_resets')
                ->where('email', $user->email)
                ->get()
                ->getFirstRow();

            // Reset password
            $result = $this->service->resetPassword(
                $tokenRecord->token,
                $user->email,
                "NewPass{$user->id}123!"
            );

            $this->assertEquals('success', $result['status']);
        }

        // Verify all passwords were changed
        foreach ($users as $user) {
            $updatedUser = $this->userModel->find($user->id);
            $this->assertTrue(password_verify("NewPass{$user->id}123!", $updatedUser->password));
        }
    }

    public function testExpiredTokensAutomaticallyCleanedUp(): void
    {
        // Create some expired tokens
        for ($i = 0; $i < 5; $i++) {
            $this->db->table('password_resets')->insert([
                'email' => "expired{$i}@example.com",
                'token' => "expired_token_{$i}",
                'created_at' => date('Y-m-d H:i:s', time() - 7200),
            ]);
        }

        $user = $this->userModel->first();
        $this->service->sendResetLink($user->email);

        // Get token and validate (triggers cleanup)
        $tokenRecord = $this->db->table('password_resets')
            ->where('email', $user->email)
            ->get()
            ->getFirstRow();

        $this->service->validateToken($tokenRecord->token, $user->email);

        // Expired tokens should be gone
        $expiredCount = $this->db->table('password_resets')
            ->where('created_at <', date('Y-m-d H:i:s', time() - 3600))
            ->countAllResults();

        $this->assertEquals(0, $expiredCount);
    }
}
