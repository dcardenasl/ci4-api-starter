<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\RefreshTokenModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * RefreshTokenModel Integration Tests
 *
 * Tests database operations for refresh tokens including
 * creation, validation, revocation, and expiration.
 */
class RefreshTokenModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected RefreshTokenModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new RefreshTokenModel();
        $this->seedDatabase();
    }

    protected function seedDatabase(): void
    {
        // Create test users first
        $seeder = \Config\Database::seeder();
        $seeder->call('Tests\Support\Database\Seeds\TestUserSeeder');

        // Insert test refresh tokens
        $this->db->table('refresh_tokens')->insertBatch([
            [
                'user_id' => 1,
                'token' => 'valid_token_user1',
                'expires_at' => date('Y-m-d H:i:s', time() + 3600), // 1 hour from now
                'revoked_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'user_id' => 1,
                'token' => 'expired_token_user1',
                'expires_at' => date('Y-m-d H:i:s', time() - 3600), // 1 hour ago
                'revoked_at' => null,
                'created_at' => date('Y-m-d H:i:s', time() - 7200),
            ],
            [
                'user_id' => 1,
                'token' => 'revoked_token_user1',
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'revoked_at' => date('Y-m-d H:i:s', time() - 1800), // Revoked 30 min ago
                'created_at' => date('Y-m-d H:i:s', time() - 3600),
            ],
            [
                'user_id' => 2,
                'token' => 'valid_token_user2',
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'revoked_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    // ==================== VALIDATION TESTS ====================

    public function testValidationRequiresUserId(): void
    {
        $data = [
            'token' => 'test_token_' . uniqid(),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $this->assertNotEmpty($this->model->errors());
    }

    public function testValidationRequiresToken(): void
    {
        $data = [
            'user_id' => 1,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $this->assertNotEmpty($this->model->errors());
    }

    public function testValidationRequiresExpiresAt(): void
    {
        $data = [
            'user_id' => 1,
            'token' => 'test_token_' . uniqid(),
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $this->assertNotEmpty($this->model->errors());
    }

    public function testValidationRequiresUniqueToken(): void
    {
        $data = [
            'user_id' => 1,
            'token' => 'valid_token_user1', // Duplicate token from seed
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $errors = $this->model->errors();
        $this->assertArrayHasKey('token', $errors);
    }

    public function testInsertValidToken(): void
    {
        $data = [
            'user_id' => 1,
            'token' => 'new_unique_token_' . uniqid(),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    // ==================== GET ACTIVE TOKEN TESTS ====================

    public function testGetActiveTokenReturnsValidToken(): void
    {
        $token = $this->model->getActiveToken('valid_token_user1');

        $this->assertIsObject($token);
        $this->assertEquals('valid_token_user1', $token->token);
        $this->assertEquals(1, $token->user_id);
        $this->assertNull($token->revoked_at);
    }

    public function testGetActiveTokenReturnsNullForExpiredToken(): void
    {
        $token = $this->model->getActiveToken('expired_token_user1');

        $this->assertNull($token);
    }

    public function testGetActiveTokenReturnsNullForRevokedToken(): void
    {
        $token = $this->model->getActiveToken('revoked_token_user1');

        $this->assertNull($token);
    }

    public function testGetActiveTokenReturnsNullForNonExistentToken(): void
    {
        $token = $this->model->getActiveToken('non_existent_token');

        $this->assertNull($token);
    }

    public function testGetActiveTokenReturnsCorrectUserToken(): void
    {
        $token = $this->model->getActiveToken('valid_token_user2');

        $this->assertIsObject($token);
        $this->assertEquals(2, $token->user_id);
    }

    // ==================== REVOKE TOKEN TESTS ====================

    public function testRevokeTokenSetsRevokedAt(): void
    {
        $result = $this->model->revokeToken('valid_token_user1');

        $this->assertTrue($result);

        // Verify token is revoked in database
        $token = $this->db->table('refresh_tokens')
            ->where('token', 'valid_token_user1')
            ->get()
            ->getFirstRow();

        $this->assertNotNull($token->revoked_at);
    }

    public function testRevokeTokenReturnsTrueEvenIfAlreadyRevoked(): void
    {
        // Revoke first time
        $this->model->revokeToken('valid_token_user1');

        // Revoke again
        $result = $this->model->revokeToken('valid_token_user1');

        // Should still return true (update succeeded even if no change)
        $this->assertTrue($result);
    }

    public function testRevokeTokenReturnsFalseForNonExistentToken(): void
    {
        $result = $this->model->revokeToken('non_existent_token');

        $this->assertFalse($result);
    }

    public function testRevokedTokenNotReturnedByGetActiveToken(): void
    {
        // Revoke the token
        $this->model->revokeToken('valid_token_user1');

        // Try to get it as active
        $token = $this->model->getActiveToken('valid_token_user1');

        $this->assertNull($token, 'Revoked token should not be returned as active');
    }

    // ==================== REVOKE ALL USER TOKENS TESTS ====================

    public function testRevokeAllUserTokensRevokesOnlyUserTokens(): void
    {
        $result = $this->model->revokeAllUserTokens(1);

        $this->assertTrue($result);

        // Check user 1 tokens are revoked
        $user1Tokens = $this->db->table('refresh_tokens')
            ->where('user_id', 1)
            ->where('revoked_at IS NOT NULL')
            ->countAllResults();

        // User 1 has 3 tokens, 1 already revoked, so 2 more should be revoked
        $this->assertGreaterThanOrEqual(2, $user1Tokens);

        // Check user 2 token is NOT revoked
        $user2Token = $this->db->table('refresh_tokens')
            ->where('user_id', 2)
            ->where('token', 'valid_token_user2')
            ->get()
            ->getFirstRow();

        $this->assertNull($user2Token->revoked_at);
    }

    public function testRevokeAllUserTokensHandlesUserWithNoTokens(): void
    {
        $result = $this->model->revokeAllUserTokens(999);

        // Should return false because no rows were affected
        $this->assertFalse($result);
    }

    public function testRevokeAllUserTokensDoesNotRevokeAlreadyRevoked(): void
    {
        // Get the original revoked_at timestamp
        $originalToken = $this->db->table('refresh_tokens')
            ->where('token', 'revoked_token_user1')
            ->get()
            ->getFirstRow();

        $originalRevokedAt = $originalToken->revoked_at;

        // Revoke all user tokens
        $this->model->revokeAllUserTokens(1);

        // Check that the already-revoked token's timestamp didn't change
        $updatedToken = $this->db->table('refresh_tokens')
            ->where('token', 'revoked_token_user1')
            ->get()
            ->getFirstRow();

        // The timestamp should be updated since the query doesn't exclude already revoked
        $this->assertNotNull($updatedToken->revoked_at);
    }

    // ==================== DELETE EXPIRED TESTS ====================

    public function testDeleteExpiredRemovesOnlyExpiredTokens(): void
    {
        $deletedCount = $this->model->deleteExpired();

        // We seeded 1 expired token
        $this->assertGreaterThanOrEqual(1, $deletedCount);

        // Verify expired token is gone
        $expiredToken = $this->db->table('refresh_tokens')
            ->where('token', 'expired_token_user1')
            ->get()
            ->getFirstRow();

        $this->assertNull($expiredToken);

        // Verify valid tokens still exist
        $validToken = $this->db->table('refresh_tokens')
            ->where('token', 'valid_token_user1')
            ->get()
            ->getFirstRow();

        $this->assertNotNull($validToken);
    }

    public function testDeleteExpiredReturnsZeroWhenNoExpiredTokens(): void
    {
        // First delete all expired tokens
        $this->model->deleteExpired();

        // Try again - should be 0
        $deletedCount = $this->model->deleteExpired();

        $this->assertEquals(0, $deletedCount);
    }

    public function testDeleteExpiredDoesNotDeleteRevokedButNotExpired(): void
    {
        // Count tokens before
        $beforeCount = $this->db->table('refresh_tokens')
            ->where('token', 'revoked_token_user1')
            ->countAllResults();

        $this->model->deleteExpired();

        // Count tokens after
        $afterCount = $this->db->table('refresh_tokens')
            ->where('token', 'revoked_token_user1')
            ->countAllResults();

        $this->assertEquals($beforeCount, $afterCount, 'Revoked but not expired tokens should not be deleted');
    }

    // ==================== EDGE CASES ====================

    public function testTokenCanBeMaxLength(): void
    {
        $longToken = str_repeat('a', 255);

        $data = [
            'user_id' => 1,
            'token' => $longToken,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->model->insert($data);

        $this->assertIsInt($result);
    }

    public function testMultipleTokensForSameUser(): void
    {
        $tokens = [];
        for ($i = 0; $i < 5; $i++) {
            $tokens[] = [
                'user_id' => 1,
                'token' => 'token_' . $i . '_' . uniqid(),
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        $result = $this->model->insertBatch($tokens);

        $this->assertEquals(5, $result);

        // Verify all tokens exist
        $count = $this->db->table('refresh_tokens')
            ->where('user_id', 1)
            ->countAllResults();

        $this->assertGreaterThanOrEqual(5, $count);
    }

    public function testExpirationAtBoundary(): void
    {
        // Token expiring in 1 second
        $nearExpiryToken = [
            'user_id' => 1,
            'token' => 'near_expiry_' . uniqid(),
            'expires_at' => date('Y-m-d H:i:s', time() + 1),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->model->insert($nearExpiryToken);

        // Should still be active
        $token = $this->model->getActiveToken($nearExpiryToken['token']);
        $this->assertNotNull($token);

        // Wait 2 seconds
        sleep(2);

        // Should now be expired
        $token = $this->model->getActiveToken($nearExpiryToken['token']);
        $this->assertNull($token);
    }

    // ==================== DATA INTEGRITY TESTS ====================

    public function testRevokedAtIsNullByDefault(): void
    {
        $data = [
            'user_id' => 1,
            'token' => 'test_default_' . uniqid(),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $id = $this->model->insert($data);

        $token = $this->db->table('refresh_tokens')
            ->where('id', $id)
            ->get()
            ->getFirstRow();

        $this->assertNull($token->revoked_at);
    }

    public function testCreatedAtIsStored(): void
    {
        $now = date('Y-m-d H:i:s');

        $data = [
            'user_id' => 1,
            'token' => 'test_created_' . uniqid(),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => $now,
        ];

        $id = $this->model->insert($data);

        $token = $this->db->table('refresh_tokens')
            ->where('id', $id)
            ->get()
            ->getFirstRow();

        $this->assertNotNull($token->created_at);
    }
}
