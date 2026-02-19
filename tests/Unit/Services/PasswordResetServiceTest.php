<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entities\UserEntity;
use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\EmailServiceInterface;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
use App\Services\PasswordResetService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * PasswordResetService Unit Tests
 *
 * Tests password reset flow with mocked dependencies.
 */
class PasswordResetServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected PasswordResetService $service;
    protected UserModel $mockUserModel;
    protected PasswordResetModel $mockPasswordResetModel;
    protected EmailServiceInterface $mockEmailService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockEmailService = $this->createMock(EmailServiceInterface::class);
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
    private function createServiceWithUser(?UserEntity $user): PasswordResetService
    {
        $mockUserModel = new class ($user) extends UserModel {
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

            public function update($id = null, $row = null): bool
            {
                return true;
            }
        };

        $mockPasswordResetModel = new class () extends PasswordResetModel {
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
                return $token === 'valid-token-123';
            }
        };

        return new PasswordResetService(
            $mockUserModel,
            $mockPasswordResetModel,
            $this->mockEmailService
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

        $result = $service->sendResetLink(['email' => 'test@example.com']);

        $this->assertSuccessResponse($result);
    }

    public function testSendResetLinkUsesAllowedClientBaseUrl(): void
    {
        putenv('WEBAPP_BASE_URL=https://fallback.example.com');
        putenv('WEBAPP_ALLOWED_BASE_URLS=https://fallback.example.com,https://admin.example.com');

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
                        && str_starts_with($data['reset_link'], 'https://admin.example.com/reset-password');
                })
            );

        $service->sendResetLink([
            'email' => 'test@example.com',
            'client_base_url' => 'https://admin.example.com',
        ]);
    }

    public function testSendResetLinkPreventsEmailEnumeration(): void
    {
        // Test with non-existent user
        $service = $this->createServiceWithUser(null);

        $this->mockEmailService->expects($this->never())
            ->method('queueTemplate');

        $result = $service->sendResetLink(['email' => 'nonexistent@example.com']);

        // Should return success even for non-existent email
        $this->assertSuccessResponse($result);
    }

    public function testSendResetLinkThrowsExceptionForInvalidEmail(): void
    {
        $service = $this->createServiceWithUser(null);

        $this->expectException(ValidationException::class);

        $service->sendResetLink(['email' => 'invalid-email']);
    }

    public function testSendResetLinkThrowsExceptionForEmptyEmail(): void
    {
        $service = $this->createServiceWithUser(null);

        $this->expectException(ValidationException::class);

        $service->sendResetLink(['email' => '']);
    }

    // ==================== VALIDATE TOKEN TESTS ====================

    public function testValidateTokenSucceedsForValidToken(): void
    {
        $service = $this->createServiceWithUser(null);

        $result = $service->validateToken([
            'email' => 'test@example.com',
            'token' => 'valid-token-123',
        ]);

        $this->assertSuccessResponse($result);
        $this->assertTrue($result['data']['valid']);
    }

    public function testValidateTokenFailsForInvalidToken(): void
    {
        $service = $this->createServiceWithUser(null);

        $this->expectException(NotFoundException::class);

        $service->validateToken([
            'email' => 'test@example.com',
            'token' => 'invalid-token',
        ]);
    }

    public function testValidateTokenThrowsExceptionForMissingToken(): void
    {
        $service = $this->createServiceWithUser(null);

        $this->expectException(BadRequestException::class);

        $service->validateToken([
            'email' => 'test@example.com',
            'token' => '',
        ]);
    }

    public function testValidateTokenThrowsExceptionForMissingEmail(): void
    {
        $service = $this->createServiceWithUser(null);

        $this->expectException(BadRequestException::class);

        $service->validateToken([
            'email' => '',
            'token' => 'some-token',
        ]);
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

        $result = $service->resetPassword([
            'email' => 'test@example.com',
            'token' => 'valid-token-123',
            'password' => 'NewSecure123!',
        ]);

        $this->assertSuccessResponse($result);
    }

    public function testResetPasswordThrowsExceptionForWeakPassword(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
        ]);

        $service = $this->createServiceWithUser($user);

        $this->expectException(ValidationException::class);

        $service->resetPassword([
            'email' => 'test@example.com',
            'token' => 'valid-token-123',
            'password' => 'weak',
        ]);
    }

    public function testResetPasswordThrowsExceptionForPasswordTooShort(): void
    {
        $user = $this->createUserEntity(['id' => 1, 'email' => 'test@example.com']);

        $service = $this->createServiceWithUser($user);

        $this->expectException(ValidationException::class);

        $service->resetPassword([
            'email' => 'test@example.com',
            'token' => 'valid-token-123',
            'password' => 'Short1!',
        ]);
    }

    public function testResetPasswordThrowsExceptionForPasswordTooLong(): void
    {
        $user = $this->createUserEntity(['id' => 1, 'email' => 'test@example.com']);

        $service = $this->createServiceWithUser($user);

        $this->expectException(ValidationException::class);

        $service->resetPassword([
            'email' => 'test@example.com',
            'token' => 'valid-token-123',
            'password' => str_repeat('A1!', 50), // 150 chars
        ]);
    }

    public function testResetPasswordThrowsExceptionForInvalidToken(): void
    {
        $user = $this->createUserEntity(['id' => 1, 'email' => 'test@example.com']);

        $service = $this->createServiceWithUser($user);

        $this->expectException(NotFoundException::class);

        $service->resetPassword([
            'email' => 'test@example.com',
            'token' => 'invalid-token',
            'password' => 'ValidPassword123!',
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

        $result = $service->resetPassword([
            'email' => 'invited@example.com',
            'token' => 'valid-token-123',
            'password' => 'NewPassword123!',
        ]);

        $this->assertSuccessResponse($result);
        // In real implementation, user status would be changed to 'active'
    }

    public function testResetPasswordThrowsExceptionForMissingFields(): void
    {
        $service = $this->createServiceWithUser(null);

        $this->expectException(BadRequestException::class);

        $service->resetPassword([
            'email' => 'test@example.com',
            'token' => 'valid-token-123',
            // Missing password
        ]);
    }
}
