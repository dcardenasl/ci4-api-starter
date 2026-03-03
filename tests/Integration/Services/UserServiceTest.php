<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\DTO\Request\Users\UserCreateRequestDTO;
use App\DTO\Request\Users\UserUpdateRequestDTO;
use App\DTO\SecurityContext;
use App\Exceptions\ConflictException;
use App\Models\UserModel;
use App\Services\Users\UserService;
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
        $this->userService = \Config\Services::userService(false);
    }

    public function testStoreCreatesUserInDatabase(): void
    {
        $request = new UserCreateRequestDTO([
            'email' => 'integration@example.com',
            'first_name' => 'Integration',
            'last_name' => 'User',
            'role' => 'user',
        ]);

        $result = $this->userService->store($request, new SecurityContext(1, 'admin'));
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
        ]);

        $result = $this->userService->update((int) $userId, $request, new SecurityContext(1, 'admin'));

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

        $result = $this->userService->destroy((int) $userId, new SecurityContext(1, 'admin'));
        $this->assertTrue($result);

        $this->assertNull($this->userModel->find($userId));
        $this->assertNotNull($this->userModel->withDeleted()->find($userId));
    }

    public function testApproveActivatesPendingApprovalUser(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'pending-approve@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'pending_approval',
        ]);

        $result = $this->userService->approve((int) $userId, new SecurityContext(1, 'admin'));

        $user = $this->userModel->find($userId);
        $this->assertEquals('active', $user->status);
        $this->assertNotNull($user->approved_at);
        $this->assertEquals('active', $result->toArray()['status'] ?? null);
    }

    public function testApproveThrowsConflictForAlreadyActiveUser(): void
    {
        $userId = $this->userModel->insert([
            'email' => 'already-active@example.com',
            'password' => password_hash('ValidPass123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'status' => 'active',
        ]);

        $this->expectException(ConflictException::class);
        $this->userService->approve((int) $userId, new SecurityContext(1, 'admin'));
    }
}
