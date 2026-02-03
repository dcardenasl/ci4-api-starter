<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\RefreshTokenModel;
use App\Models\TokenBlacklistModel;
use App\Services\TokenRevocationService;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * TokenRevocationService Unit Tests
 *
 * Comprehensive test coverage for token revocation operations.
 * Tests blacklist management, cache behavior, and cleanup.
 */
class TokenRevocationServiceTest extends CIUnitTestCase
{
    protected TokenRevocationService $service;
    protected TokenBlacklistModel $mockBlacklistModel;
    protected RefreshTokenModel $mockRefreshTokenModel;
    protected CacheInterface $mockCache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockBlacklistModel = $this->createMock(TokenBlacklistModel::class);
        $this->mockRefreshTokenModel = $this->createMock(RefreshTokenModel::class);

        $this->service = new TokenRevocationService(
            $this->mockBlacklistModel,
            $this->mockRefreshTokenModel
        );
    }

    // ==================== REVOKE TOKEN TESTS ====================

    public function testRevokeTokenAddsToBlacklist(): void
    {
        $jti = 'test-jti-123';
        $expiresAt = time() + 3600;

        $this->mockBlacklistModel->expects($this->once())
            ->method('addToBlacklist')
            ->with($jti, $expiresAt)
            ->willReturn(true);

        $result = $this->service->revokeToken($jti, $expiresAt);

        $this->assertEquals('success', $result['status']);
    }

    public function testRevokeTokenReturnsErrorWhenAddFails(): void
    {
        $jti = 'test-jti-456';
        $expiresAt = time() + 3600;

        $this->mockBlacklistModel->expects($this->once())
            ->method('addToBlacklist')
            ->with($jti, $expiresAt)
            ->willReturn(false);

        $result = $this->service->revokeToken($jti, $expiresAt);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('errors', $result);
    }

    public function testRevokeTokenHandlesLongJti(): void
    {
        $longJti = str_repeat('a', 255);
        $expiresAt = time() + 3600;

        $this->mockBlacklistModel->expects($this->once())
            ->method('addToBlacklist')
            ->with($longJti, $expiresAt)
            ->willReturn(true);

        $result = $this->service->revokeToken($longJti, $expiresAt);

        $this->assertEquals('success', $result['status']);
    }

    public function testRevokeTokenHandlesFutureExpiration(): void
    {
        $jti = 'future-jti';
        $futureTimestamp = time() + 86400 * 365; // 1 year

        $this->mockBlacklistModel->expects($this->once())
            ->method('addToBlacklist')
            ->with($jti, $futureTimestamp)
            ->willReturn(true);

        $result = $this->service->revokeToken($jti, $futureTimestamp);

        $this->assertEquals('success', $result['status']);
    }

    // ==================== IS REVOKED TESTS ====================

    public function testIsRevokedReturnsTrueWhenBlacklisted(): void
    {
        $jti = 'blacklisted-jti';

        $this->mockBlacklistModel->expects($this->once())
            ->method('isBlacklisted')
            ->with($jti)
            ->willReturn(true);

        $result = $this->service->isRevoked($jti);

        $this->assertTrue($result);
    }

    public function testIsRevokedReturnsFalseWhenNotBlacklisted(): void
    {
        $jti = 'valid-jti';

        $this->mockBlacklistModel->expects($this->once())
            ->method('isBlacklisted')
            ->with($jti)
            ->willReturn(false);

        $result = $this->service->isRevoked($jti);

        $this->assertFalse($result);
    }

    public function testIsRevokedHandlesEmptyJti(): void
    {
        $this->mockBlacklistModel->expects($this->once())
            ->method('isBlacklisted')
            ->with('')
            ->willReturn(false);

        $result = $this->service->isRevoked('');

        $this->assertFalse($result);
    }

    // ==================== REVOKE ALL USER TOKENS TESTS ====================

    public function testRevokeAllUserTokensCallsRefreshTokenModel(): void
    {
        $userId = 42;

        $this->mockRefreshTokenModel->expects($this->once())
            ->method('revokeAllUserTokens')
            ->with($userId)
            ->willReturn(true);

        $result = $this->service->revokeAllUserTokens($userId);

        $this->assertEquals('success', $result['status']);
    }

    public function testRevokeAllUserTokensReturnsSuccess(): void
    {
        $userId = 1;

        $this->mockRefreshTokenModel->expects($this->once())
            ->method('revokeAllUserTokens')
            ->willReturn(true);

        $result = $this->service->revokeAllUserTokens($userId);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testRevokeAllUserTokensHandlesZeroUserId(): void
    {
        $this->mockRefreshTokenModel->expects($this->once())
            ->method('revokeAllUserTokens')
            ->with(0)
            ->willReturn(true);

        $result = $this->service->revokeAllUserTokens(0);

        $this->assertEquals('success', $result['status']);
    }

    public function testRevokeAllUserTokensHandlesLargeUserId(): void
    {
        $largeId = PHP_INT_MAX;

        $this->mockRefreshTokenModel->expects($this->once())
            ->method('revokeAllUserTokens')
            ->with($largeId)
            ->willReturn(true);

        $result = $this->service->revokeAllUserTokens($largeId);

        $this->assertEquals('success', $result['status']);
    }

    // ==================== CLEANUP EXPIRED TESTS ====================

    public function testCleanupExpiredCallsBothModels(): void
    {
        $this->mockBlacklistModel->expects($this->once())
            ->method('deleteExpired')
            ->willReturn(5);

        $this->mockRefreshTokenModel->expects($this->once())
            ->method('deleteExpired')
            ->willReturn(3);

        $result = $this->service->cleanupExpired();

        $this->assertEquals(8, $result);
    }

    public function testCleanupExpiredReturnsZeroWhenNothingDeleted(): void
    {
        $this->mockBlacklistModel->expects($this->once())
            ->method('deleteExpired')
            ->willReturn(0);

        $this->mockRefreshTokenModel->expects($this->once())
            ->method('deleteExpired')
            ->willReturn(0);

        $result = $this->service->cleanupExpired();

        $this->assertEquals(0, $result);
    }

    public function testCleanupExpiredHandlesOnlyBlacklistDeletions(): void
    {
        $this->mockBlacklistModel->expects($this->once())
            ->method('deleteExpired')
            ->willReturn(10);

        $this->mockRefreshTokenModel->expects($this->once())
            ->method('deleteExpired')
            ->willReturn(0);

        $result = $this->service->cleanupExpired();

        $this->assertEquals(10, $result);
    }

    public function testCleanupExpiredHandlesOnlyRefreshTokenDeletions(): void
    {
        $this->mockBlacklistModel->expects($this->once())
            ->method('deleteExpired')
            ->willReturn(0);

        $this->mockRefreshTokenModel->expects($this->once())
            ->method('deleteExpired')
            ->willReturn(7);

        $result = $this->service->cleanupExpired();

        $this->assertEquals(7, $result);
    }

    public function testCleanupExpiredHandlesLargeDeletions(): void
    {
        $this->mockBlacklistModel->expects($this->once())
            ->method('deleteExpired')
            ->willReturn(1000);

        $this->mockRefreshTokenModel->expects($this->once())
            ->method('deleteExpired')
            ->willReturn(500);

        $result = $this->service->cleanupExpired();

        $this->assertEquals(1500, $result);
    }

    // ==================== EDGE CASES ====================

    public function testRevokeTokenHandlesSpecialCharactersInJti(): void
    {
        $specialJti = 'jti-with-special-chars-!@#$%^&*()';
        $expiresAt = time() + 3600;

        $this->mockBlacklistModel->expects($this->once())
            ->method('addToBlacklist')
            ->with($specialJti, $expiresAt)
            ->willReturn(true);

        $result = $this->service->revokeToken($specialJti, $expiresAt);

        $this->assertEquals('success', $result['status']);
    }

    public function testIsRevokedConsistentForSameJti(): void
    {
        $jti = 'consistent-jti';

        $this->mockBlacklistModel->expects($this->exactly(2))
            ->method('isBlacklisted')
            ->with($jti)
            ->willReturn(true);

        $result1 = $this->service->isRevoked($jti);

        // Clear cache to force DB check again
        \Config\Services::cache()->delete("token_revoked_{$jti}");

        $result2 = $this->service->isRevoked($jti);

        $this->assertEquals($result1, $result2);
    }

    public function testRevokeTokenHandlesPastExpiration(): void
    {
        $jti = 'past-jti';
        $pastTimestamp = time() - 3600; // 1 hour ago

        $this->mockBlacklistModel->expects($this->once())
            ->method('addToBlacklist')
            ->with($jti, $pastTimestamp)
            ->willReturn(true);

        $result = $this->service->revokeToken($jti, $pastTimestamp);

        $this->assertEquals('success', $result['status']);
    }

    // ==================== INTEGRATION PATTERN TESTS ====================

    public function testTypicalRevocationWorkflow(): void
    {
        $jti = 'workflow-jti';
        $expiresAt = time() + 3600;

        // 1. Revoke token
        $this->mockBlacklistModel->expects($this->once())
            ->method('addToBlacklist')
            ->with($jti, $expiresAt)
            ->willReturn(true);

        $revokeResult = $this->service->revokeToken($jti, $expiresAt);
        $this->assertEquals('success', $revokeResult['status']);

        // 2. Check if revoked
        $this->mockBlacklistModel->expects($this->once())
            ->method('isBlacklisted')
            ->with($jti)
            ->willReturn(true);

        $isRevoked = $this->service->isRevoked($jti);
        $this->assertTrue($isRevoked);
    }

    public function testRevokeMultipleTokensSequentially(): void
    {
        $tokens = [
            'jti-1' => time() + 3600,
            'jti-2' => time() + 7200,
            'jti-3' => time() + 1800,
        ];

        $this->mockBlacklistModel->expects($this->exactly(3))
            ->method('addToBlacklist')
            ->willReturn(true);

        foreach ($tokens as $jti => $expiresAt) {
            $result = $this->service->revokeToken($jti, $expiresAt);
            $this->assertEquals('success', $result['status']);
        }
    }
}
