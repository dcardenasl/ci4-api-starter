<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\RefreshTokenModel;
use App\Services\RefreshTokenService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * RefreshTokenService Unit Tests
 *
 * Comprehensive test coverage for refresh token operations.
 * Tests token issuance, refresh, revocation, and security aspects.
 */
class RefreshTokenServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected RefreshTokenService $service;
    protected RefreshTokenModel $mockModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockModel = $this->createMock(RefreshTokenModel::class);
        $this->service = new RefreshTokenService($this->mockModel);
    }

    // ==================== ISSUE REFRESH TOKEN TESTS ====================

    public function testIssueRefreshTokenGeneratesValidToken(): void
    {
        $userId = 1;

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

        $this->assertIsString($result, 'Should return a token string');
        $this->assertNotEmpty($result, 'Token should not be empty');

        putenv('JWT_REFRESH_TOKEN_TTL'); // Clear
    }

    public function testIssueRefreshTokenUsesDefaultTTLWhenNotConfigured(): void
    {
        $userId = 1;

        putenv('JWT_REFRESH_TOKEN_TTL'); // Ensure no custom TTL

        $this->mockModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                $expiresAt = strtotime($data['expires_at']);
                $expectedExpiry = time() + 604800; // 7 days default
                return abs($expiresAt - $expectedExpiry) < 2;
            }))
            ->willReturn(true);

        $this->service->issueRefreshToken($userId);
    }

    // ==================== VALIDATION TESTS ====================

    /**
     * @dataProvider invalidRefreshAccessTokenDataProvider
     */
    public function testRefreshAccessTokenValidatesRequiredParameters(array $data, string $expectedErrorField): void
    {
        $result = $this->service->refreshAccessToken($data);

        $this->assertErrorResponse($result, $expectedErrorField);
    }

    public static function invalidRefreshAccessTokenDataProvider(): array
    {
        return [
            'missing refresh_token' => [[], 'refresh_token'],
            'empty refresh_token' => [['refresh_token' => ''], 'refresh_token'],
        ];
    }

    /**
     * @dataProvider invalidRevokeDataProvider
     */
    public function testRevokeValidatesRequiredParameters(array $data, string $expectedErrorField): void
    {
        $result = $this->service->revoke($data);

        $this->assertErrorResponse($result, $expectedErrorField);
    }

    public static function invalidRevokeDataProvider(): array
    {
        return [
            'missing refresh_token' => [[], 'refresh_token'],
            'empty refresh_token' => [['refresh_token' => ''], 'refresh_token'],
        ];
    }

    // ==================== REVOKE TOKEN TESTS ====================

    public function testRevokeMarksTokenAsRevoked(): void
    {
        $token = 'valid-token';

        $this->mockModel->expects($this->once())
            ->method('revokeToken')
            ->with($token)
            ->willReturn(true);

        $result = $this->service->revoke(['refresh_token' => $token]);

        $this->assertSuccessResponse($result);
    }

    public function testRevokeReturnsErrorWhenTokenNotFound(): void
    {
        $token = 'non-existent-token';

        $this->mockModel->expects($this->once())
            ->method('revokeToken')
            ->with($token)
            ->willReturn(false);

        $result = $this->service->revoke(['refresh_token' => $token]);

        $this->assertErrorResponseWithCode($result, 404);
    }

    // ==================== REVOKE ALL USER TOKENS TESTS ====================

    public function testRevokeAllUserTokensRevokesAllActiveTokens(): void
    {
        $userId = 1;

        $this->mockModel->expects($this->once())
            ->method('revokeAllUserTokens')
            ->with($userId)
            ->willReturn(true);

        $result = $this->service->revokeAllUserTokens($userId);

        $this->assertSuccessResponse($result);
    }

    // ==================== SECURITY TESTS ====================

    public function testIssueRefreshTokenGeneratesSecureRandomTokens(): void
    {
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
}
