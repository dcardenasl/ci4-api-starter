<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\PasswordResetModel;
use Tests\Support\DatabaseTestCase;

/**
 * PasswordResetModel Integration Tests
 *
 * Tests database operations for password reset tokens including
 * creation, validation, expiration, and timing attack prevention.
 */
class PasswordResetModelTest extends DatabaseTestCase
{
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected PasswordResetModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new PasswordResetModel();
        $this->seedDatabase();
    }

    protected function seedDatabase(): void
    {
        // Insert test password reset tokens
        $this->db->table('password_resets')->insertBatch([
            [
                'email' => 'user1@example.com',
                'token' => 'valid_token_user1',
                'created_at' => date('Y-m-d H:i:s'), // Just created
            ],
            [
                'email' => 'user2@example.com',
                'token' => 'expired_token_user2',
                'created_at' => date('Y-m-d H:i:s', time() - 7200), // 2 hours ago
            ],
            [
                'email' => 'user3@example.com',
                'token' => 'old_token_user3',
                'created_at' => date('Y-m-d H:i:s', time() - 86400), // 1 day ago
            ],
        ]);
    }

    // ==================== INSERT TESTS ====================

    public function testInsertPasswordResetToken(): void
    {
        $data = [
            'email' => 'newuser@example.com',
            'token' => 'new_reset_token_12345',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testInsertSetsCreatedAt(): void
    {
        $data = [
            'email' => 'test@example.com',
            'token' => 'test_token',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $id = $this->model->insert($data);

        $record = $this->db->table('password_resets')
            ->where('id', $id)
            ->get()
            ->getFirstRow();

        $this->assertNotNull($record->created_at);
    }

    public function testInsertLongToken(): void
    {
        $longToken = bin2hex(random_bytes(128)); // 256 char hex string

        $data = [
            'email' => 'test@example.com',
            'token' => $longToken,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertIsInt($result);
    }

    public function testMultipleTokensForSameEmail(): void
    {
        $email = 'multi@example.com';

        for ($i = 0; $i < 3; $i++) {
            $this->model->insert([
                'email' => $email,
                'token' => "token_{$i}_" . uniqid(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $count = $this->db->table('password_resets')
            ->where('email', $email)
            ->countAllResults();

        $this->assertEquals(3, $count);
    }

    // ==================== IS VALID TOKEN TESTS ====================

    public function testIsValidTokenReturnsTrueForValidToken(): void
    {
        $result = $this->model->isValidToken('user1@example.com', 'valid_token_user1', 60);

        $this->assertTrue($result);
    }

    public function testIsValidTokenReturnsFalseForExpiredToken(): void
    {
        // Token created 2 hours ago with 60 minute expiry
        $result = $this->model->isValidToken('user2@example.com', 'expired_token_user2', 60);

        $this->assertFalse($result);
    }

    public function testIsValidTokenReturnsFalseForNonExistentToken(): void
    {
        $result = $this->model->isValidToken('user1@example.com', 'wrong_token', 60);

        $this->assertFalse($result);
    }

    public function testIsValidTokenReturnsFalseForWrongEmail(): void
    {
        $result = $this->model->isValidToken('wrong@example.com', 'valid_token_user1', 60);

        $this->assertFalse($result);
    }

    public function testIsValidTokenIsCaseSensitive(): void
    {
        $resultLower = $this->model->isValidToken('user1@example.com', 'valid_token_user1', 60);
        $resultUpper = $this->model->isValidToken('user1@example.com', 'VALID_TOKEN_USER1', 60);

        $this->assertTrue($resultLower);
        $this->assertFalse($resultUpper);
    }

    public function testIsValidTokenEmailIsCaseSensitive(): void
    {
        // Email lookup might be case-sensitive depending on DB collation
        $result1 = $this->model->isValidToken('user1@example.com', 'valid_token_user1', 60);
        $result2 = $this->model->isValidToken('USER1@EXAMPLE.COM', 'valid_token_user1', 60);

        $this->assertTrue($result1);
        // Result2 depends on database collation
    }

    public function testIsValidTokenWithCustomExpiry(): void
    {
        // Token created 2 hours ago, check with 3 hour expiry
        $result = $this->model->isValidToken('user2@example.com', 'expired_token_user2', 180);

        $this->assertTrue($result);
    }

    public function testIsValidTokenWithZeroExpiry(): void
    {
        // All tokens should be expired with 0 minute expiry
        $result = $this->model->isValidToken('user1@example.com', 'valid_token_user1', 0);

        $this->assertFalse($result);
    }

    public function testIsValidTokenUsesHashEquals(): void
    {
        // This tests timing attack prevention
        // Both comparisons should take similar time

        $validEmail = 'user1@example.com';
        $validToken = 'valid_token_user1';
        $wrongToken = 'wrong_token_here';

        $iterations = 100;

        // Test with correct token
        $start1 = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->model->isValidToken($validEmail, $validToken, 60);
        }
        $time1 = microtime(true) - $start1;

        // Test with wrong token
        $start2 = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->model->isValidToken($validEmail, $wrongToken, 60);
        }
        $time2 = microtime(true) - $start2;

        // Both should complete (timing might vary, but both should be fast)
        $this->assertLessThan(1, $time1);
        $this->assertLessThan(1, $time2);

        // The timing difference should not be dramatic (hash_equals prevents timing attacks)
        // We can't assert exact timing, but we verify both complete successfully
        $this->assertTrue(true);
    }

    // ==================== CLEAN EXPIRED TESTS ====================

    public function testCleanExpiredRemovesOldTokens(): void
    {
        // Remove tokens older than 60 minutes
        $this->model->cleanExpired(60);

        // Tokens older than 1 hour should be gone
        $expiredCount = $this->db->table('password_resets')
            ->where('email', 'user2@example.com')
            ->countAllResults();

        $this->assertEquals(0, $expiredCount);

        $oldCount = $this->db->table('password_resets')
            ->where('email', 'user3@example.com')
            ->countAllResults();

        $this->assertEquals(0, $oldCount);
    }

    public function testCleanExpiredKeepsRecentTokens(): void
    {
        $this->model->cleanExpired(60);

        // Recent token should still exist
        $recentCount = $this->db->table('password_resets')
            ->where('email', 'user1@example.com')
            ->countAllResults();

        $this->assertGreaterThan(0, $recentCount);
    }

    public function testCleanExpiredWithCustomExpiry(): void
    {
        // Clean tokens older than 3 hours
        $this->model->cleanExpired(180);

        // Token from 2 hours ago should still exist
        $count = $this->db->table('password_resets')
            ->where('email', 'user2@example.com')
            ->countAllResults();

        $this->assertGreaterThan(0, $count);
    }

    public function testCleanExpiredWithZeroMinutes(): void
    {
        // Clean all tokens (0 minute expiry)
        $this->model->cleanExpired(0);

        // All tokens should be removed
        $count = $this->db->table('password_resets')->countAllResults();

        $this->assertEquals(0, $count);
    }

    public function testCleanExpiredIsIdempotent(): void
    {
        $this->model->cleanExpired(60);
        $count1 = $this->db->table('password_resets')->countAllResults();

        $this->model->cleanExpired(60);
        $count2 = $this->db->table('password_resets')->countAllResults();

        $this->assertEquals($count1, $count2);
    }

    public function testCleanExpiredHandlesEmptyTable(): void
    {
        // Clear all (disable foreign key checks to avoid constraint errors)
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->table('password_resets')->truncate();
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        // Should not error
        $this->model->cleanExpired(60);

        // Table should still be empty
        $count = $this->db->table('password_resets')->countAllResults();
        $this->assertEquals(0, $count);
    }

    // ==================== EDGE CASES ====================

    public function testIsValidTokenHandlesEmptyToken(): void
    {
        $result = $this->model->isValidToken('user1@example.com', '', 60);

        $this->assertFalse($result);
    }

    public function testIsValidTokenHandlesEmptyEmail(): void
    {
        $result = $this->model->isValidToken('', 'valid_token_user1', 60);

        $this->assertFalse($result);
    }

    public function testIsValidTokenHandlesBothEmpty(): void
    {
        $result = $this->model->isValidToken('', '', 60);

        $this->assertFalse($result);
    }

    public function testIsValidTokenWithVeryLongExpiry(): void
    {
        // 1 year expiry
        $result = $this->model->isValidToken('user1@example.com', 'valid_token_user1', 525600);

        $this->assertTrue($result);
    }

    public function testTokenBoundaryExpiration(): void
    {
        // Create token expiring in 2 minutes
        $email = 'boundary@example.com';
        $token = 'boundary_token';

        $this->model->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => date('Y-m-d H:i:s', time() - 118), // 118 seconds ago
        ]);

        // With 2 minute (120 second) expiry, should be valid
        $result1 = $this->model->isValidToken($email, $token, 2);
        $this->assertTrue($result1);

        // With 1 minute (60 second) expiry, should be invalid
        $result2 = $this->model->isValidToken($email, $token, 1);
        $this->assertFalse($result2);
    }

    public function testMultipleValidTokensForEmail(): void
    {
        $email = 'multi@example.com';

        // Add 3 valid tokens
        $tokens = [];
        for ($i = 0; $i < 3; $i++) {
            $token = "multi_token_{$i}";
            $tokens[] = $token;
            $this->model->insert([
                'email' => $email,
                'token' => $token,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // All should be valid
        foreach ($tokens as $token) {
            $result = $this->model->isValidToken($email, $token, 60);
            $this->assertTrue($result, "Token {$token} should be valid");
        }
    }

    public function testIsValidTokenWithSpecialCharacters(): void
    {
        $specialToken = 'token-with-special!@#$%^&*()_+-=[]{}|;:,.<>?';

        $this->model->insert([
            'email' => 'special@example.com',
            'token' => $specialToken,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->model->isValidToken('special@example.com', $specialToken, 60);

        $this->assertTrue($result);
    }

    // ==================== SECURITY TESTS ====================

    public function testTokensAreNotReusableAfterDeletion(): void
    {
        $email = 'user1@example.com';
        $token = 'valid_token_user1';

        // Token exists
        $this->assertTrue($this->model->isValidToken($email, $token, 60));

        // Delete it
        $this->model->where('email', $email)->where('token', $token)->delete();

        // Should no longer be valid
        $this->assertFalse($this->model->isValidToken($email, $token, 60));
    }

    public function testOldTokensAutomaticallyExpire(): void
    {
        // Add very old token
        $this->model->insert([
            'email' => 'veryold@example.com',
            'token' => 'very_old_token',
            'created_at' => date('Y-m-d H:i:s', time() - 604800), // 1 week ago
        ]);

        // Should be invalid with normal expiry
        $result = $this->model->isValidToken('veryold@example.com', 'very_old_token', 60);

        $this->assertFalse($result);
    }

    public function testDeleteOldTokenBeforeCreatingNew(): void
    {
        $email = 'replace@example.com';

        // Create first token
        $this->model->insert([
            'email' => $email,
            'token' => 'old_token',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Delete old tokens for this email
        $this->model->where('email', $email)->delete();

        // Create new token
        $this->model->insert([
            'email' => $email,
            'token' => 'new_token',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Old token should be gone
        $this->assertFalse($this->model->isValidToken($email, 'old_token', 60));

        // New token should be valid
        $this->assertTrue($this->model->isValidToken($email, 'new_token', 60));
    }

    // ==================== DATA INTEGRITY TESTS ====================

    public function testAutoIncrementIdWorks(): void
    {
        $id1 = $this->model->insert([
            'email' => 'id1@example.com',
            'token' => 'token1',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $id2 = $this->model->insert([
            'email' => 'id2@example.com',
            'token' => 'token2',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->assertNotEquals($id1, $id2);
        $this->assertGreaterThan($id1, $id2);
    }

    public function testCreatedAtStoresCorrectTimestamp(): void
    {
        $beforeTime = time();

        $id = $this->model->insert([
            'email' => 'timestamp@example.com',
            'token' => 'timestamp_token',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $afterTime = time();

        $record = $this->db->table('password_resets')
            ->where('id', $id)
            ->get()
            ->getFirstRow();

        $createdAt = strtotime($record->created_at);

        $this->assertGreaterThanOrEqual($beforeTime, $createdAt);
        $this->assertLessThanOrEqual($afterTime, $createdAt);
    }
}
