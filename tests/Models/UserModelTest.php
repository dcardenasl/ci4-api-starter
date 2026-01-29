<?php

namespace Tests\Models;

use App\Entities\UserEntity;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class UserModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected UserModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new UserModel();
        $this->seedDatabase();
    }

    protected function seedDatabase(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call('Tests\Support\Database\Seeds\TestUserSeeder');
    }

    public function testFindAll()
    {
        $users = $this->model->findAll();

        $this->assertIsArray($users);
        $this->assertGreaterThanOrEqual(2, count($users));
        $this->assertInstanceOf(UserEntity::class, $users[0]);
    }

    public function testFindById()
    {
        $user = $this->model->find(1);

        $this->assertInstanceOf(UserEntity::class, $user);
        $this->assertEquals('testuser', $user->username);
        $this->assertEquals('test@example.com', $user->email);
    }

    public function testFindNonExistent()
    {
        $user = $this->model->find(9999);

        $this->assertNull($user);
    }

    public function testInsertSuccess()
    {
        $data = [
            'username' => 'modeltest',
            'email'    => 'modeltest@example.com',
            'password' => password_hash('Testpass1', PASSWORD_BCRYPT),
            'role'     => 'user',
        ];

        $userId = $this->model->insert($data);

        $this->assertIsInt($userId);
        $this->assertGreaterThan(0, $userId);

        // Verify inserted data
        $user = $this->model->find($userId);
        $this->assertEquals('modeltest', $user->username);
    }

    public function testInsertValidationError()
    {
        $data = [
            'username' => 'ab', // Too short (min 3)
            'email'    => 'invalid-email',
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $this->assertNotEmpty($this->model->errors());
    }

    public function testInsertDuplicateUsername()
    {
        $data = [
            'username' => 'testuser', // Already exists
            'email'    => 'newemail@example.com',
            'password' => password_hash('Pass1234', PASSWORD_BCRYPT),
            'role'     => 'user',
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $errors = $this->model->errors();
        $this->assertArrayHasKey('username', $errors);
    }

    public function testInsertDuplicateEmail()
    {
        $data = [
            'username' => 'newusername',
            'email'    => 'test@example.com', // Already exists
            'password' => password_hash('Pass1234', PASSWORD_BCRYPT),
            'role'     => 'user',
        ];

        $result = $this->model->insert($data);

        $this->assertFalse($result);
        $errors = $this->model->errors();
        $this->assertArrayHasKey('email', $errors);
    }

    public function testUpdateSuccess()
    {
        $data = [
            'username' => 'updatedtestuser',
            'email'    => 'updated@example.com',
        ];

        $result = $this->model->update(1, $data);

        $this->assertTrue($result);

        // Verify updated data
        $user = $this->model->find(1);
        $this->assertEquals('updatedtestuser', $user->username);
        $this->assertEquals('updated@example.com', $user->email);
    }

    public function testUpdateValidationError()
    {
        $data = [
            'username' => 'u', // Too short
            'email'    => 'invalid',
        ];

        $result = $this->model->update(1, $data);

        $this->assertFalse($result);
        $this->assertNotEmpty($this->model->errors());
    }

    public function testUpdateDuplicateUsername()
    {
        $data = [
            'username' => 'adminuser', // Exists as user ID 2
        ];

        $result = $this->model->update(1, $data);

        $this->assertFalse($result);
        $errors = $this->model->errors();
        $this->assertArrayHasKey('username', $errors);
    }

    public function testDelete()
    {
        $result = $this->model->delete(1);

        $this->assertTrue($result);

        // Verify soft deleted (should not find by default)
        $user = $this->model->find(1);
        $this->assertNull($user);

        // Verify exists with soft deletes disabled
        $user = $this->model->withDeleted()->find(1);
        $this->assertInstanceOf(UserEntity::class, $user);
        $this->assertNotNull($user->deleted_at);
    }

    public function testTimestamps()
    {
        $data = [
            'username' => 'timestamptest',
            'email'    => 'timestamp@example.com',
            'password' => password_hash('Pass1234', PASSWORD_BCRYPT),
            'role'     => 'user',
        ];

        $userId = $this->model->insert($data);
        $user = $this->model->find($userId);

        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);

        // Update and check updated_at changes
        $oldUpdatedAt = $user->updated_at;
        sleep(1);

        $this->model->update($userId, ['username' => 'timestamptest2']);
        $user = $this->model->find($userId);

        $this->assertNotEquals($oldUpdatedAt, $user->updated_at);
    }

    public function testReturnType()
    {
        $user = $this->model->find(1);

        $this->assertInstanceOf(UserEntity::class, $user);
        $this->assertIsString($user->username);
        $this->assertIsString($user->email);
        $this->assertIsString($user->role);
    }

    public function testEntityToArray()
    {
        $user = $this->model->find(1);
        $array = $user->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('username', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('role', $array);

        // Password should not be in array (security feature)
        $this->assertArrayNotHasKey('password', $array);
    }

    public function testWhereClause()
    {
        $users = $this->model->where('role', 'admin')->findAll();

        $this->assertIsArray($users);
        $this->assertCount(1, $users);
        $this->assertEquals('adminuser', $users[0]->username);
    }

    public function testOrWhereClause()
    {
        $user = $this->model
            ->where('username', 'testuser')
            ->orWhere('email', 'test@example.com')
            ->first();

        $this->assertInstanceOf(UserEntity::class, $user);
        $this->assertEquals('testuser', $user->username);
    }

    public function testAllowedFieldsProtection()
    {
        $data = [
            'username'   => 'hacktest',
            'email'      => 'hack@example.com',
            'password'   => password_hash('Pass1234', PASSWORD_BCRYPT),
            'role'       => 'user',
            'created_at' => '2020-01-01', // Should be ignored (not in allowedFields)
        ];

        $userId = $this->model->insert($data);

        $this->assertIsInt($userId);
        $this->assertGreaterThan(0, $userId);

        // Verify created_at was set automatically, not from input
        $user = $this->model->find($userId);
        $this->assertNotEquals('2020-01-01', $user->created_at);
    }
}
