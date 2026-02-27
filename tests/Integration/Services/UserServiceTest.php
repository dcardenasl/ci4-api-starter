<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\DTO\Request\Users\UserStoreRequestDTO;
use App\DTO\Request\Users\UserUpdateRequestDTO;
use App\Models\UserModel;
use App\Services\UserService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * UserService Integration Tests
 */
class UserServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use CustomAssertionsTrait;

    protected $migrate     = true;
    protected $namespace   = 'App';

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

    public function testStoreCreatesUserInDatabase(): void
    {
        $request = new UserStoreRequestDTO([
            'email' => 'integration@example.com',
            'first_name' => 'Integration',
            'last_name' => 'User',
            'user_role' => 'admin',
        ]);

        $result = $this->userService->store($request);
        $data = $result->toArray();

        $user = $this->userModel->find($data['id']);
        $this->assertEquals('integration@example.com', $user->email);
    }

    public function testShowReturnsExistingUser(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'show@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->userService->show((int) $userId);
        $this->assertEquals('show@example.com', $result->toArray()['email']);
    }

    public function testUpdateModifiesUserInDatabase(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'old@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $request = new UserUpdateRequestDTO([
            'email' => 'new@example.com',
            'first_name' => 'New',
            'user_role' => 'superadmin',
        ]);

        $result = $this->userService->update((int) $userId, $request);

        $user = $this->userModel->find($userId);
        $this->assertEquals('new@example.com', $user->email);
    }

    public function testDestroySoftDeletesUser(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'delete@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
        ]);

        $result = $this->userService->destroy((int) $userId);
        $this->assertTrue($result);

        $this->assertNull($this->userModel->find($userId));
        $this->assertNotNull($this->userModel->withDeleted()->find($userId));
    }
}
