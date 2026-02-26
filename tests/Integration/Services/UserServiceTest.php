<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\UserModel;
use App\Services\UserService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * UserService Integration Tests
 *
 * Tests UserService with real database operations.
 * These tests verify the full flow from service to database.
 */
class UserServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CustomAssertionsTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';  // Use app migrations

    protected UserService $userService;
    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userModel = new UserModel();
        $this->userService = new UserService(
            $this->userModel,
            \Config\Services::emailService(),
            new \App\Models\PasswordResetModel(),
            \Config\Services::auditService()
        );
    }

    // ==================== STORE INTEGRATION TESTS ====================

    public function testStoreCreatesUserInDatabase(): void
    {
        $result = $this->userService->store([
            'email' => 'integration@example.com',
            'first_name' => 'Integration',
            'last_name' => 'User',
        ]);

        $this->assertInstanceOf(\App\DTO\Response\Users\UserResponseDTO::class, $result);
        $data = $result->toArray();
        $this->assertArrayHasKey('id', $data);

        // Verify in database
        $user = $this->userModel->find($data['id']);
        $this->assertNotNull($user);
        $this->assertEquals('integration@example.com', $user->email);
    }

    public function testStoreGeneratesAndHashesPasswordBeforeSaving(): void
    {
        $result = $this->userService->store([
            'email' => 'hash@example.com',
        ]);

        $data = $result->toArray();
        $user = $this->userModel->find($data['id']);

        $this->assertNotEmpty($user->password);
        $this->assertStringStartsWith('$2', $user->password);
    }

    public function testStoreRejectsInvalidData(): void
    {
        $this->expectException(ValidationException::class);

        $this->userService->store([
            'email' => 'invalid',
        ]);
    }

    // ==================== SHOW INTEGRATION TESTS ====================

    public function testShowReturnsExistingUser(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'show@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->userService->show(['id' => $userId]);

        $this->assertInstanceOf(\App\DTO\Response\Users\UserResponseDTO::class, $result);
        $data = $result->toArray();
        $this->assertEquals('show@example.com', $data['email']);
    }

    public function testShowThrowsNotFoundForMissingUser(): void
    {
        $this->expectException(NotFoundException::class);

        $this->userService->show(['id' => 99999]);
    }

    // ==================== UPDATE INTEGRATION TESTS ====================

    public function testUpdateModifiesUserInDatabase(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'old@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->userService->update([
            'id' => $userId,
            'email' => 'new@example.com',
            'first_name' => 'New',
            'last_name' => 'Name',
        ]);

        $this->assertInstanceOf(\App\DTO\Response\Users\UserResponseDTO::class, $result);
        $data = $result->toArray();

        // Verify changes persisted
        $user = $this->userModel->find($userId);
        $this->assertEquals('new@example.com', $user->email);
        $this->assertEquals('New', $user->first_name);
        $this->assertEquals('Name', $user->last_name);
    }

    // ==================== DESTROY INTEGRATION TESTS ====================

    public function testDestroySoftDeletesUser(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'delete@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->userService->destroy(['id' => $userId]);

        $this->assertSuccessResponse($result);

        // Should not find user normally
        $user = $this->userModel->find($userId);
        $this->assertNull($user);

        // But should still exist with soft delete
        $deletedUser = $this->userModel->withDeleted()->find($userId);
        $this->assertNotNull($deletedUser);
        $this->assertNotNull($deletedUser->deleted_at);
    }

    // ==================== INDEX INTEGRATION TESTS ====================

    public function testIndexReturnsPaginatedUsers(): void
    {
        // Create multiple users
        for ($i = 1; $i <= 5; $i++) {
            $this->userModel->insert([
                'email' => "user{$i}@example.com",
                'first_name' => "User{$i}",
                'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
                'role' => 'user',
            ]);
        }

        $dto = new \App\DTO\Request\Users\UserIndexRequestDTO(['page' => 1, 'per_page' => 3]);
        $result = $this->userService->index($dto);

        $this->assertIsArray($result);
        $this->assertCount(3, $result['data']);
        $this->assertEquals(5, $result['total']);
    }

    public function testIndexFiltersUsers(): void
    {
        $this->userModel->insert([
            'email' => 'admin@example.com',
            'first_name' => 'Admin',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'admin',
        ]);

        $this->userModel->insert([
            'email' => 'normal@example.com',
            'first_name' => 'Normal',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $dto = new \App\DTO\Request\Users\UserIndexRequestDTO([
            'role' => 'admin',
        ]);
        $result = $this->userService->index($dto);

        $this->assertIsArray($result);
        $this->assertCount(1, $result['data']);
        $this->assertEquals('admin', $result['data'][0]->toArray()['role']);
    }
}
