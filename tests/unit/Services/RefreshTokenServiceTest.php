<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\RefreshTokenModel;
use App\Services\RefreshTokenService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * RefreshTokenService Unit Tests
 *
 * Comprehensive test coverage for refresh token operations.
 * Tests token issuance, refresh, revocation, and security aspects.
 */
class RefreshTokenServiceTest extends CIUnitTestCase
{
    protected RefreshTokenService $service;
    protected RefreshTokenModel $mockModel;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the model
        $this->mockModel = $this->createMock(RefreshTokenModel::class);
        $this->service = new RefreshTokenService($this->mockModel);
    }

    // ==================== ISSUE REFRESH TOKEN TESTS ====================

    public function testIssueRefreshTokenGeneratesValidToken(): void
    {
        $userId = 1;

        // Mock the insert operation
        $this->mockModel->expects($this->once())
            ->method('insert')
            ->willReturn(true);

        $token = $this->service->issueRefreshToken($userId);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
    }

    public function testIssueRefreshTokenGeneratesUniqueTokens(): void
    {
        $userId = 1;

        // Mock the insert operation
        $this->mockModel->expects($this->exactly(2))
            ->method('insert')
            ->willReturn(true);

        $token1 = $this->service->issueRefreshToken($userId);
        $token2 = $this->service->issueRefreshToken($userId);

        $this->assertNotEquals($token1, $token2, 'Each token should be unique');
    }

    public function testIssueRefreshTokenStoresTokenInDatabase(): void
    {
        $userId = 42;

        $this->mockModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) use ($userId) {
                return $data['user_id'] === $userId
                    && isset($data['token'])
                    && isset($data['expires_at'])
                    && isset($data['created_at']);
            }))
            ->willReturn(true);

        $this->service->issueRefreshToken($userId);
    }

    public function testIssueRefreshTokenSetsCorrectExpiration(): void
    {
        $userId = 1;

        // Set custom TTL for testing
        putenv('JWT_REFRESH_TOKEN_TTL=86400'); // 1 day

        $this->mockModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                $expiresAt = strtotime($data['expires_at']);
                $expectedExpiry = time() + 86400;
                // Allow 2 second tolerance for test execution time
                return abs($expiresAt - $expectedExpiry) < 2;
            }))
            ->willReturn(true);

        $result = $this->service->issueRefreshToken($userId);

        // Explicit assertions for PHPUnit
        $this->assertIsString($result, 'Should return a token string');
        $this->assertNotEmpty($result, 'Token should not be empty');

        putenv('JWT_REFRESH_TOKEN_TTL'); // Clear
    }

    public function testIssueRefreshTokenUsesDefaultTTLWhenNotSet(): void
    {
        $userId = 1;

        // Ensure no custom TTL is set
        putenv('JWT_REFRESH_TOKEN_TTL');

        $this->mockModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                $expiresAt = strtotime($data['expires_at']);
                $expectedExpiry = time() + 604800; // 7 days default
                // Allow 2 second tolerance
                return abs($expiresAt - $expectedExpiry) < 2;
            }))
            ->willReturn(true);

        $this->service->issueRefreshToken($userId);
    }

    // ==================== REFRESH ACCESS TOKEN TESTS ====================

    public function testRefreshAccessTokenReturnErrorWhenTokenMissing(): void
    {
        $result = $this->service->refreshAccessToken([]);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('refresh_token', $result['errors']);
    }

    public function testRefreshAccessTokenReturnErrorWhenTokenEmpty(): void
    {
        $result = $this->service->refreshAccessToken(['refresh_token' => '']);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('errors', $result);
    }

    // ==================== REVOKE TOKEN TESTS ====================

    public function testRevokeReturnErrorWhenTokenMissing(): void
    {
        $result = $this->service->revoke([]);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('refresh_token', $result['errors']);
    }

    public function testRevokeReturnErrorWhenTokenEmpty(): void
    {
        $result = $this->service->revoke(['refresh_token' => '']);

        $this->assertEquals('error', $result['status']);
        $this->assertArrayHasKey('errors', $result);
    }

    public function testRevokeReturnSuccessWhenTokenRevoked(): void
    {
        $token = 'valid-token';

        $this->mockModel->expects($this->once())
            ->method('revokeToken')
            ->with($token)
            ->willReturn(true);

        $result = $this->service->revoke(['refresh_token' => $token]);

        $this->assertEquals('success', $result['status']);
    }

    public function testRevokeReturnErrorWhenTokenNotFound(): void
    {
        $token = 'non-existent-token';

        $this->mockModel->expects($this->once())
            ->method('revokeToken')
            ->with($token)
            ->willReturn(false);

        $result = $this->service->revoke(['refresh_token' => $token]);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(404, $result['code']);
    }

    // ==================== REVOKE ALL USER TOKENS TESTS ====================

    public function testRevokeAllUserTokensReturnSuccess(): void
    {
        $userId = 1;

        $this->mockModel->expects($this->once())
            ->method('revokeAllUserTokens')
            ->with($userId)
            ->willReturn(true);

        $result = $this->service->revokeAllUserTokens($userId);

        $this->assertEquals('success', $result['status']);
    }

    public function testRevokeAllUserTokensCallsModelCorrectly(): void
    {
        $userId = 42;

        $this->mockModel->expects($this->once())
            ->method('revokeAllUserTokens')
            ->with($this->identicalTo($userId));

        $this->service->revokeAllUserTokens($userId);
    }

    // ==================== SECURITY TESTS ====================

    public function testIssueRefreshTokenGeneratesSecureRandomToken(): void
    {
        // Mock the insert operation
        $this->mockModel->expects($this->exactly(100))
            ->method('insert')
            ->willReturn(true);

        $tokens = [];
        for ($i = 0; $i < 100; $i++) {
            $tokens[] = $this->service->issueRefreshToken(1);
        }

        // All tokens should be unique
        $uniqueTokens = array_unique($tokens);
        $this->assertCount(100, $uniqueTokens, 'All tokens should be unique');

        // All tokens should be 64 chars hex
        foreach ($tokens as $token) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        }
    }

    public function testTokenFormatIsHexadecimal(): void
    {
        $this->mockModel->expects($this->once())
            ->method('insert')
            ->willReturn(true);

        $token = $this->service->issueRefreshToken(1);

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $token,
            'Token should be 64 character hexadecimal string'
        );
    }

    // ==================== EDGE CASES ====================

    public function testIssueRefreshTokenHandlesZeroUserId(): void
    {
        $this->mockModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                return $data['user_id'] === 0;
            }))
            ->willReturn(true);

        $token = $this->service->issueRefreshToken(0);
        $this->assertNotEmpty($token);
    }

    public function testIssueRefreshTokenHandlesLargeUserId(): void
    {
        $largeId = PHP_INT_MAX;

        $this->mockModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) use ($largeId) {
                return $data['user_id'] === $largeId;
            }))
            ->willReturn(true);

        $token = $this->service->issueRefreshToken($largeId);
        $this->assertNotEmpty($token);
    }

    public function testRevokeHandlesLongToken(): void
    {
        $longToken = str_repeat('a', 255);

        $this->mockModel->expects($this->once())
            ->method('revokeToken')
            ->with($longToken)
            ->willReturn(true);

        $result = $this->service->revoke(['refresh_token' => $longToken]);
        $this->assertEquals('success', $result['status']);
    }

    public function testRevokeAllUserTokensHandlesZeroUserId(): void
    {
        $this->mockModel->expects($this->once())
            ->method('revokeAllUserTokens')
            ->with(0)
            ->willReturn(true);

        $result = $this->service->revokeAllUserTokens(0);
        $this->assertEquals('success', $result['status']);
    }
}
