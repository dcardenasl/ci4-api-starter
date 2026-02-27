<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entities\UserEntity;
use App\Exceptions\NotFoundException;
use App\Interfaces\System\AuditServiceInterface;
use App\Interfaces\System\EmailServiceInterface;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
use App\Services\Users\UserService;
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
    protected EmailServiceInterface $mockEmailService;
    protected PasswordResetModel $passwordResetModelStub;
    protected AuditServiceInterface $mockAuditService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockUserModel = $this->createMock(UserModel::class);
        $this->mockEmailService = $this->createMock(EmailServiceInterface::class);
        $this->mockAuditService = $this->createMock(AuditServiceInterface::class);
        $this->passwordResetModelStub = new class () extends PasswordResetModel {
            public function __construct()
            {
            }
            public function where($key = null, $value = null, ?bool $escape = null): static
            {
                return $this;
            }
            public function delete($id = null, bool $purge = false)
            {
                return true;
            }
            public function insert($row = null, bool $returnID = true)
            {
                return 1;
            }
        };

        $this->mockEmailService->method('queueTemplate')->willReturn(1);

        $this->service = new UserService(
            $this->mockUserModel,
            $this->mockEmailService,
            $this->mockAuditService,
            new \App\Libraries\Security\UserRoleGuard(),
            new \App\Services\Auth\UserInvitationService(
                $this->passwordResetModelStub,
                $this->mockEmailService
            )
        );
    }

    private function createUserEntity(array $data): UserEntity
    {
        return new UserEntity($data);
    }

    // ==================== SHOW TESTS ====================

    public function testShowReturnsUserData(): void
    {
        $user = $this->createUserEntity([
            'id' => 1,
            'email' => 'test@example.com',
            'role' => 'user',
        ]);

        $this->mockUserModel->expects($this->once())->method('find')->with(1)->willReturn($user);

        $result = $this->service->show(1);

        $this->assertInstanceOf(\App\Interfaces\DataTransferObjectInterface::class, $result);
        $this->assertEquals('test@example.com', $result->toArray()['email']);
    }

    public function testShowWithNonExistentUserThrowsNotFoundException(): void
    {
        $this->mockUserModel->method('find')->willReturn(null);
        $this->expectException(NotFoundException::class);
        $this->service->show(999);
    }

    // ==================== STORE TESTS ====================

    public function testStoreCreatesUser(): void
    {
        $request = new \App\DTO\Request\Users\UserStoreRequestDTO([
            'email' => 'new@example.com',
            'role' => 'user',
        ]);

        $this->mockUserModel->expects($this->once())->method('insert')->willReturn(1);
        $this->mockUserModel->method('find')->willReturn($this->createUserEntity(['id' => 1, 'email' => 'new@example.com']));

        $result = $this->service->store($request);
        $this->assertInstanceOf(\App\Interfaces\DataTransferObjectInterface::class, $result);
    }

    // ==================== UPDATE TESTS ====================

    public function testUpdateModifiesUser(): void
    {
        $id = 1;
        $user = $this->createUserEntity(['id' => $id, 'role' => 'user']);
        $this->mockUserModel->method('find')->willReturn($user);
        $this->mockUserModel->expects($this->once())->method('update')->willReturn(true);

        $request = new class (['first_name' => 'Updated']) implements \App\Interfaces\DataTransferObjectInterface {
            public function __construct(private array $data)
            {
            }
            public function toArray(): array
            {
                return $this->data;
            }
        };

        $result = $this->service->update($id, $request);
        $this->assertInstanceOf(\App\Interfaces\DataTransferObjectInterface::class, $result);
    }

    // ==================== DESTROY TESTS ====================

    public function testDestroyDeletesUser(): void
    {
        $id = 1;
        $this->mockUserModel->method('find')->willReturn($this->createUserEntity(['id' => $id, 'role' => 'user']));
        $this->mockUserModel->expects($this->once())->method('delete')->with($id)->willReturn(true);

        $result = $this->service->destroy($id);
        $this->assertTrue($result);
    }
}
