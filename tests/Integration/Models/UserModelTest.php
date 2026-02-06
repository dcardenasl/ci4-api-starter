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

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';  // Use app migrations

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
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
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
            'email' => 'find@example.com',
            'first_name' => 'Find',
            'last_name' => 'Test',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $user = $this->userModel->find($userId);

        $this->assertInstanceOf(\App\Entities\UserEntity::class, $user);
        $this->assertEquals('find@example.com', $user->email);
    }

    public function testUpdateModifiesUser(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'update@example.com',
            'first_name' => 'Update',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->userModel->update($userId, ['first_name' => 'Updated']);

        $this->assertTrue($result);

        $user = $this->userModel->find($userId);
        $this->assertEquals('Updated', $user->first_name);
    }

    public function testSoftDeleteRemovesFromResults(): void
    {
        $userId = $this->userModel->insert([
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
            'email' => 'invalid-email',
            'password' => 'ValidPass123!',
        ]);

        $this->assertFalse($result);
        $this->assertArrayHasKey('email', $this->userModel->errors());
    }

    public function testValidationRejectsDuplicateEmail(): void
    {
        $this->userModel->insert([
            'email' => 'duplicate@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->userModel->insert([
            'email' => 'duplicate@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $this->assertFalse($result);
        $this->assertArrayHasKey('email', $this->userModel->errors());
    }

    // ==================== TRAIT TESTS ====================

    public function testFilterableTraitFiltersResults(): void
    {
        $this->userModel->insert([
            'email' => 'admin1@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'admin',
        ]);

        $this->userModel->insert([
            'email' => 'user1@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        // Use applyFilters() method from Filterable trait
        $admins = $this->userModel->applyFilters(['role' => ['eq' => 'admin']])->findAll();

        $this->assertCount(1, $admins);
        $this->assertEquals('admin', $admins[0]->role);
    }

    public function testSearchableTraitMethodExists(): void
    {
        // Verify Searchable trait methods are available
        $this->assertTrue(method_exists($this->userModel, 'search'));
        $this->assertTrue(method_exists($this->userModel, 'getSearchableFields'));

        // Verify searchable fields are defined
        $searchableFields = $this->userModel->getSearchableFields();
        $this->assertContains('email', $searchableFields);
        $this->assertContains('first_name', $searchableFields);
        $this->assertContains('last_name', $searchableFields);
    }
}
