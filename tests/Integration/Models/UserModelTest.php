<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * UserModel Integration Tests
 *
 * Tests UserModel with real database operations.
 * Requires test database configured in phpunit.xml
 */
class UserModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new UserModel();
    }

    // ==================== BASIC CRUD TESTS ====================

    public function testInsertCreatesUser(): void
    {
        $userData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ];

        $userId = $this->userModel->insert($userData);

        $this->assertIsInt($userId);
        $this->assertGreaterThan(0, $userId);
    }

    public function testFindReturnsUserEntity(): void
    {
        $userId = $this->userModel->insert([
            'username' => 'findtest',
            'email' => 'find@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $user = $this->userModel->find($userId);

        $this->assertInstanceOf(\App\Entities\UserEntity::class, $user);
        $this->assertEquals('findtest', $user->username);
        $this->assertEquals('find@example.com', $user->email);
    }

    public function testUpdateModifiesUser(): void
    {
        $userId = $this->userModel->insert([
            'username' => 'updatetest',
            'email' => 'update@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->userModel->update($userId, ['username' => 'updated']);

        $this->assertTrue($result);

        $user = $this->userModel->find($userId);
        $this->assertEquals('updated', $user->username);
    }

    public function testSoftDeleteRemovesFromResults(): void
    {
        $userId = $this->userModel->insert([
            'username' => 'deletetest',
            'email' => 'delete@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $this->userModel->delete($userId);

        $user = $this->userModel->find($userId);
        $this->assertNull($user);

        // But can be found with withDeleted
        $deletedUser = $this->userModel->withDeleted()->find($userId);
        $this->assertNotNull($deletedUser);
        $this->assertNotNull($deletedUser->deleted_at);
    }

    // ==================== VALIDATION TESTS ====================

    public function testValidationRejectsInvalidEmail(): void
    {
        $result = $this->userModel->validate([
            'username' => 'testuser',
            'email' => 'invalid-email',
            'password' => 'ValidPass123!',
        ]);

        $this->assertFalse($result);
        $this->assertArrayHasKey('email', $this->userModel->errors());
    }

    public function testValidationRejectsWeakPassword(): void
    {
        $result = $this->userModel->validate([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'weak',
        ]);

        $this->assertFalse($result);
        $this->assertArrayHasKey('password', $this->userModel->errors());
    }

    public function testValidationRejectsDuplicateEmail(): void
    {
        $this->userModel->insert([
            'username' => 'user1',
            'email' => 'duplicate@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->userModel->insert([
            'username' => 'user2',
            'email' => 'duplicate@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $this->assertFalse($result);
        $this->assertArrayHasKey('email', $this->userModel->errors());
    }

    public function testValidationRejectsDuplicateUsername(): void
    {
        $this->userModel->insert([
            'username' => 'sameuser',
            'email' => 'user1@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->userModel->insert([
            'username' => 'sameuser',
            'email' => 'user2@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $this->assertFalse($result);
        $this->assertArrayHasKey('username', $this->userModel->errors());
    }

    // ==================== TRAIT TESTS ====================

    public function testFilterableTraitFiltersResults(): void
    {
        $this->userModel->insert([
            'username' => 'admin1',
            'email' => 'admin1@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'admin',
        ]);

        $this->userModel->insert([
            'username' => 'user1',
            'email' => 'user1@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $admins = $this->userModel->filter(['role' => ['eq' => 'admin']])->findAll();

        $this->assertCount(1, $admins);
        $this->assertEquals('admin', $admins[0]->role);
    }

    public function testSearchableTraitSearchesResults(): void
    {
        $this->userModel->insert([
            'username' => 'johnsmith',
            'email' => 'john@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $this->userModel->insert([
            'username' => 'janedoe',
            'email' => 'jane@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $results = $this->userModel->search('john')->findAll();

        $this->assertCount(1, $results);
        $this->assertEquals('johnsmith', $results[0]->username);
    }
}
