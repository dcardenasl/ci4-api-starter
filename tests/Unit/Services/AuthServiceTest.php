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
use App\Interfaces\Users\UserRepositoryInterface;
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
        $mockUserRepository = $this->createMock(UserRepositoryInterface::class);
        $mockUserRepository->method('findByEmail')->willReturn($returnUser);
        $mockUserRepository->method('findByEmailWithDeleted')->willReturn($returnUser);
        $mockUserRepository->method('find')->willReturn($returnUser);
        $mockUserRepository->method('insert')->willReturn(1);
        $mockUserRepository->method('update')->willReturn(true);
        $mockUserRepository->method('restore')->willReturn(true);

        $userMapper = new AuthUserMapper();
        $sessionManager = new SessionManager($this->mockJwtService, $this->mockRefreshTokenService);
        $registerUserAction = new RegisterUserAction($mockUserRepository, $this->mockVerificationService, $this->mockEmailService);
        $googleLoginAction = new GoogleLoginAction(
            $mockUserRepository,
            $this->mockGoogleIdentityService,
            new GoogleAuthHandler($mockUserRepository, $this->mockRefreshTokenService),
            $sessionManager,
            $userMapper,
            new UserAccountGuard(),
            $this->mockAuditService,
            $this->mockEmailService
        );

        return new AuthService(
            $mockUserRepository,
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
        $this->assertEquals('jwt.access.token', $data['access_token']);
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

        $mockUserRepository = $this->createMock(UserRepositoryInterface::class);
        $mockUserRepository->method('find')->willReturn($user);

        $registerUserAction = $this->createMock(RegisterUserAction::class);
        $request = new \App\DTO\Request\Auth\RegisterRequestDTO([
            'email' => 'new-unique+' . uniqid('', true) . '@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
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
            $mockUserRepository,
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
