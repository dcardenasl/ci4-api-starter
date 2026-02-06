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
        $this->userService = new UserService($this->userModel);
    }

    // ==================== STORE INTEGRATION TESTS ====================

    public function testStoreCreatesUserInDatabase(): void
    {
        $result = $this->userService->store([
            'username' => 'integrationuser',
            'email' => 'integration@example.com',
            'password' => 'ValidPass123!',
        ]);

        $this->assertSuccessResponse($result);
        $this->assertArrayHasKey('id', $result['data']);

        // Verify in database
        $user = $this->userModel->find($result['data']['id']);
        $this->assertNotNull($user);
        $this->assertEquals('integrationuser', $user->username);
    }

    public function testStoreHashesPasswordBeforeSaving(): void
    {
        $plainPassword = 'ValidPass123!';

        $result = $this->userService->store([
            'username' => 'hashtest',
            'email' => 'hash@example.com',
            'password' => $plainPassword,
        ]);

        $user = $this->userModel->find($result['data']['id']);

        // Password should not be stored as plain text
        $this->assertNotEquals($plainPassword, $user->password);
        // But should verify correctly
        $this->assertTrue(password_verify($plainPassword, $user->password));
    }

    public function testStoreRejectsInvalidData(): void
    {
        $this->expectException(ValidationException::class);

        $this->userService->store([
            'username' => 'ab', // Too short
            'email' => 'invalid',
            'password' => 'weak',
        ]);
    }

    // ==================== SHOW INTEGRATION TESTS ====================

    public function testShowReturnsExistingUser(): void
    {
        $userId = $this->userModel->insert([
            'username' => 'showtest',
            'email' => 'show@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->userService->show(['id' => $userId]);

        $this->assertSuccessResponse($result);
        $this->assertEquals('showtest', $result['data']['username']);
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
            'username' => 'oldname',
            'email' => 'old@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->userService->update([
            'id' => $userId,
            'username' => 'newname',
            'email' => 'new@example.com',
        ]);

        $this->assertSuccessResponse($result);

        // Verify changes persisted
        $user = $this->userModel->find($userId);
        $this->assertEquals('newname', $user->username);
        $this->assertEquals('new@example.com', $user->email);
    }

    // ==================== DESTROY INTEGRATION TESTS ====================

    public function testDestroySoftDeletesUser(): void
    {
        $userId = $this->userModel->insert([
            'username' => 'todelete',
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
                'username' => "user{$i}",
                'email' => "user{$i}@example.com",
                'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
                'role' => 'user',
            ]);
        }

        $result = $this->userService->index(['page' => 1, 'limit' => 3]);

        $this->assertPaginatedResponse($result);
        $this->assertCount(3, $result['data']);
        $this->assertEquals(5, $result['meta']['total']);
    }

    public function testIndexFiltersUsers(): void
    {
        $this->userModel->insert([
            'username' => 'adminuser',
            'email' => 'admin@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'admin',
        ]);

        $this->userModel->insert([
            'username' => 'normaluser',
            'email' => 'normal@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->userService->index([
            'filter' => ['role' => ['eq' => 'admin']],
        ]);

        $this->assertPaginatedResponse($result);
        $this->assertCount(1, $result['data']);
        $this->assertEquals('admin', $result['data'][0]['role']);
    }
}
