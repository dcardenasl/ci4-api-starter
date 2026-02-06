<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entities\UserEntity;
use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockUserModel = $this->createMock(UserModel::class);
        $this->service = new UserService($this->mockUserModel);
    }

    // ==================== SHOW TESTS ====================

    public function testShowReturnsUserData(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'username' => 'testuser',
            'email' => 'test@example.com',
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
        $this->assertEquals('testuser', $result['data']['username']);
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
            'username' => 'newuser',
            'email' => 'new@example.com',
            'role' => 'user',
        ]);

        $this->mockUserModel
            ->method('find')
            ->willReturn($createdUser);

        $result = $this->service->store([
            'username' => 'newuser',
            'email' => 'new@example.com',
            'password' => 'ValidPass123!',
        ]);

        $this->assertSuccessResponse($result);
        $this->assertEquals(1, $result['data']['id']);
    }

    public function testStoreWithInvalidDataThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->store([
            'username' => 'newuser',
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
            'username' => 'newuser',
            'email' => 'new@example.com',
            'password' => 'ValidPass123!',
        ]);
    }

    // ==================== UPDATE TESTS ====================

    public function testUpdateModifiesUser(): void
    {
        $existingUser = $this->createUserEntity([
            'id' => 1,
            'username' => 'olduser',
            'email' => 'old@example.com',
        ]);

        $updatedUser = $this->createUserEntity([
            'id' => 1,
            'username' => 'newuser',
            'email' => 'new@example.com',
        ]);

        $this->mockUserModel
            ->method('find')
            ->willReturnOnConsecutiveCalls($existingUser, $updatedUser);

        $this->mockUserModel
            ->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(function ($data) {
                return $data['username'] === 'newuser' && $data['email'] === 'new@example.com';
            }))
            ->willReturn(true);

        $result = $this->service->update([
            'id' => 1,
            'username' => 'newuser',
            'email' => 'new@example.com',
        ]);

        $this->assertSuccessResponse($result);
        $this->assertEquals('newuser', $result['data']['username']);
    }

    public function testUpdateWithoutIdThrowsException(): void
    {
        $this->expectException(BadRequestException::class);

        $this->service->update(['username' => 'newuser']);
    }

    public function testUpdateNonExistentUserThrowsNotFoundException(): void
    {
        $this->mockUserModel
            ->method('find')
            ->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->update(['id' => 999, 'username' => 'test']);
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
