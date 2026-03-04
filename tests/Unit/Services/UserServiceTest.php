<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\Response\Users\UserResponseDTO;
use App\Entities\UserEntity;
use App\Exceptions\NotFoundException;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Services\Users\Actions\ApproveUserAction;
use App\Services\Users\Actions\CreateUserAction;
use App\Services\Users\Actions\UpdateUserAction;
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
    protected UserRepositoryInterface $mockUserRepository;
    protected ApproveUserAction $mockApproveUserAction;
    protected CreateUserAction $mockCreateUserAction;
    protected UpdateUserAction $mockUpdateUserAction;
    protected ResponseMapperInterface $responseMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockUserRepository = $this->createMock(UserRepositoryInterface::class);
        $this->mockApproveUserAction = $this->createMock(ApproveUserAction::class);
        $this->mockCreateUserAction = $this->createMock(CreateUserAction::class);
        $this->mockUpdateUserAction = $this->createMock(UpdateUserAction::class);
        $this->responseMapper = new class () implements ResponseMapperInterface {
            public function map(object $entity): \App\Interfaces\DataTransferObjectInterface
            {
                return UserResponseDTO::fromArray($entity->toArray());
            }
        };

        $this->service = new UserService(
            $this->mockUserRepository,
            $this->responseMapper,
            new \App\Libraries\Security\UserRoleGuard(),
            $this->mockApproveUserAction,
            $this->mockCreateUserAction,
            $this->mockUpdateUserAction
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

        $this->mockUserRepository->expects($this->once())->method('find')->with(1)->willReturn($user);

        $result = $this->service->show(1);

        $this->assertInstanceOf(\App\Interfaces\DataTransferObjectInterface::class, $result);
        $this->assertEquals('test@example.com', $result->toArray()['email']);
    }

    public function testShowWithNonExistentUserThrowsNotFoundException(): void
    {
        $this->mockUserRepository->method('find')->willReturn(null);
        $this->expectException(NotFoundException::class);
        $this->service->show(999);
    }

    // ==================== STORE TESTS ====================

    public function testStoreCreatesUser(): void
    {
        $request = new \App\DTO\Request\Users\UserCreateRequestDTO([
            'email' => 'new@example.com',
            'role' => 'user',
        ], service('validation'));

        $expectedUser = $this->createUserEntity(['id' => 1, 'email' => 'new@example.com']);
        $this->mockCreateUserAction->expects($this->once())->method('execute')->willReturn($expectedUser);

        $result = $this->service->store($request);
        $this->assertInstanceOf(\App\Interfaces\DataTransferObjectInterface::class, $result);
    }

    // ==================== UPDATE TESTS ====================

    public function testUpdateModifiesUser(): void
    {
        $id = 1;
        $user = $this->createUserEntity(['id' => $id, 'role' => 'user']);
        $request = new \App\DTO\Request\Users\UserUpdateRequestDTO([
            'first_name' => 'Updated',
        ], service('validation'));
        $this->mockUpdateUserAction->expects($this->once())->method('execute')->with($id, $request)->willReturn($user);

        $result = $this->service->update($id, $request);
        $this->assertInstanceOf(\App\Interfaces\DataTransferObjectInterface::class, $result);
    }

    public function testApproveDelegatesToApproveUserAction(): void
    {
        $id = 1;
        $context = new \App\DTO\SecurityContext(10, 'admin');
        $approvedUser = $this->createUserEntity([
            'id' => $id,
            'email' => 'approved@example.com',
            'status' => 'active',
        ]);

        $this->mockApproveUserAction
            ->expects($this->once())
            ->method('execute')
            ->with($id, $context, 'https://client.test')
            ->willReturn($approvedUser);

        $result = $this->service->approve($id, $context, 'https://client.test');
        $this->assertInstanceOf(\App\Interfaces\DataTransferObjectInterface::class, $result);
        $this->assertEquals('approved@example.com', $result->toArray()['email']);
    }

    // ==================== DESTROY TESTS ====================

    public function testDestroyDeletesUser(): void
    {
        $id = 1;
        $this->mockUserRepository->method('find')->willReturn($this->createUserEntity(['id' => $id, 'role' => 'user']));
        $this->mockUserRepository->expects($this->once())->method('delete')->with($id)->willReturn(true);

        $result = $this->service->destroy($id);
        $this->assertTrue($result);
    }
}
