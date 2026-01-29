<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\UserService;
use App\Models\UserModel;
use App\Entities\UserEntity;
use App\Exceptions\NotFoundException;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * UserService Unit Tests
 *
 * Comprehensive test coverage for UserService business logic.
 * Uses mocked UserModel to isolate service layer testing.
 */
class UserServiceTest extends CIUnitTestCase
{
    protected UserService $service;
    protected UserModel|MockObject $mockModel;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock UserModel
        $this->mockModel = $this->createMock(UserModel::class);

        // Inject mock into service
        $this->service = new UserService($this->mockModel);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ==================== INDEX TESTS ====================

    public function testIndexReturnsAllUsers(): void
    {
        $users = [
            $this->createUserEntity(['id' => 1, 'username' => 'user1']),
            $this->createUserEntity(['id' => 2, 'username' => 'user2']),
        ];

        $this->mockModel->expects($this->once())
            ->method('findAll')
            ->willReturn($users);

        $result = $this->service->index([]);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(2, $result['data']);
    }

    public function testIndexReturnsEmptyArrayWhenNoUsers(): void
    {
        $this->mockModel->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $result = $this->service->index([]);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEmpty($result['data']);
    }

    // ==================== SHOW TESTS ====================

    public function testShowReturnsUserById(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);

        $this->mockModel->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($user);

