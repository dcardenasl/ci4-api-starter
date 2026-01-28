<?php

namespace Tests\Services;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\UserService;
use App\Models\UserModel;

class UserServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = new UserService(new UserModel());
        $this->seedDatabase();
    }

    protected function seedDatabase(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call('Tests\Support\Database\Seeds\TestUserSeeder');
    }

    public function testIndex()
    {
        $result = $this->userService->index([]);

        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('data', $result);
        $this->assertIsArray($result['data']);
        $this->assertGreaterThanOrEqual(2, count($result['data']));
    }

    public function testShow()
    {
        $result = $this->userService->show(['id' => 1]);

        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('data', $result);
        $this->assertIsArray($result['data']);
        $this->assertEquals('testuser', $result['data']['username']);
    }

    public function testShowNonExistent()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Usuario no encontrado');

        $this->userService->show(['id' => 9999]);
    }

    public function testShowMissingId()
    {
        $result = $this->userService->show([]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('id', $result['errors']);
    }

    public function testStore()
    {
        $data = [
            'username' => 'newserviceuser',
            'email'    => 'newservice@example.com',
        ];

        $result = $this->userService->store($data);

        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('newserviceuser', $result['data']['username']);
    }

    public function testStoreValidationError()
    {
        $data = [
            'username' => 'nu', // Too short
            'email'    => 'invalid-email',
        ];

        $result = $this->userService->store($data);

        $this->assertArrayHasKey('errors', $result);
    }

    public function testUpdate()
    {
        $data = [
            'id'       => 1,
            'username' => 'updateduser',
            'email'    => 'updated@example.com',
        ];

        $result = $this->userService->update($data);

        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('success', $result['status']);
        $this->assertEquals('updateduser', $result['data']['username']);
    }

    public function testUpdateNonExistent()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Usuario no encontrado');

        $this->userService->update([
            'id'    => 9999,
            'email' => 'test@example.com',
        ]);
    }

    public function testUpdateNoFields()
    {
        $result = $this->userService->update(['id' => 1]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('fields', $result['errors']);
    }

    public function testDestroy()
    {
        $result = $this->userService->destroy(['id' => 1]);

        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('message', $result);

        // Verify user is soft deleted
        $this->expectException(\InvalidArgumentException::class);
        $this->userService->show(['id' => 1]);
    }

    public function testDestroyNonExistent()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Usuario no encontrado');

        $this->userService->destroy(['id' => 9999]);
    }

    public function testLoginSuccess()
    {
        $result = $this->userService->login([
            'username' => 'testuser',
            'password' => 'testpass123',
        ]);

        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('testuser', $result['data']['username']);
        $this->assertEquals('user', $result['data']['role']);
    }

    public function testLoginWithEmail()
    {
        $result = $this->userService->login([
            'username' => 'test@example.com',
            'password' => 'testpass123',
        ]);

        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('success', $result['status']);
    }

    public function testLoginInvalidPassword()
    {
        $result = $this->userService->login([
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('credentials', $result['errors']);
    }

    public function testLoginNonExistentUser()
    {
        $result = $this->userService->login([
            'username' => 'nonexistent',
            'password' => 'password123',
        ]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('credentials', $result['errors']);
    }

    public function testLoginMissingCredentials()
    {
        $result = $this->userService->login([
            'username' => 'testuser',
        ]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('credentials', $result['errors']);
    }

    public function testRegisterSuccess()
    {
        $result = $this->userService->register([
            'username' => 'registertest',
            'email'    => 'registertest@example.com',
            'password' => 'password123',
        ]);

        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('registertest', $result['data']['username']);
        $this->assertEquals('user', $result['data']['role']);

        // Verify password is hashed
        $model = new UserModel();
        $user = $model->where('username', 'registertest')->first();
        $this->assertNotEquals('password123', $user->password);
        $this->assertTrue(password_verify('password123', $user->password));
    }

    public function testRegisterWithCustomRole()
    {
        $result = $this->userService->register([
            'username' => 'admintest',
            'email'    => 'admintest@example.com',
            'password' => 'adminpass123',
            'role'     => 'admin',
        ]);

        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('success', $result['status']);
        $this->assertEquals('admin', $result['data']['role']);
    }

    public function testRegisterMissingPassword()
    {
        $result = $this->userService->register([
            'username' => 'nopassword',
            'email'    => 'nopassword@example.com',
        ]);

        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('password', $result['errors']);
    }

    public function testRegisterValidationError()
    {
        $result = $this->userService->register([
            'username' => 'u', // Too short
            'email'    => 'invalid-email',
            'password' => 'pass',
        ]);

        $this->assertArrayHasKey('errors', $result);
    }
}
