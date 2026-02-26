<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entities\UserEntity;
use App\Exceptions\BadRequestException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Interfaces\AuditServiceInterface;
use App\Interfaces\EmailServiceInterface;
use App\Models\UserModel;
use App\Services\VerificationService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * VerificationService Unit Tests
 *
 * Tests email verification flow with mocked dependencies.
 */
class VerificationServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected VerificationService $service;
    protected EmailServiceInterface $mockEmailService;
    protected AuditServiceInterface $mockAuditService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockEmailService = $this->createMock(EmailServiceInterface::class);
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
    private function createServiceWithUser(?UserEntity $user, bool $allowUpdate = true): VerificationService
    {
        $mockUserModel = new class ($user, $allowUpdate) extends UserModel {
            private ?UserEntity $returnUser;
            private bool $allowUpdate;

            public function __construct(?UserEntity $user, bool $allowUpdate)
            {
                $this->returnUser = $user;
                $this->allowUpdate = $allowUpdate;
            }

            public function find($id = null)
            {
                return $this->returnUser;
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
                if (!$this->allowUpdate) {
                    return false;
                }
                // Simulate updating the user entity
                if ($this->returnUser && is_array($row)) {
                    foreach ($row as $key => $value) {
                        $this->returnUser->{$key} = $value;
                    }
                }
                return true;
            }
        };

        return new VerificationService(
            $mockUserModel,
            $this->mockEmailService,
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

    // ==================== SEND VERIFICATION EMAIL TESTS ====================

    public function testSendVerificationEmailCreatesTokenAndSends(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email_verified_at' => null,
        ]);

        $service = $this->createServiceWithUser($user);

        $this->mockEmailService->expects($this->once())
            ->method('queueTemplate')
            ->with(
                'verification',
                'test@example.com',
                $this->callback(function ($data) {
                    return isset($data['verification_link'])
                        && str_contains($data['verification_link'], '/verify-email?token=')
                        && str_contains($data['verification_link'], 'token=');
                })
            );

        $result = $service->sendVerificationEmail(1);

        $this->assertSuccessResponse($result);
    }

    public function testSendVerificationEmailUsesAllowedClientBaseUrl(): void
    {
        putenv('WEBAPP_BASE_URL=https://fallback.example.com');
        putenv('WEBAPP_ALLOWED_BASE_URLS=https://fallback.example.com,https://admin.example.com');

        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email_verified_at' => null,
        ]);

        $service = $this->createServiceWithUser($user);

        $this->mockEmailService->expects($this->once())
            ->method('queueTemplate')
            ->with(
                'verification',
                'test@example.com',
                $this->callback(function ($data) {
                    return isset($data['verification_link'])
                        && str_starts_with($data['verification_link'], 'https://admin.example.com/verify-email');
                })
            );

        $service->sendVerificationEmail(1, [
            'client_base_url' => 'https://admin.example.com',
        ]);
    }

    public function testSendVerificationEmailThrowsForAlreadyVerified(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'verified@example.com',
            'email_verified_at' => '2024-01-01 00:00:00',
        ]);

        $service = $this->createServiceWithUser($user);

        $this->expectException(ConflictException::class);

        $service->sendVerificationEmail(1);
    }

    public function testSendVerificationEmailThrowsForNonExistentUser(): void
    {
        $service = $this->createServiceWithUser(null);

        $this->expectException(NotFoundException::class);

        $service->sendVerificationEmail(999);
    }

    // ==================== VERIFY EMAIL TESTS ====================

    public function testVerifyEmailSuccessfully(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'email_verified_at' => null,
            'email_verification_token' => 'valid-token-123',
            'verification_token_expires' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);

        $service = $this->createServiceWithUser($user);

        $result = $service->verifyEmail(new \App\DTO\Request\Identity\VerificationRequestDTO([
            'token' => 'valid-token-123',
            'email' => 'test@example.com'
        ]));

        $this->assertInstanceOf(\App\DTO\Response\Identity\VerificationResponseDTO::class, $result);
        $data = $result->toArray();
        $this->assertEquals(1, $data['user_id']);

        // Verify the user entity was updated
        $this->assertNotNull($user->email_verified_at);
    }

    public function testVerifyEmailFailsForExpiredToken(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'email_verified_at' => null,
            'email_verification_token' => 'expired-token',
            'verification_token_expires' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $service = $this->createServiceWithUser($user);

        $this->expectException(BadRequestException::class);

        $service->verifyEmail(new \App\DTO\Request\Identity\VerificationRequestDTO([
            'token' => 'expired-token',
            'email' => 'test@example.com'
        ]));
    }

    public function testVerifyEmailFailsForInvalidToken(): void
    {
        $service = $this->createServiceWithUser(null);

        $this->expectException(NotFoundException::class);

        $service->verifyEmail(new \App\DTO\Request\Identity\VerificationRequestDTO([
            'token' => 'invalid-token',
            'email' => 'test@example.com'
        ]));
    }

    public function testVerifyEmailThrowsForEmptyToken(): void
    {
        // Validation now happens in DTO constructor
        $this->expectException(\App\Exceptions\ValidationException::class);

        new \App\DTO\Request\Identity\VerificationRequestDTO(['token' => '']);
    }

    // ==================== RESEND VERIFICATION TESTS ====================

    public function testResendVerificationSuccessfully(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'email_verified_at' => null,
        ]);

        $service = $this->createServiceWithUser($user);

        $this->mockEmailService->expects($this->once())
            ->method('queueTemplate')
            ->with('verification', 'test@example.com', $this->anything());

        $result = $service->resendVerification(1);

        $this->assertSuccessResponse($result);
    }

    public function testResendVerificationThrowsForAlreadyVerified(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'verified@example.com',
            'email_verified_at' => '2024-01-01 00:00:00',
        ]);

        $service = $this->createServiceWithUser($user);

        $this->expectException(ConflictException::class);

        $service->resendVerification(1);
    }

    public function testResendVerificationThrowsForNonExistentUser(): void
    {
        $service = $this->createServiceWithUser(null);

        $this->expectException(NotFoundException::class);

        $service->resendVerification(999);
    }
}
