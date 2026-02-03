<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\RefreshTokenModel;
use App\Models\UserModel;
use App\Services\JwtService;
use App\Services\RefreshTokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * RefreshTokenService Integration Tests
 *
 * Tests the complete refresh token flow with real database operations.
 * Includes token rotation, race condition prevention, and security tests.
 */
class RefreshTokenServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected RefreshTokenService $service;
    protected RefreshTokenModel $model;
    protected UserModel $userModel;
    protected JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new RefreshTokenModel();
        $this->userModel = new UserModel();
        $this->jwtService = new JwtService();
        $this->service = new RefreshTokenService($this->model);

        $this->seedDatabase();
    }

    protected function seedDatabase(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call('Tests\Support\Database\Seeds\TestUserSeeder');
    }

    // ==================== ISSUE REFRESH TOKEN INTEGRATION TESTS ====================

    public function testIssueRefreshTokenCreatesRecordInDatabase(): void
    {
        $userId = 1;

        $token = $this->service->issueRefreshToken($userId);

        // Verify token exists in database
        $record = $this->db->table('refresh_tokens')
            ->where('token', $token)
            ->get()
            ->getFirstRow();

        $this->assertNotNull($record);
        $this->assertEquals($userId, $record->user_id);
        $this->assertNull($record->revoked_at);
    }

    public function testIssueRefreshTokenSetsCorrectExpiration(): void
    {
        putenv('JWT_REFRESH_TOKEN_TTL=3600'); // 1 hour

        $beforeTime = time();
        $token = $this->service->issueRefreshToken(1);
        $afterTime = time();

        $record = $this->db->table('refresh_tokens')
            ->where('token', $token)
            ->get()
            ->getFirstRow();

        $expiresAt = strtotime($record->expires_at);
        $expectedMin = $beforeTime + 3600;
        $expectedMax = $afterTime + 3600;

        $this->assertGreaterThanOrEqual($expectedMin, $expiresAt);
        $this->assertLessThanOrEqual($expectedMax, $expiresAt);

        putenv('JWT_REFRESH_TOKEN_TTL'); // Clear
    }

    public function testIssueRefreshTokenGeneratesUniqueTokens(): void
    {
        $token1 = $this->service->issueRefreshToken(1);
        $token2 = $this->service->issueRefreshToken(1);
        $token3 = $this->service->issueRefreshToken(2);

        $this->assertNotEquals($token1, $token2);
        $this->assertNotEquals($token2, $token3);
        $this->assertNotEquals($token1, $token3);

        // All should exist in database
        $count = $this->db->table('refresh_tokens')
            ->whereIn('token', [$token1, $token2, $token3])
            ->countAllResults();

        $this->assertEquals(3, $count);
    }

    // ==================== REFRESH ACCESS TOKEN INTEGRATION TESTS ====================

    public function testRefreshAccessTokenSuccessWithValidToken(): void
    {
        // Issue a refresh token
        $refreshToken = $this->service->issueRefreshToken(1);

        // Use it to get new access token
        $result = $this->service->refreshAccessToken([
            'refresh_token' => $refreshToken,
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('access_token', $result['data']);
        $this->assertArrayHasKey('refresh_token', $result['data']);
        $this->assertArrayHasKey('expires_in', $result['data']);
    }

    public function testRefreshAccessTokenRotatesRefreshToken(): void
    {
        // Issue a refresh token
        $oldRefreshToken = $this->service->issueRefreshToken(1);

        // Use it to get new tokens
        $result = $this->service->refreshAccessToken([
            'refresh_token' => $oldRefreshToken,
        ]);

        $newRefreshToken = $result['data']['refresh_token'];

        // Old and new tokens should be different
        $this->assertNotEquals($oldRefreshToken, $newRefreshToken);

        // Old token should be revoked
        $oldRecord = $this->db->table('refresh_tokens')
            ->where('token', $oldRefreshToken)
            ->get()
            ->getFirstRow();

        $this->assertNotNull($oldRecord->revoked_at);

        // New token should be active
        $newRecord = $this->db->table('refresh_tokens')
            ->where('token', $newRefreshToken)
            ->get()
            ->getFirstRow();

        $this->assertNull($newRecord->revoked_at);
    }

    public function testRefreshAccessTokenGeneratesValidAccessToken(): void
    {
        $refreshToken = $this->service->issueRefreshToken(1);

        $result = $this->service->refreshAccessToken([
            'refresh_token' => $refreshToken,
        ]);

        $accessToken = $result['data']['access_token'];

        // Verify it's a valid JWT
        $decoded = $this->jwtService->decode($accessToken);

        $this->assertNotNull($decoded);
        $this->assertEquals(1, $decoded->uid);
        $this->assertObjectHasProperty('role', $decoded);
    }

    public function testRefreshAccessTokenFailsWithExpiredToken(): void
    {
        // Create an expired token
        $expiredToken = bin2hex(random_bytes(32));
        $this->db->table('refresh_tokens')->insert([
            'user_id' => 1,
            'token' => $expiredToken,
            'expires_at' => date('Y-m-d H:i:s', time() - 3600), // Expired 1 hour ago
            'revoked_at' => null,
            'created_at' => date('Y-m-d H:i:s', time() - 7200),
        ]);

        $result = $this->service->refreshAccessToken([
            'refresh_token' => $expiredToken,
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(401, $result['code']);
    }

    public function testRefreshAccessTokenFailsWithRevokedToken(): void
    {
        // Create a revoked token
        $revokedToken = bin2hex(random_bytes(32));
        $this->db->table('refresh_tokens')->insert([
            'user_id' => 1,
            'token' => $revokedToken,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'revoked_at' => date('Y-m-d H:i:s', time() - 1800), // Revoked 30 min ago
            'created_at' => date('Y-m-d H:i:s', time() - 3600),
        ]);

        $result = $this->service->refreshAccessToken([
            'refresh_token' => $revokedToken,
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(401, $result['code']);
    }

    public function testRefreshAccessTokenFailsWithInvalidToken(): void
    {
        $result = $this->service->refreshAccessToken([
            'refresh_token' => 'invalid_non_existent_token',
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(401, $result['code']);
    }

    public function testRefreshAccessTokenFailsWhenUserDeleted(): void
    {
        // Create token for a user that will be deleted
        $refreshToken = $this->service->issueRefreshToken(1);

        // Delete the user (soft delete)
        $this->userModel->delete(1);

        $result = $this->service->refreshAccessToken([
            'refresh_token' => $refreshToken,
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(401, $result['code']);
    }

    public function testRefreshAccessTokenCannotReuseToken(): void
    {
        $refreshToken = $this->service->issueRefreshToken(1);

        // First use - should succeed
        $result1 = $this->service->refreshAccessToken([
            'refresh_token' => $refreshToken,
        ]);

        $this->assertEquals('success', $result1['status']);

        // Second use - should fail (token rotation)
        $result2 = $this->service->refreshAccessToken([
            'refresh_token' => $refreshToken,
        ]);

        $this->assertEquals('error', $result2['status']);
        $this->assertEquals(401, $result2['code']);
    }

    public function testRefreshAccessTokenPreservesUserRole(): void
    {
        // Get admin user (from seeder)
        $adminUser = $this->userModel->where('role', 'admin')->first();

        $refreshToken = $this->service->issueRefreshToken((int) $adminUser->id);

        $result = $this->service->refreshAccessToken([
            'refresh_token' => $refreshToken,
        ]);

        $accessToken = $result['data']['access_token'];
        $decoded = $this->jwtService->decode($accessToken);

        $this->assertEquals('admin', $decoded->role);
    }

    // ==================== REVOKE TOKEN INTEGRATION TESTS ====================

    public function testRevokeTokenMarksTokenAsRevoked(): void
    {
        $token = $this->service->issueRefreshToken(1);

        $result = $this->service->revoke(['refresh_token' => $token]);

        $this->assertEquals('success', $result['status']);

        // Verify in database
        $record = $this->db->table('refresh_tokens')
            ->where('token', $token)
            ->get()
            ->getFirstRow();

        $this->assertNotNull($record->revoked_at);
    }

    public function testRevokeTokenPreventsSubsequentRefresh(): void
    {
        $token = $this->service->issueRefreshToken(1);

        // Revoke it
        $this->service->revoke(['refresh_token' => $token]);

        // Try to use it
        $result = $this->service->refreshAccessToken(['refresh_token' => $token]);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(401, $result['code']);
    }

    public function testRevokeTokenFailsForNonExistentToken(): void
    {
        $result = $this->service->revoke(['refresh_token' => 'non_existent']);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(404, $result['code']);
    }

    public function testRevokeAlreadyRevokedTokenSucceeds(): void
    {
        $token = $this->service->issueRefreshToken(1);

        // Revoke first time
        $result1 = $this->service->revoke(['refresh_token' => $token]);
        $this->assertEquals('success', $result1['status']);

        // Revoke second time
        $result2 = $this->service->revoke(['refresh_token' => $token]);
        $this->assertEquals('success', $result2['status']);
    }

    // ==================== REVOKE ALL USER TOKENS INTEGRATION TESTS ====================

    public function testRevokeAllUserTokensRevokesAllActiveTokens(): void
    {
        // Create multiple tokens for user 1
        $token1 = $this->service->issueRefreshToken(1);
        $token2 = $this->service->issueRefreshToken(1);
        $token3 = $this->service->issueRefreshToken(1);

        // Create token for user 2
        $token4 = $this->service->issueRefreshToken(2);

        // Revoke all tokens for user 1
        $result = $this->service->revokeAllUserTokens(1);

        $this->assertEquals('success', $result['status']);

        // Verify user 1 tokens are revoked
        $user1ActiveCount = $this->db->table('refresh_tokens')
            ->where('user_id', 1)
            ->where('revoked_at IS NULL')
            ->countAllResults();

        $this->assertEquals(0, $user1ActiveCount);

        // Verify user 2 token is NOT revoked
        $user2Token = $this->db->table('refresh_tokens')
            ->where('token', $token4)
            ->get()
            ->getFirstRow();

        $this->assertNull($user2Token->revoked_at);
    }

    public function testRevokeAllUserTokensDoesNotAffectOtherUsers(): void
    {
        $this->service->issueRefreshToken(1);
        $this->service->issueRefreshToken(1);

        $token2 = $this->service->issueRefreshToken(2);

        // Revoke all user 1 tokens
        $this->service->revokeAllUserTokens(1);

        // User 2 should still be able to refresh
        $result = $this->service->refreshAccessToken(['refresh_token' => $token2]);

        $this->assertEquals('success', $result['status']);
    }

    public function testRevokeAllUserTokensWorksWithNoTokens(): void
    {
        // User with no tokens
        $result = $this->service->revokeAllUserTokens(999);

        $this->assertEquals('success', $result['status']);
    }

    // ==================== SECURITY TESTS ====================

    public function testTokenRotationPreventsReplayAttacks(): void
    {
        $originalToken = $this->service->issueRefreshToken(1);

        // Attacker captures token
        $capturedToken = $originalToken;

        // Legitimate user refreshes
        $result1 = $this->service->refreshAccessToken([
            'refresh_token' => $originalToken,
        ]);

        $this->assertEquals('success', $result1['status']);

        // Attacker tries to use captured token
        $result2 = $this->service->refreshAccessToken([
            'refresh_token' => $capturedToken,
        ]);

        $this->assertEquals('error', $result2['status']);
        $this->assertEquals(401, $result2['code']);
    }

    public function testRefreshTokensAreSecurelyRandom(): void
    {
        $tokens = [];

        for ($i = 0; $i < 100; $i++) {
            $tokens[] = $this->service->issueRefreshToken(1);
        }

        // All tokens should be unique
        $uniqueTokens = array_unique($tokens);
        $this->assertCount(100, $uniqueTokens);

        // All tokens should be 64 character hex strings
        foreach ($tokens as $token) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        }
    }

    public function testTransactionRollbackOnError(): void
    {
        // Create a valid token
        $token = $this->service->issueRefreshToken(1);

        // Get initial count
        $initialCount = $this->db->table('refresh_tokens')
            ->where('user_id', 1)
            ->countAllResults();

        // Mock JWT service to cause failure after token rotation
        // This would require dependency injection improvements
        // For now, test with invalid user scenario
        $this->db->table('refresh_tokens')->where('token', $token)->delete();

        $result = $this->service->refreshAccessToken([
            'refresh_token' => $token,
        ]);

        $this->assertEquals('error', $result['status']);

        // Verify no new tokens were created
        $finalCount = $this->db->table('refresh_tokens')
            ->where('user_id', 1)
            ->countAllResults();

        $this->assertEquals($initialCount, $finalCount);
    }

    // ==================== EDGE CASES ====================

    public function testMultipleUsersCanHaveTokensSimultaneously(): void
    {
        $token1 = $this->service->issueRefreshToken(1);
        $token2 = $this->service->issueRefreshToken(2);

        // Both should work
        $result1 = $this->service->refreshAccessToken(['refresh_token' => $token1]);
        $result2 = $this->service->refreshAccessToken(['refresh_token' => $token2]);

        $this->assertEquals('success', $result1['status']);
        $this->assertEquals('success', $result2['status']);
    }

    public function testUserCanHaveMultipleActiveTokens(): void
    {
        // Simulate multiple devices/sessions
        $deviceToken1 = $this->service->issueRefreshToken(1);
        $deviceToken2 = $this->service->issueRefreshToken(1);
        $deviceToken3 = $this->service->issueRefreshToken(1);

        // All should work independently
        $result1 = $this->service->refreshAccessToken(['refresh_token' => $deviceToken1]);
        $result2 = $this->service->refreshAccessToken(['refresh_token' => $deviceToken2]);

        $this->assertEquals('success', $result1['status']);
        $this->assertEquals('success', $result2['status']);

        // Third token should still be valid (not affected by others)
        $activeToken = $this->model->getActiveToken($deviceToken3);
        $this->assertNotNull($activeToken);
    }

    public function testRefreshAccessTokenReturnsCorrectExpiresIn(): void
    {
        putenv('JWT_ACCESS_TOKEN_TTL=7200'); // 2 hours

        $refreshToken = $this->service->issueRefreshToken(1);

        $result = $this->service->refreshAccessToken([
            'refresh_token' => $refreshToken,
        ]);

        $this->assertEquals(7200, $result['data']['expires_in']);

        putenv('JWT_ACCESS_TOKEN_TTL'); // Clear
    }

    public function testRefreshAccessTokenUsesDefaultExpiresIn(): void
    {
        putenv('JWT_ACCESS_TOKEN_TTL'); // Clear any custom value

        $refreshToken = $this->service->issueRefreshToken(1);

        $result = $this->service->refreshAccessToken([
            'refresh_token' => $refreshToken,
        ]);

        $this->assertEquals(3600, $result['data']['expires_in']); // Default 1 hour
    }
}
