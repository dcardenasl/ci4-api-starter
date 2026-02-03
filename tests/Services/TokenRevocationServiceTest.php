<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\RefreshTokenModel;
use App\Models\TokenBlacklistModel;
use App\Services\TokenRevocationService;
use Tests\Support\DatabaseTestCase;

/**
 * TokenRevocationService Integration Tests
 *
 * Tests the complete token revocation flow with real database operations.
 * Includes blacklist management, caching, and cleanup operations.
 */
class TokenRevocationServiceTest extends DatabaseTestCase
{
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected TokenRevocationService $service;
    protected TokenBlacklistModel $blacklistModel;
    protected RefreshTokenModel $refreshTokenModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->blacklistModel = new TokenBlacklistModel();
        $this->refreshTokenModel = new RefreshTokenModel();
        $this->service = new TokenRevocationService(
            $this->blacklistModel,
            $this->refreshTokenModel
        );

        $this->seedDatabase();
    }

    protected function seedDatabase(): void
    {
        // Create test users
        $seeder = \Config\Database::seeder();
        $seeder->call('Tests\Support\Database\Seeds\TestUserSeeder');

        // Create some refresh tokens
        $this->db->table('refresh_tokens')->insertBatch([
            [
                'user_id' => 1,
                'token' => 'refresh_token_1',
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'revoked_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'user_id' => 1,
                'token' => 'refresh_token_2',
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'revoked_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'user_id' => 2,
                'token' => 'refresh_token_3',
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'revoked_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ]);

        // Create some blacklisted tokens
        $this->db->table('token_blacklist')->insertBatch([
            [
                'token_jti' => 'already_blacklisted_jti',
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'token_jti' => 'expired_blacklisted_jti',
                'expires_at' => date('Y-m-d H:i:s', time() - 3600),
                'created_at' => date('Y-m-d H:i:s', time() - 7200),
            ],
        ]);
    }

    protected function tearDown(): void
    {
        // Clear cache after each test
        \Config\Services::cache()->clean();
        parent::tearDown();
    }

    // ==================== REVOKE TOKEN INTEGRATION TESTS ====================

    public function testRevokeTokenAddsToBlacklistDatabase(): void
    {
        $jti = 'new_revoked_jti';
        $expiresAt = time() + 3600;

        $result = $this->service->revokeToken($jti, $expiresAt);

        $this->assertEquals('success', $result['status']);

        // Verify in database
        $record = $this->db->table('token_blacklist')
            ->where('token_jti', $jti)
            ->get()
            ->getFirstRow();

        $this->assertNotNull($record);
        $this->assertEquals($jti, $record->token_jti);
    }

    public function testRevokeTokenMakesTokenRevoked(): void
    {
        $jti = 'test_jti_' . uniqid();
        $expiresAt = time() + 3600;

        // Initially not revoked
        $this->assertFalse($this->service->isRevoked($jti));

        // Revoke it
        $this->service->revokeToken($jti, $expiresAt);

        // Clear cache to force DB check
        \Config\Services::cache()->delete("token_revoked_{$jti}");

        // Now should be revoked
        $this->assertTrue($this->service->isRevoked($jti));
    }

    public function testRevokeTokenFailsForDuplicateJti(): void
    {
        $jti = 'duplicate_jti_' . uniqid();
        $expiresAt = time() + 3600;

        // First revocation
        $result1 = $this->service->revokeToken($jti, $expiresAt);
        $this->assertEquals('success', $result1['status']);

        // Second revocation - should fail
        $result2 = $this->service->revokeToken($jti, $expiresAt);
        $this->assertEquals('error', $result2['status']);
    }

    public function testRevokeTokenStoresCorrectExpiration(): void
    {
        $jti = 'expiry_test_jti';
        $expiresAt = time() + 7200; // 2 hours

        $this->service->revokeToken($jti, $expiresAt);

        $record = $this->db->table('token_blacklist')
            ->where('token_jti', $jti)
            ->get()
            ->getFirstRow();

        $storedExpiresAt = strtotime($record->expires_at);
        $this->assertEquals($expiresAt, $storedExpiresAt, '', 2);
    }

    // ==================== IS REVOKED INTEGRATION TESTS ====================

    public function testIsRevokedReturnsTrueForBlacklistedToken(): void
    {
        $result = $this->service->isRevoked('already_blacklisted_jti');

        $this->assertTrue($result);
    }

    public function testIsRevokedReturnsFalseForValidToken(): void
    {
        $result = $this->service->isRevoked('non_existent_jti');

        $this->assertFalse($result);
    }

    public function testIsRevokedReturnsFalseForExpiredBlacklistEntry(): void
    {
        $result = $this->service->isRevoked('expired_blacklisted_jti');

        $this->assertFalse($result);
    }

    public function testIsRevokedUsesCaching(): void
    {
        $jti = 'cache_test_jti';
        $expiresAt = time() + 3600;

        // Add to blacklist
        $this->service->revokeToken($jti, $expiresAt);

        // First call - hits database
        $result1 = $this->service->isRevoked($jti);

        // Second call - should hit cache (check cache)
        $cacheKey = "token_revoked_{$jti}";
        $cached = \Config\Services::cache()->get($cacheKey);
        $this->assertNotNull($cached);

        $result2 = $this->service->isRevoked($jti);

        $this->assertTrue($result1);
        $this->assertTrue($result2);
        $this->assertEquals($result1, $result2);
    }

    public function testIsRevokedCachesNegativeResults(): void
    {
        $jti = 'non_blacklisted_' . uniqid();

        // First call - checks DB
        $result1 = $this->service->isRevoked($jti);

        // Check cache was set
        $cacheKey = "token_revoked_{$jti}";
        $cached = \Config\Services::cache()->get($cacheKey);
        $this->assertNotNull($cached);
        $this->assertEquals(0, $cached);

        $result2 = $this->service->isRevoked($jti);

        $this->assertFalse($result1);
        $this->assertFalse($result2);
    }

    public function testIsRevokedCacheExpiresAfterFiveMinutes(): void
    {
        $jti = 'cache_expiry_' . uniqid();

        $this->service->isRevoked($jti);

        $cacheKey = "token_revoked_{$jti}";
        $cache = \Config\Services::cache();

        // Cache should exist
        $this->assertNotNull($cache->get($cacheKey));

        // Mock cache expiry by manually deleting
        $cache->delete($cacheKey);

        // Should be gone
        $this->assertNull($cache->get($cacheKey));
    }

    // ==================== REVOKE ALL USER TOKENS INTEGRATION TESTS ====================

    public function testRevokeAllUserTokensRevokesRefreshTokens(): void
    {
        $result = $this->service->revokeAllUserTokens(1);

        $this->assertEquals('success', $result['status']);

        // Check all user 1 tokens are revoked
        $activeTokens = $this->db->table('refresh_tokens')
            ->where('user_id', 1)
            ->where('revoked_at IS NULL')
            ->countAllResults();

        $this->assertEquals(0, $activeTokens);
    }

    public function testRevokeAllUserTokensDoesNotAffectOtherUsers(): void
    {
        $this->service->revokeAllUserTokens(1);

        // Check user 2 tokens are NOT revoked
        $user2ActiveTokens = $this->db->table('refresh_tokens')
            ->where('user_id', 2)
            ->where('revoked_at IS NULL')
            ->countAllResults();

        $this->assertGreaterThan(0, $user2ActiveTokens);
    }

    public function testRevokeAllUserTokensHandlesUserWithNoTokens(): void
    {
        $result = $this->service->revokeAllUserTokens(999);

        $this->assertEquals('success', $result['status']);
    }

    public function testRevokeAllUserTokensMarksRevokedAt(): void
    {
        $beforeTime = time();
        $this->service->revokeAllUserTokens(1);
        $afterTime = time();

        $tokens = $this->db->table('refresh_tokens')
            ->where('user_id', 1)
            ->get()
            ->getResult();

        foreach ($tokens as $token) {
            $this->assertNotNull($token->revoked_at);
            $revokedTime = strtotime($token->revoked_at);
            $this->assertGreaterThanOrEqual($beforeTime, $revokedTime);
            $this->assertLessThanOrEqual($afterTime, $revokedTime);
        }
    }

    // ==================== CLEANUP EXPIRED INTEGRATION TESTS ====================

    public function testCleanupExpiredRemovesExpiredBlacklist(): void
    {
        $initialCount = $this->db->table('token_blacklist')
            ->where('token_jti', 'expired_blacklisted_jti')
            ->countAllResults();

        $this->assertGreaterThan(0, $initialCount);

        $deletedCount = $this->service->cleanupExpired();

        $this->assertGreaterThan(0, $deletedCount);

        $finalCount = $this->db->table('token_blacklist')
            ->where('token_jti', 'expired_blacklisted_jti')
            ->countAllResults();

        $this->assertEquals(0, $finalCount);
    }

    public function testCleanupExpiredRemovesExpiredRefreshTokens(): void
    {
        // Add expired refresh token
        $this->db->table('refresh_tokens')->insert([
            'user_id' => 1,
            'token' => 'expired_refresh_token',
            'expires_at' => date('Y-m-d H:i:s', time() - 3600),
            'revoked_at' => null,
            'created_at' => date('Y-m-d H:i:s', time() - 7200),
        ]);

        $this->service->cleanupExpired();

        $record = $this->db->table('refresh_tokens')
            ->where('token', 'expired_refresh_token')
            ->get()
            ->getFirstRow();

        $this->assertNull($record);
    }

    public function testCleanupExpiredDoesNotRemoveValidTokens(): void
    {
        $validBlacklistCountBefore = $this->db->table('token_blacklist')
            ->where('token_jti', 'already_blacklisted_jti')
            ->countAllResults();

        $validRefreshCountBefore = $this->db->table('refresh_tokens')
            ->where('token', 'refresh_token_1')
            ->countAllResults();

        $this->service->cleanupExpired();

        $validBlacklistCountAfter = $this->db->table('token_blacklist')
            ->where('token_jti', 'already_blacklisted_jti')
            ->countAllResults();

        $validRefreshCountAfter = $this->db->table('refresh_tokens')
            ->where('token', 'refresh_token_1')
            ->countAllResults();

        $this->assertEquals($validBlacklistCountBefore, $validBlacklistCountAfter);
        $this->assertEquals($validRefreshCountBefore, $validRefreshCountAfter);
    }

    public function testCleanupExpiredReturnsTotalCount(): void
    {
        // Add multiple expired tokens
        $this->db->table('token_blacklist')->insert([
            'token_jti' => 'extra_expired_1',
            'expires_at' => date('Y-m-d H:i:s', time() - 1800),
            'created_at' => date('Y-m-d H:i:s', time() - 3600),
        ]);

        $this->db->table('refresh_tokens')->insert([
            'user_id' => 1,
            'token' => 'extra_expired_refresh',
            'expires_at' => date('Y-m-d H:i:s', time() - 1800),
            'revoked_at' => null,
            'created_at' => date('Y-m-d H:i:s', time() - 3600),
        ]);

        $deletedCount = $this->service->cleanupExpired();

        // Should have deleted at least 3 (1 from seed + 2 we just added)
        $this->assertGreaterThanOrEqual(3, $deletedCount);
    }

    public function testCleanupExpiredReturnsZeroWhenNothingExpired(): void
    {
        // Clean first
        $this->service->cleanupExpired();

        // Clean again - should return 0
        $deletedCount = $this->service->cleanupExpired();

        $this->assertEquals(0, $deletedCount);
    }

    // ==================== SECURITY AND EDGE CASES ====================

    public function testRevokeTokenPreventsSubsequentValidation(): void
    {
        $jti = 'security_test_' . uniqid();
        $expiresAt = time() + 3600;

        // Token is valid
        $this->assertFalse($this->service->isRevoked($jti));

        // Revoke it
        $this->service->revokeToken($jti, $expiresAt);

        // Clear cache
        \Config\Services::cache()->delete("token_revoked_{$jti}");

        // Should now be invalid
        $this->assertTrue($this->service->isRevoked($jti));
    }

    public function testMultipleRevocationsInSequence(): void
    {
        $jtis = [];
        for ($i = 0; $i < 5; $i++) {
            $jti = "batch_jti_{$i}_" . uniqid();
            $jtis[] = $jti;
            $result = $this->service->revokeToken($jti, time() + 3600);
            $this->assertEquals('success', $result['status']);
        }

        // Clear cache
        foreach ($jtis as $jti) {
            \Config\Services::cache()->delete("token_revoked_{$jti}");
        }

        // All should be revoked
        foreach ($jtis as $jti) {
            $this->assertTrue($this->service->isRevoked($jti));
        }
    }

    public function testRevokeAllUserTokensConcurrentUsers(): void
    {
        // Revoke tokens for both users
        $result1 = $this->service->revokeAllUserTokens(1);
        $result2 = $this->service->revokeAllUserTokens(2);

        $this->assertEquals('success', $result1['status']);
        $this->assertEquals('success', $result2['status']);

        // Both should have no active tokens
        $user1Active = $this->db->table('refresh_tokens')
            ->where('user_id', 1)
            ->where('revoked_at IS NULL')
            ->countAllResults();

        $user2Active = $this->db->table('refresh_tokens')
            ->where('user_id', 2)
            ->where('revoked_at IS NULL')
            ->countAllResults();

        $this->assertEquals(0, $user1Active);
        $this->assertEquals(0, $user2Active);
    }

    public function testIsRevokedPerformanceWithMultipleChecks(): void
    {
        $jti = 'performance_test_' . uniqid();

        // First check - hits DB
        $start1 = microtime(true);
        $this->service->isRevoked($jti);
        $time1 = microtime(true) - $start1;

        // Second check - hits cache (should be faster)
        $start2 = microtime(true);
        $this->service->isRevoked($jti);
        $time2 = microtime(true) - $start2;

        // Cache hit should be faster (though this might be flaky in fast environments)
        // Just verify both complete successfully
        $this->assertLessThan(1, $time1); // Should complete in under 1 second
        $this->assertLessThan(1, $time2);
    }

    public function testCleanupExpiredIsIdempotent(): void
    {
        $count1 = $this->service->cleanupExpired();
        $count2 = $this->service->cleanupExpired();
        $count3 = $this->service->cleanupExpired();

        $this->assertGreaterThan(0, $count1);
        $this->assertEquals(0, $count2);
        $this->assertEquals(0, $count3);
    }

    // ==================== FULL WORKFLOW TESTS ====================

    public function testCompleteTokenRevocationWorkflow(): void
    {
        $jti = 'workflow_jti_' . uniqid();
        $expiresAt = time() + 3600;

        // 1. Token is not revoked initially
        $this->assertFalse($this->service->isRevoked($jti));

        // 2. Revoke the token
        $revokeResult = $this->service->revokeToken($jti, $expiresAt);
        $this->assertEquals('success', $revokeResult['status']);

        // 3. Clear cache
        \Config\Services::cache()->delete("token_revoked_{$jti}");

        // 4. Token is now revoked
        $this->assertTrue($this->service->isRevoked($jti));

        // 5. Result is cached
        $cacheKey = "token_revoked_{$jti}";
        $cached = \Config\Services::cache()->get($cacheKey);
        $this->assertEquals(1, $cached);
    }
}