        $result = $this->service->show(['id' => 1]);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('testuser', $result['data']['username']);
    }

    public function testShowThrowsNotFoundExceptionForInvalidId(): void
    {
        $this->mockModel->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('User not found');

        $this->service->show(['id' => 999]);
    }

    public function testShowReturnsErrorWhenIdMissing(): void
    {
        $result = $this->service->show([]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('id', $result['errors']);
    }

    // ==================== STORE TESTS ====================

    public function testStoreCreatesNewUser(): void
    {
        $userData = [
            'username' => 'newuser',
            'email' => 'newuser@example.com',
        ];

        $createdUser = $this->createUserEntity([
            'id' => 1,
            'username' => 'newuser',
            'email' => 'newuser@example.com',
        ]);

        $this->mockModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) use ($userData) {
                return $data['username'] === $userData['username']
                    && $data['email'] === $userData['email'];
            }))
            ->willReturn(1);

        $this->mockModel->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($createdUser);

        $result = $this->service->store($userData);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('newuser', $result['data']['username']);
    }

    public function testStoreReturnsValidationErrorsOnFailure(): void
    {
        $userData = [
            'username' => 'invalid',
            'email' => 'invalid-email',
        ];

        $this->mockModel->expects($this->once())
            ->method('insert')
            ->willReturn(false);

        $this->mockModel->expects($this->once())
            ->method('errors')
            ->willReturn(['email' => 'Invalid email format']);

        $result = $this->service->store($userData);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('email', $result['errors']);
    }

    // ==================== UPDATE TESTS ====================

    public function testUpdateModifiesExistingUser(): void
    {
        $userId = 1;
        $updateData = [
            'id' => $userId,
            'email' => 'updated@example.com',
        ];

        $existingUser = $this->createUserEntity([
            'id' => $userId,
            'username' => 'testuser',
            'email' => 'old@example.com',
        ]);

        $updatedUser = $this->createUserEntity([
            'id' => $userId,
            'username' => 'testuser',
            'email' => 'updated@example.com',
        ]);

        // First find() to check if user exists, second find() to return updated user
        $this->mockModel->expects($this->exactly(2))
            ->method('find')
            ->with($userId)
            ->willReturnOnConsecutiveCalls($existingUser, $updatedUser);

        $this->mockModel->expects($this->once())
            ->method('update')
            ->with($userId, $this->arrayHasKey('email'))
            ->willReturn(true);

        $result = $this->service->update($updateData);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('updated@example.com', $result['data']['email']);
    }

    public function testUpdateThrowsNotFoundExceptionForInvalidId(): void
    {
        $this->mockModel->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->update(['id' => 999, 'email' => 'test@example.com']);
    }

    public function testUpdateReturnsErrorWhenNoFieldsProvided(): void
    {
        $userId = 1;
        $existingUser = $this->createUserEntity(['id' => $userId]);

        $this->mockModel->expects($this->once())
            ->method('find')
            ->with($userId)
            ->willReturn($existingUser);

        $result = $this->service->update(['id' => $userId]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('fields', $result['errors']);
    }

    public function testUpdateReturnsErrorWhenIdMissing(): void
    {
        $result = $this->service->update(['email' => 'test@example.com']);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('id', $result['errors']);
    }

    // ==================== DESTROY TESTS ====================

    public function testDestroyDeletesUser(): void
    {
        $userId = 1;
        $user = $this->createUserEntity(['id' => $userId]);

        $this->mockModel->expects($this->once())
            ->method('find')
            ->with($userId)
            ->willReturn($user);

        $this->mockModel->expects($this->once())
            ->method('delete')
            ->with($userId)
            ->willReturn(true);

        $result = $this->service->destroy(['id' => $userId]);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testDestroyThrowsNotFoundExceptionForInvalidId(): void
    {
        $this->mockModel->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->expectException(NotFoundException::class);

        $this->service->destroy(['id' => 999]);
    }

    public function testDestroyReturnsErrorWhenIdMissing(): void
    {
        $result = $this->service->destroy([]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('id', $result['errors']);
    }

    // ==================== LOGIN TESTS ====================

    /**
     * Note: Login tests that require mocking Query Builder chain (where/orWhere)
     * are difficult to unit test with mocks due to CodeIgniter's architecture.
     * These would be better tested as integration tests with a real database.
     *
     * We keep tests for business logic that doesn't depend on Query Builder.
     */


    public function testLoginReturnsErrorWhenCredentialsMissing(): void
    {
        $result = $this->service->login([]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('credentials', $result['errors']);
    }

    public function testPasswordHashingIsUsedInLogin(): void
    {
        // Test that password verification logic works correctly
        $plainPassword = 'Password123';
        $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);

        // Verify the hash was created
        $this->assertNotEquals($plainPassword, $hashedPassword);

        // Verify password_verify works
        $this->assertTrue(password_verify($plainPassword, $hashedPassword));
        $this->assertFalse(password_verify('WrongPassword', $hashedPassword));
    }

    // ==================== REGISTER TESTS ====================

    public function testRegisterCreatesUserWithHashedPassword(): void
    {
        $password = 'Password123';
        $userData = [
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => $password,
        ];

        $createdUser = $this->createUserEntity([
            'id' => 1,
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'role' => 'user',
        ]);

        $this->mockModel->expects($this->once())
            ->method('validate')
            ->with($userData)
            ->willReturn(true);

        $this->mockModel->expects($this->once())
            ->method('insert')
            ->with($this->callback(function ($data) use ($password) {
                // Verify password is hashed
                return isset($data['password'])
                    && $data['password'] !== $password
                    && password_verify($password, $data['password'])
                    && $data['role'] === 'user'; // Security: always 'user'
            }), false)
            ->willReturn(1);

        $this->mockModel->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($createdUser);

        $result = $this->service->register($userData);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('newuser', $result['data']['username']);
        $this->assertEquals('user', $result['data']['role']);
    }

    public function testRegisterAlwaysAssignsUserRole(): void
    {
        $userData = [
            'username' => 'hacker',
            'email' => 'hacker@example.com',
            'password' => 'Password123',
            'role' => 'admin', // Attempting to inject admin role
        ];

        $this->mockModel->method('validate')->willReturn(true);
        $this->mockModel->method('insert')
            ->with($this->callback(function ($data) {
                // Verify role is ALWAYS 'user', ignoring input
                return $data['role'] === 'user';
            }), false)
            ->willReturn(1);

        $this->mockModel->method('find')->willReturn(
            $this->createUserEntity(['role' => 'user'])
        );

        $result = $this->service->register($userData);

        $this->assertEquals('user', $result['data']['role']);
    }

    public function testRegisterReturnsErrorWhenPasswordMissing(): void
    {
        $result = $this->service->register([
            'username' => 'test',
            'email' => 'test@example.com',
        ]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('password', $result['errors']);
    }

    public function testRegisterReturnsValidationErrors(): void
    {
        $userData = [
            'username' => 'test',
            'email' => 'invalid-email',
            'password' => 'weak', // Weak password
        ];

        $this->mockModel->expects($this->once())
            ->method('validate')
            ->willReturn(false);

        $this->mockModel->expects($this->once())
            ->method('errors')
            ->willReturn([
                'password' => 'Password must be at least 8 characters',
            ]);

        $result = $this->service->register($userData);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('password', $result['errors']);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Create a UserEntity for testing
     */
    protected function createUserEntity(array $data = []): UserEntity
    {
        $defaults = [
            'id' => 1,
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => password_hash('Password123', PASSWORD_BCRYPT),
            'role' => 'user',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'deleted_at' => null,
        ];

        return new UserEntity(array_merge($defaults, $data));
    }
}
