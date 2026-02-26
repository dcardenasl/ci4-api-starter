<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\Request\Auth\LoginRequestDTO;
use App\DTO\Request\Auth\RegisterRequestDTO;
use App\Entities\UserEntity;
use App\Exceptions\AuthenticationException;
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
        $service = $this->createServiceWithUserQuery($user);

        // We bypass is_unique validation in tests if database is not ready,
        // but here we expect the DTO to validate.
        // For unit tests, we might need to mock the validation or ensure the email is "unique" enough.

        $result = $service->register(new RegisterRequestDTO([
            'email' => 'new-unique-'.time().'@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
            'password' => 'StrongPass123!',
        ]));

        $this->assertInstanceOf(\App\Interfaces\DataTransferObjectInterface::class, $result);
    }

    public function testMeReturnsUserProfile(): void
    {
        $user = new UserEntity(['id' => 1, 'email' => 'test@example.com']);
        $service = $this->createServiceWithUserQuery($user);

        $result = $service->me(1);
        $this->assertEquals('test@example.com', $result['email']);
    }
}
