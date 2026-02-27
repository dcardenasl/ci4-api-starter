<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entities\UserEntity;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\System\AuditServiceInterface;
use App\Interfaces\System\EmailServiceInterface;
use App\Interfaces\Tokens\RefreshTokenServiceInterface;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
use App\Services\Auth\PasswordResetService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * PasswordResetService Unit Tests
 */
class PasswordResetServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    private const VALID_RESET_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const UNKNOWN_RESET_TOKEN = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    protected PasswordResetService $service;
    protected UserModel $mockUserModel;
    protected PasswordResetModel $mockPasswordResetModel;
    protected EmailServiceInterface $mockEmailService;
    protected RefreshTokenServiceInterface $mockRefreshTokenService;
    protected AuditServiceInterface $mockAuditService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockEmailService = $this->createMock(EmailServiceInterface::class);
        $this->mockRefreshTokenService = $this->createMock(RefreshTokenServiceInterface::class);
        $this->mockAuditService = $this->createMock(AuditServiceInterface::class);
    }

    protected function tearDown(): void
    {
        putenv('WEBAPP_BASE_URL');
        putenv('WEBAPP_ALLOWED_BASE_URLS');

        parent::tearDown();
    }

    /**
     * Create service with mocked user model
     */
    private function createServiceWithUser(
        ?UserEntity $activeUser,
        ?UserEntity $deletedUser = null
    ): PasswordResetService {
        $validToken = self::VALID_RESET_TOKEN;

        $mockUserModel = new class ($activeUser, $deletedUser) extends UserModel {
            private ?UserEntity $activeUser;
            private ?UserEntity $deletedUser;
            private bool $includeDeleted = false;

            public function __construct(?UserEntity $activeUser, ?UserEntity $deletedUser)
            {
                $this->activeUser = $activeUser;
                $this->deletedUser = $deletedUser;
            }

            public function withDeleted(bool $val = true): static
            {
                $this->includeDeleted = $val;
                return $this;
            }

            public function where($key, $value = null, ?bool $escape = null): static
            {
                return $this;
            }

            public function first()
            {
                return $this->includeDeleted ? $this->deletedUser : $this->activeUser;
            }

            public function update($id = null, $row = null): bool
            {
                return true;
            }
        };

        $mockPasswordResetModel = new class ($validToken) extends PasswordResetModel {
            private string $validToken;

            public function __construct(string $validToken)
            {
                $this->validToken = $validToken;
            }

            public function where($key, $value = null, ?bool $escape = null): static
            {
                return $this;
            }

            public function delete($id = null, bool $purge = false)
            {
                return true;
            }

            public function insert($row = null, bool $returnID = true)
            {
                return 1;
            }

            public function cleanExpired(int $expiryMinutes = 60): void
            {
                // Do nothing in mock
            }

            public function isValidToken(string $email, string $token, int $expiryMinutes = 60): bool
            {
                return $token === $this->validToken;
            }
        };

        return new PasswordResetService(
            $mockUserModel,
            $mockPasswordResetModel,
            $this->mockEmailService,
            $this->mockRefreshTokenService,
            $this->mockAuditService
        );
    }

    /**
     * Helper: Create user entity
     */
    private function createUserEntity(array $data): UserEntity
    {
        $user = new UserEntity();
        foreach ($data as $key => $value) {
            $user->{$key} = $value;
        }

        return $user;
    }

    // ==================== SEND RESET LINK TESTS ====================

    public function testSendResetLinkCreatesTokenAndSendsEmail(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'active',
        ]);

        $service = $this->createServiceWithUser($user);

        $this->mockEmailService->expects($this->once())
            ->method('queueTemplate')
            ->with(
                'password-reset',
                'test@example.com',
                $this->callback(function ($data) {
                    return isset($data['reset_link'])
                        && str_contains($data['reset_link'], '/reset-password?token=')
                        && str_contains($data['reset_link'], 'token=');
                })
            );

        $result = $service->sendResetLink(new \App\DTO\Request\Identity\ForgotPasswordRequestDTO(['email' => 'test@example.com']));

        $this->assertTrue($result);
    }

    public function testSendResetLinkPreventsEmailEnumeration(): void
    {
        // Test with non-existent user
        $service = $this->createServiceWithUser(null);

        $this->mockEmailService->expects($this->never())
            ->method('queueTemplate');

        $result = $service->sendResetLink(new \App\DTO\Request\Identity\ForgotPasswordRequestDTO(['email' => 'nonexistent@example.com']));

        $this->assertTrue($result);
    }

    public function testSendResetLinkThrowsExceptionForInvalidEmail(): void
    {
        $service = $this->createServiceWithUser(null);

        $this->expectException(ValidationException::class);

        new \App\DTO\Request\Identity\ForgotPasswordRequestDTO(['email' => 'invalid-email']);
    }

    public function testSendResetLinkThrowsExceptionForEmptyEmail(): void
    {
        $service = $this->createServiceWithUser(null);

        $this->expectException(ValidationException::class);

        new \App\DTO\Request\Identity\ForgotPasswordRequestDTO(['email' => '']);
    }

    // ==================== VALIDATE TOKEN TESTS ====================

    public function testValidateTokenSucceedsForValidToken(): void
    {
        $service = $this->createServiceWithUser(null);

        $request = new \App\DTO\Request\Identity\PasswordResetTokenValidationDTO([
            'email' => 'test@example.com',
            'token' => self::VALID_RESET_TOKEN,
        ]);

        $result = $service->validateToken($request);

        $this->assertTrue($result);
    }

    public function testValidateTokenFailsForInvalidToken(): void
    {
        $service = $this->createServiceWithUser(null);

        $this->expectException(NotFoundException::class);

        $request = new \App\DTO\Request\Identity\PasswordResetTokenValidationDTO([
            'email' => 'test@example.com',
            'token' => self::UNKNOWN_RESET_TOKEN,
        ]);

        $service->validateToken($request);
    }

    // ==================== RESET PASSWORD TESTS ====================

    public function testResetPasswordSuccessfully(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'status' => 'active',
        ]);

        $service = $this->createServiceWithUser($user);

        $result = $service->resetPassword(new \App\DTO\Request\Identity\ResetPasswordRequestDTO([
            'email' => 'test@example.com',
            'token' => self::VALID_RESET_TOKEN,
            'password' => 'NewSecure123!',
        ]));

        $this->assertTrue($result);
    }

    public function testResetPasswordThrowsExceptionForWeakPassword(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
        ]);

        $service = $this->createServiceWithUser($user);

        $this->expectException(\App\Exceptions\ValidationException::class);

        new \App\DTO\Request\Identity\ResetPasswordRequestDTO([
            'email' => 'test@example.com',
            'token' => self::VALID_RESET_TOKEN,
            'password' => 'weak',
        ]);
    }

    public function testResetPasswordActivatesInvitedUser(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'invited@example.com',
            'status' => 'invited',
            'invited_by' => 2,
            'invited_at' => '2024-01-01 00:00:00',
        ]);

        $service = $this->createServiceWithUser($user);

        $result = $service->resetPassword(new \App\DTO\Request\Identity\ResetPasswordRequestDTO([
            'email' => 'invited@example.com',
            'token' => self::VALID_RESET_TOKEN,
            'password' => 'NewPassword123!',
        ]));

        $this->assertTrue($result);
    }

    public function testResetPasswordThrowsExceptionForMissingFields(): void
    {
        $this->expectException(ValidationException::class);

        new \App\DTO\Request\Identity\ResetPasswordRequestDTO([
            'email' => 'test@example.com',
            'token' => self::VALID_RESET_TOKEN,
            // Missing password
        ]);
    }
}
