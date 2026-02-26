<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entities\UserEntity;
use App\Exceptions\AuthenticationException;
use App\Exceptions\ValidationException;
use App\Interfaces\AuditServiceInterface;
use App\Interfaces\JwtServiceInterface;
use App\Interfaces\RefreshTokenServiceInterface;
use App\Interfaces\VerificationServiceInterface;
use App\Models\UserModel;
use App\Services\AuthService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * AuthService Unit Tests
 *
 * Tests authentication logic with mocked dependencies.
 * Note: Tests that require query builder mocking are skipped.
 */
class AuthServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected AuthService $service;
    protected JwtServiceInterface $mockJwtService;
    protected RefreshTokenServiceInterface $mockRefreshTokenService;
    protected VerificationServiceInterface $mockVerificationService;
    protected AuditServiceInterface $mockAuditService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockJwtService = $this->createMock(JwtServiceInterface::class);
        $this->mockRefreshTokenService = $this->createMock(RefreshTokenServiceInterface::class);
        $this->mockVerificationService = $this->createMock(VerificationServiceInterface::class);
        $this->mockAuditService = $this->createMock(AuditServiceInterface::class);
    }

    /**
     * Create AuthService with a real UserModel mock using anonymous class
     */
    protected function createServiceWithUserQuery(?UserEntity $returnUser): AuthService
    {
        // Create an anonymous class that extends UserModel and overrides query methods
        $mockUserModel = new class ($returnUser) extends UserModel {
            private ?UserEntity $returnUser;

            public function __construct(?UserEntity $user)
            {
                $this->returnUser = $user;
            }

            public function where($key, $value = null, ?bool $escape = null): static
            {
                return $this;
            }

            public function orWhere($key, $value = null, ?bool $escape = null): static
            {
                return $this;
            }

            public function first()
            {
                return $this->returnUser;
            }

            public function insert($row = null, bool $returnID = true)
            {
                return 1;
            }

            public function find($id = null)
            {
                return $this->returnUser;
            }
        };

        return new AuthService(
            $mockUserModel,
            $this->mockJwtService,
            $this->mockRefreshTokenService,
            $this->mockVerificationService,
            $this->mockAuditService
        );
    }

    // ==================== LOGIN TESTS ====================

    public function testLoginWithValidCredentialsReturnsUserData(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $service = $this->createServiceWithUserQuery($user);

        // Expect tokens to be generated
        $this->mockJwtService
            ->method('encode')
            ->willReturn('jwt.access.token');

        $this->mockRefreshTokenService
            ->method('issueRefreshToken')
            ->willReturn('refresh.token');

        $result = $service->login(new \App\DTO\Request\Auth\LoginRequestDTO([
            'email' => 'test@example.com',
            'password' => 'ValidPass123!',
        ]));

        $this->assertInstanceOf(\App\DTO\Response\Auth\LoginResponseDTO::class, $result);
        $data = $result->toArray();
        $this->assertEquals('jwt.access.token', $data['access_token']);
        $this->assertEquals('refresh.token', $data['refresh_token']);
        $this->assertEquals(1, $data['user']['id']);
        $this->assertEquals('test@example.com', $data['user']['email']);
    }

    public function testLoginWithInvalidPasswordThrowsException(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'password' => password_hash('CorrectPass123!', PASSWORD_BCRYPT),
        ]);

        $service = $this->createServiceWithUserQuery($user);

        $this->expectException(AuthenticationException::class);

        $service->login(new \App\DTO\Request\Auth\LoginRequestDTO([
            'email' => 'test@example.com',
            'password' => 'WrongPassword123!',
        ]));
    }

    public function testLoginWithNonExistentUserThrowsException(): void
    {
        $service = $this->createServiceWithUserQuery(null);

        $this->expectException(AuthenticationException::class);

        $service->login(new \App\DTO\Request\Auth\LoginRequestDTO([
            'email' => 'nonexistent@example.com',
            'password' => 'AnyPassword123!',
        ]));
    }

    public function testLoginWithEmptyCredentialsThrowsException(): void
    {
        $service = $this->createServiceWithUserQuery(null);

        // Validation happens in DTO constructor now
        $this->expectException(\App\Exceptions\ValidationException::class);

        $service->login(new \App\DTO\Request\Auth\LoginRequestDTO([
            'email' => '',
            'password' => '',
        ]));
    }

    public function testLoginWithMissingPasswordThrowsException(): void
    {
        $service = $this->createServiceWithUserQuery(null);

        // Validation happens in DTO constructor now
        $this->expectException(\App\Exceptions\ValidationException::class);

        $service->login(new \App\DTO\Request\Auth\LoginRequestDTO([
            'email' => 'test@example.com',
        ]));
    }

    // ==================== LOGIN WITH TOKEN TESTS ====================

    public function testLoginWithTokenReturnsAccessAndRefreshTokens(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $service = $this->createServiceWithUserQuery($user);

        $this->mockJwtService
            ->expects($this->once())
            ->method('encode')
            ->with(1, 'user')
            ->willReturn('jwt.access.token');

        $this->mockRefreshTokenService
            ->expects($this->once())
            ->method('issueRefreshToken')
            ->with(1)
            ->willReturn('refresh.token.here');

        $result = $service->loginWithToken([
            'email' => 'test@example.com',
            'password' => 'ValidPass123!',
        ]);

        $this->assertSuccessResponse($result);
        $this->assertEquals('jwt.access.token', $result['data']['access_token']);
        $this->assertEquals('refresh.token.here', $result['data']['refresh_token']);
        $this->assertArrayHasKey('expires_in', $result['data']);
        $this->assertArrayHasKey('user', $result['data']);
        $this->assertEquals(1, $result['data']['user']['id']);
    }

    public function testLoginWithTokenFailsIfEmailNotVerified(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'email_verified_at' => null,
        ]);

        $service = $this->createServiceWithUserQuery($user);

        $this->mockJwtService
            ->expects($this->never())
            ->method('encode');

        $this->mockRefreshTokenService
            ->expects($this->never())
            ->method('issueRefreshToken');

        $this->expectException(AuthenticationException::class);

        $service->loginWithToken([
            'email' => 'test@example.com',
            'password' => 'ValidPass123!',
        ]);
    }

    public function testLoginWithTokenAllowsUnverifiedEmailWhenDisabled(): void
    {
        $previous = getenv('AUTH_REQUIRE_EMAIL_VERIFICATION');
        putenv('AUTH_REQUIRE_EMAIL_VERIFICATION=false');

        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'email_verified_at' => null,
        ]);

        $service = $this->createServiceWithUserQuery($user);

        $this->mockJwtService
            ->expects($this->once())
            ->method('encode')
            ->with(1, 'user')
            ->willReturn('jwt.access.token');

        $this->mockRefreshTokenService
            ->expects($this->once())
            ->method('issueRefreshToken')
            ->with(1)
            ->willReturn('refresh.token.here');

        $result = $service->loginWithToken([
            'email' => 'test@example.com',
            'password' => 'ValidPass123!',
        ]);

        $this->assertSuccessResponse($result);

        if ($previous === false || $previous === '') {
            putenv('AUTH_REQUIRE_EMAIL_VERIFICATION');
        } else {
            putenv('AUTH_REQUIRE_EMAIL_VERIFICATION=' . $previous);
        }
    }

    public function testLoginWithTokenAllowsGoogleOauthWithoutVerification(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'email_verified_at' => null,
            'oauth_provider' => 'google',
        ]);

        $service = $this->createServiceWithUserQuery($user);

        $this->mockJwtService
            ->expects($this->once())
            ->method('encode')
            ->with(1, 'user')
            ->willReturn('jwt.access.token');

        $this->mockRefreshTokenService
            ->expects($this->once())
            ->method('issueRefreshToken')
            ->with(1)
            ->willReturn('refresh.token.here');

        $result = $service->loginWithToken([
            'email' => 'test@example.com',
            'password' => 'ValidPass123!',
        ]);

        $this->assertSuccessResponse($result);
        $this->assertEquals('jwt.access.token', $result['data']['access_token']);
        $this->assertEquals('refresh.token.here', $result['data']['refresh_token']);
    }

    public function testLoginWithTokenFailsIfPendingApproval(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'pending_approval',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $service = $this->createServiceWithUserQuery($user);

        $this->mockJwtService
            ->expects($this->never())
            ->method('encode');

        $this->mockRefreshTokenService
            ->expects($this->never())
            ->method('issueRefreshToken');

        $this->expectException(\App\Exceptions\AuthorizationException::class);

        $service->loginWithToken([
            'email' => 'test@example.com',
            'password' => 'ValidPass123!',
        ]);
    }

    public function testLoginWithTokenFailsIfInvited(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'invited',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $service = $this->createServiceWithUserQuery($user);

        $this->mockJwtService
            ->expects($this->never())
            ->method('encode');

        $this->mockRefreshTokenService
            ->expects($this->never())
            ->method('issueRefreshToken');

        $this->expectException(\App\Exceptions\AuthorizationException::class);

        $service->loginWithToken([
            'email' => 'test@example.com',
            'password' => 'ValidPass123!',
        ]);
    }

    // ==================== REGISTER TESTS ====================

    public function testRegisterWithValidDataCreatesUser(): void
    {
        $createdUser = $this->createUserEntity([
            'id' => 1,
            'email' => 'new@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
            'role' => 'user',
            'status' => 'pending_approval',
        ]);

        $service = $this->createServiceWithUserQuery($createdUser);

        $result = $service->register(new \App\DTO\Request\Auth\RegisterRequestDTO([
            'email' => 'new@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
            'password' => 'ValidPass123!',
        ]));

        $this->assertInstanceOf(\App\DTO\Response\Auth\RegisterResponseDTO::class, $result);
        $data = $result->toArray();
        $this->assertEquals(1, $data['id']);
        $this->assertEquals('user', $data['role']);
        $this->assertEquals('pending_approval', $data['status']);
    }

    public function testRegisterWithoutPasswordThrowsException(): void
    {
        $service = $this->createServiceWithUserQuery(null);

        // Validation now happens in DTO constructor
        $this->expectException(\App\Exceptions\ValidationException::class);

        $service->register(new \App\DTO\Request\Auth\RegisterRequestDTO([
            'email' => 'new@example.com',
        ]));
    }

    public function testRegisterWithInvalidDataThrowsValidationException(): void
    {
        $service = $this->createServiceWithUserQuery(null);

        $this->expectException(ValidationException::class);

        $service->register(new \App\DTO\Request\Auth\RegisterRequestDTO([
            'email' => 'invalid-email',
            'password' => 'ValidPass123!',
        ]));
    }

    // ==================== REGISTER WITH TOKEN TESTS ====================

    public function testRegisterWithTokenSendsVerificationAndReturnsMessage(): void
    {
        $createdUser = $this->createUserEntity([
            'id' => 1,
            'email' => 'new@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
            'role' => 'user',
            'status' => 'pending_approval',
        ]);

        $service = $this->createServiceWithUserQuery($createdUser);

        $this->mockVerificationService
            ->expects($this->once())
            ->method('sendVerificationEmail')
            ->with(1);

        $result = $service->registerWithToken([
            'email' => 'new@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
            'password' => 'ValidPass123!',
        ]);

        $this->assertSuccessResponse($result);
        $this->assertEquals(1, $result['data']['id']);
        $this->assertEquals('new@example.com', $result['data']['email']);
    }

    public function testRegisterWithTokenContinuesIfVerificationEmailFails(): void
    {
        $createdUser = $this->createUserEntity([
            'id' => 1,
            'email' => 'new@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
            'role' => 'user',
            'status' => 'pending_approval',
        ]);

        $service = $this->createServiceWithUserQuery($createdUser);

        // Email sending fails
        $this->mockVerificationService
            ->method('sendVerificationEmail')
            ->willThrowException(new \RuntimeException('SMTP error'));

        // Should not throw - registration should still succeed
        $result = $service->registerWithToken([
            'email' => 'new@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
            'password' => 'ValidPass123!',
        ]);

        $this->assertSuccessResponse($result);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Create a UserEntity with mock data
     */
    private function createUserEntity(array $data): UserEntity
    {
        $user = new UserEntity();
        foreach ($data as $key => $value) {
            $user->{$key} = $value;
        }
        return $user;
    }
}
