<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Interfaces\JwtServiceInterface;
use App\Models\RefreshTokenModel;
use App\Models\UserModel;
use App\Services\RefreshTokenService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * RefreshTokenService Unit Tests
 *
 * Tests token lifecycle with mocked dependencies.
 */
class RefreshTokenServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected RefreshTokenService $service;
    protected RefreshTokenModel $mockRefreshTokenModel;
    protected JwtServiceInterface $mockJwtService;
    protected UserModel $mockUserModel;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('JWT_REFRESH_TOKEN_TTL=604800');

        $this->mockRefreshTokenModel = $this->createMock(RefreshTokenModel::class);
        $this->mockJwtService = $this->createMock(JwtServiceInterface::class);
        $this->mockUserModel = $this->createMock(UserModel::class);

        $this->service = new RefreshTokenService(
            $this->mockRefreshTokenModel,
            $this->mockJwtService,
            $this->mockUserModel
        );
    }

    protected function tearDown(): void
    {
        putenv('JWT_REFRESH_TOKEN_TTL');
        parent::tearDown();
    }

    // ==================== ISSUE REFRESH TOKEN TESTS ====================

    public function testIssueRefreshTokenReturnsTokenString(): void
    {
        $this->mockRefreshTokenModel
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                return isset($data['user_id'])
                    && $data['user_id'] === 1
                    && isset($data['token'])
                    && strlen($data['token']) === 64  // 32 bytes = 64 hex chars
                    && isset($data['expires_at']);
            }))
            ->willReturn(1);

        $token = $this->service->issueRefreshToken(1);

        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
    }

    public function testIssueRefreshTokenGeneratesUniqueTokens(): void
    {
        $this->mockRefreshTokenModel
            ->method('insert')
            ->willReturn(1);

        $token1 = $this->service->issueRefreshToken(1);
        $token2 = $this->service->issueRefreshToken(1);

        $this->assertNotEquals($token1, $token2);
    }

    // ==================== REVOKE TESTS ====================

    public function testRevokeWithEmptyTokenThrowsException(): void
    {
        $this->expectException(BadRequestException::class);

        $this->service->revoke([]);
    }

    public function testRevokeWithValidTokenReturnsSuccess(): void
    {
        $this->mockRefreshTokenModel
            ->expects($this->once())
            ->method('revokeToken')
            ->with('valid-token')
            ->willReturn(true);

        $result = $this->service->revoke(['refresh_token' => 'valid-token']);

        $this->assertSuccessResponse($result);
    }

    public function testRevokeWithNonExistentTokenThrowsNotFoundException(): void
    {
        $this->mockRefreshTokenModel
            ->method('revokeToken')
            ->willReturn(false);

        $this->expectException(NotFoundException::class);

        $this->service->revoke(['refresh_token' => 'non-existent-token']);
    }

    // ==================== REVOKE ALL USER TOKENS TESTS ====================

    public function testRevokeAllUserTokensCallsModel(): void
    {
        $this->mockRefreshTokenModel
            ->expects($this->once())
            ->method('revokeAllUserTokens')
            ->with(1);

        $result = $this->service->revokeAllUserTokens(1);

        $this->assertSuccessResponse($result);
    }
}
