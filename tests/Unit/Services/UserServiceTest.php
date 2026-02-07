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
use App\Services\UserService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * UserService Unit Tests
 *
 * Tests CRUD operations with mocked dependencies.
 */
class UserServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected UserService $service;
    protected UserModel $mockUserModel;
    protected EmailServiceInterface $mockEmailService;
    protected PasswordResetModel $mockPasswordResetModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockUserModel = $this->createMock(UserModel::class);
        $this->mockEmailService = $this->createMock(EmailServiceInterface::class);
        $this->mockPasswordResetModel = $this->createMock(PasswordResetModel::class);
        $this->service = new UserService(
            $this->mockUserModel,
            $this->mockEmailService,
            $this->mockPasswordResetModel
        );
    }

    // ==================== SHOW TESTS ====================

    public function testShowReturnsUserData(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'role' => 'user',
        ]);

        $this->mockUserModel
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($user);

        $result = $this->service->show(['id' => 1]);

        $this->assertSuccessResponse($result);
        $this->assertEquals(1, $result['data']['id']);
        $this->assertEquals('test@example.com', $result['data']['email']);
    }

    public function testShowWithoutIdThrowsException(): void
    {
        $this->expectException(BadRequestException::class);

        $this->service->show([]);
    }

    public function testShowWithNonExistentUserThrowsNotFoundException(): void
    {
        $this->mockUserModel
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->show(['id' => 999]);
    }

    // ==================== STORE TESTS ====================

    public function testStoreCreatesUser(): void
    {
        $this->mockUserModel
            ->expects($this->once())
            ->method('insert')
            ->willReturn(1);

        $createdUser = $this->createUserEntity([
            'id' => 1,
            'email' => 'new@example.com',
            'role' => 'user',
        ]);

        $this->mockUserModel
            ->method('find')
            ->willReturn($createdUser);

        $result = $this->service->store([
            'email' => 'new@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
            'password' => 'ValidPass123!',
        ]);

        $this->assertSuccessResponse($result);
        $this->assertEquals(1, $result['data']['id']);
    }

    public function testStoreWithInvalidDataThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->store([
            'email' => 'invalid',
            'password' => 'ValidPass123!',
        ]);
    }

    public function testStoreHashesPassword(): void
    {
        $this->mockUserModel
            ->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) {
                // Password should be hashed, not plain text
                return isset($data['password'])
                    && $data['password'] !== 'ValidPass123!'
                    && password_verify('ValidPass123!', $data['password']);
            }))
            ->willReturn(1);

        $createdUser = $this->createUserEntity(['id' => 1]);
        $this->mockUserModel->method('find')->willReturn($createdUser);

        $this->service->store([
            'email' => 'new@example.com',
            'password' => 'ValidPass123!',
        ]);
    }

    // ==================== UPDATE TESTS ====================

    public function testUpdateModifiesUser(): void
    {
        $existingUser = $this->createUserEntity([
            'id' => 1,
            'email' => 'old@example.com',
            'first_name' => 'Old',
        ]);

        $updatedUser = $this->createUserEntity([
            'id' => 1,
            'email' => 'new@example.com',
            'first_name' => 'New',
        ]);

        $this->mockUserModel
            ->method('find')
            ->willReturnOnConsecutiveCalls($existingUser, $updatedUser);

        $this->mockUserModel
            ->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(function ($data) {
                return $data['email'] === 'new@example.com' && $data['first_name'] === 'New';
            }))
            ->willReturn(true);

        $result = $this->service->update([
            'id' => 1,
            'email' => 'new@example.com',
            'first_name' => 'New',
        ]);

        $this->assertSuccessResponse($result);
        $this->assertEquals('new@example.com', $result['data']['email']);
    }

    public function testUpdateWithoutIdThrowsException(): void
    {
        $this->expectException(BadRequestException::class);

        $this->service->update(['email' => 'new@example.com']);
    }

    public function testUpdateNonExistentUserThrowsNotFoundException(): void
    {
        $this->mockUserModel
            ->method('find')
            ->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->update(['id' => 999, 'email' => 'test@example.com']);
    }

    public function testUpdateWithoutFieldsThrowsException(): void
    {
        $existingUser = $this->createUserEntity(['id' => 1]);
        $this->mockUserModel->method('find')->willReturn($existingUser);

        $this->expectException(BadRequestException::class);

        $this->service->update(['id' => 1]);
    }

    // ==================== DESTROY TESTS ====================

    public function testDestroyDeletesUser(): void
    {
        $existingUser = $this->createUserEntity(['id' => 1]);

        $this->mockUserModel
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($existingUser);

        $this->mockUserModel
            ->expects($this->once())
            ->method('delete')
            ->with(1)
            ->willReturn(true);

        $result = $this->service->destroy(['id' => 1]);

        $this->assertSuccessResponse($result);
    }

    public function testDestroyWithoutIdThrowsException(): void
    {
        $this->expectException(BadRequestException::class);

        $this->service->destroy([]);
    }

    public function testDestroyNonExistentUserThrowsNotFoundException(): void
    {
        $this->mockUserModel
            ->method('find')
            ->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->destroy(['id' => 999]);
    }

    // ==================== HELPER METHODS ====================

    private function createUserEntity(array $data): UserEntity
    {
        $user = new UserEntity();
        foreach ($data as $key => $value) {
            $user->{$key} = $value;
        }
        return $user;
    }
}
