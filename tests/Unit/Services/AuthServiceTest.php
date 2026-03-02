<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\Request\Auth\LoginRequestDTO;
use App\Entities\UserEntity;
use App\Exceptions\AuthenticationException;
use App\Interfaces\Auth\GoogleIdentityServiceInterface;
use App\Interfaces\Auth\VerificationServiceInterface;
use App\Interfaces\System\AuditServiceInterface;
use App\Interfaces\System\EmailServiceInterface;
use App\Interfaces\Tokens\JwtServiceInterface;
use App\Interfaces\Tokens\RefreshTokenServiceInterface;
use App\Models\UserModel;
use App\Services\Auth\Actions\GoogleLoginAction;
use App\Services\Auth\Actions\RegisterUserAction;
use App\Services\Auth\AuthService;
use App\Services\Auth\Support\AuthUserMapper;
use App\Services\Auth\Support\GoogleAuthHandler;
use App\Services\Auth\Support\SessionManager;
use App\Services\Users\UserAccountGuard;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * AuthService Unit Tests
 *
 * Tests authentication logic with mocked dependencies.
 */
class AuthServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected AuthService $service;
    protected \App\Interfaces\Tokens\JwtServiceInterface $mockJwtService;
    protected \App\Interfaces\Tokens\RefreshTokenServiceInterface $mockRefreshTokenService;
    protected VerificationServiceInterface $mockVerificationService;
    protected AuditServiceInterface $mockAuditService;
    protected GoogleIdentityServiceInterface $mockGoogleIdentityService;
    protected EmailServiceInterface $mockEmailService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockJwtService = $this->createMock(JwtServiceInterface::class);
        $this->mockRefreshTokenService = $this->createMock(RefreshTokenServiceInterface::class);
        $this->mockVerificationService = $this->createMock(VerificationServiceInterface::class);
        $this->mockAuditService = $this->createMock(AuditServiceInterface::class);
        $this->mockGoogleIdentityService = $this->createMock(GoogleIdentityServiceInterface::class);
        $this->mockEmailService = $this->createMock(EmailServiceInterface::class);
    }

    protected function createServiceWithUserQuery(?UserEntity $returnUser): AuthService
    {
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
            public function update($id = null, $data = null): bool
            {
                return true;
            }
        };

        $userMapper = new AuthUserMapper();
        $sessionManager = new SessionManager($this->mockJwtService, $this->mockRefreshTokenService);
        $registerUserAction = new RegisterUserAction($mockUserModel, $this->mockVerificationService);
        $googleLoginAction = new GoogleLoginAction(
            $mockUserModel,
            $this->mockGoogleIdentityService,
            new GoogleAuthHandler($mockUserModel, $this->mockRefreshTokenService),
            $sessionManager,
            $userMapper,
            new UserAccountGuard(),
            $this->mockAuditService,
            $this->mockEmailService
        );

        return new AuthService(
            $mockUserModel,
            $registerUserAction,
            $googleLoginAction,
            $this->mockAuditService,
            $userMapper,
            $sessionManager,
            new UserAccountGuard()
        );
    }

    // ==================== LOGIN TESTS ====================

    public function testLoginWithValidCredentialsReturnsUserData(): void
    {
        $user = new UserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        $service = $this->createServiceWithUserQuery($user);

        $this->mockJwtService->method('encode')->willReturn('jwt.access.token');
        $this->mockRefreshTokenService->method('issueRefreshToken')->willReturn('refresh.token');

        $result = $service->login(new LoginRequestDTO([
            'email' => 'test@example.com',
            'password' => 'ValidPass123!',
        ]));

        $this->assertInstanceOf(\App\Interfaces\DataTransferObjectInterface::class, $result);
        $data = $result->toArray();
        $this->assertEquals('jwt.access.token', $data['accessToken']);
        $this->assertEquals(1, $data['user']['id']);
    }

    public function testLoginWithInvalidPasswordThrowsException(): void
    {
        $user = new UserEntity([
            'id' => 1,
            'password' => password_hash('CorrectPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active'
        ]);

        $service = $this->createServiceWithUserQuery($user);
        $this->expectException(AuthenticationException::class);

        $service->login(new LoginRequestDTO([
            'email' => 'test@example.com',
            'password' => 'WrongPassword123!',
        ]));
    }

    // ==================== REGISTER TESTS ====================

    public function testRegisterWithValidDataCreatesUser(): void
    {
        $user = new UserEntity([
            'id' => 1,
            'email' => 'new@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
            'role' => 'user',
            'status' => 'pending_approval'
        ]);

        $mockUserModel = new class ($user) extends UserModel {
            public function __construct(private readonly UserEntity $returnUser)
            {
            }

            public function find($id = null)
            {
                return $this->returnUser;
            }
        };

        $registerUserAction = $this->createMock(RegisterUserAction::class);
        $request = new \App\DTO\Request\Auth\RegisterRequestDTO([
            'email' => 'new-unique+' . uniqid('', true) . '@example.com',
            'firstName' => 'New',
            'lastName' => 'User',
            'password' => 'StrongPass123!',
        ]);

        $registerUserAction
            ->expects($this->once())
            ->method('execute')
            ->with($request, null)
            ->willReturn($user);

        $googleLoginAction = $this->createMock(GoogleLoginAction::class);
        $userMapper = new AuthUserMapper();
        $sessionManager = new SessionManager($this->mockJwtService, $this->mockRefreshTokenService);

        $service = new AuthService(
            $mockUserModel,
            $registerUserAction,
            $googleLoginAction,
            $this->mockAuditService,
            $userMapper,
            $sessionManager,
            new UserAccountGuard()
        );

        $result = $service->register($request);

        $this->assertInstanceOf(\App\Interfaces\DataTransferObjectInterface::class, $result);
    }

    public function testMeReturnsUserProfile(): void
    {
        $user = new UserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'role' => 'user',
            'status' => 'active',
        ]);
        $service = $this->createServiceWithUserQuery($user);

        $result = $service->me(1);
        $this->assertInstanceOf(\App\Interfaces\DataTransferObjectInterface::class, $result);
        $this->assertEquals('test@example.com', $result->toArray()['email']);
    }
}
