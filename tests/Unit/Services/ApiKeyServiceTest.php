<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\Request\ApiKeys\ApiKeyCreateRequestDTO;
use App\DTO\Request\ApiKeys\ApiKeyUpdateRequestDTO;
use App\DTO\Response\ApiKeys\ApiKeyResponseDTO;
use App\Entities\ApiKeyEntity;
use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Interfaces\DataTransferObjectInterface;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Models\ApiKeyModel;
use App\Services\Tokens\Actions\CreateApiKeyAction;
use App\Services\Tokens\Actions\UpdateApiKeyAction;
use App\Services\Tokens\ApiKeyService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * ApiKeyService Unit Tests
 */
class ApiKeyServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected ApiKeyService $service;
    protected ApiKeyModel $mockApiKeyModel;
    protected CreateApiKeyAction $mockCreateApiKeyAction;
    protected UpdateApiKeyAction $mockUpdateApiKeyAction;
    protected ResponseMapperInterface $responseMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockApiKeyModel = new class () extends ApiKeyModel {
            public ?ApiKeyEntity $returnEntity = null;
            public int|false $insertReturn    = 1;
            public bool $updateReturn         = true;
            public bool $deleteReturn         = true;
            public array $validationErrors    = [];

            public function __construct()
            {
            }
            public function find($id = null, bool $purge = false)
            {
                return $this->returnEntity;
            }
            public function insert($row = null, bool $returnID = true)
            {
                return $this->insertReturn;
            }
            public function update($id = null, $row = null): bool
            {
                return $this->updateReturn;
            }
            public function delete($id = null, bool $purge = false)
            {
                return $this->deleteReturn;
            }
            public function errors(bool $forceDB = false): array
            {
                return $this->validationErrors;
            }
            public function where($key, $value = null, ?bool $escape = null): static
            {
                return $this;
            }
            public function first()
            {
                return $this->returnEntity;
            }
        };
        $this->mockCreateApiKeyAction = $this->createMock(CreateApiKeyAction::class);
        $this->mockUpdateApiKeyAction = $this->createMock(UpdateApiKeyAction::class);
        $this->responseMapper = new class () implements ResponseMapperInterface {
            public function map(object $entity): DataTransferObjectInterface
            {
                return ApiKeyResponseDTO::fromArray($entity->toArray());
            }
        };

        $this->service = new ApiKeyService(
            $this->mockApiKeyModel,
            $this->responseMapper,
            $this->mockCreateApiKeyAction,
            $this->mockUpdateApiKeyAction
        );
    }

    // ==================== SHOW TESTS ====================

    public function testShowReturnsApiKeyData(): void
    {
        $entity = $this->makeEntity(['id' => 1, 'name' => 'Test Key', 'key_prefix' => 'apk_abc123de']);
        $this->mockApiKeyModel->returnEntity = $entity;

        $result = $this->service->show(1);

        $this->assertInstanceOf(DataTransferObjectInterface::class, $result);
        $this->assertEquals(1, $result->toArray()['id']);
        $this->assertEquals('Test Key', $result->toArray()['name']);
    }

    public function testShowNonExistentKeyThrowsNotFoundException(): void
    {
        $this->mockApiKeyModel->returnEntity = null;
        $this->expectException(NotFoundException::class);
        $this->service->show(999);
    }

    // ==================== STORE TESTS ====================

    public function testStoreCreatesApiKeyAndReturnsRawKey(): void
    {
        $entity = $this->makeEntity([
            'id'                   => 1,
            'name'                 => 'My App',
            'key_prefix'           => 'apk_',
            'is_active'            => 1,
            'rate_limit_requests'  => 600,
        ]);
        $request = new ApiKeyCreateRequestDTO(['name' => 'My App'], service('validation'));
        $this->mockCreateApiKeyAction->expects($this->once())
            ->method('execute')
            ->with($this->isInstanceOf(ApiKeyCreateRequestDTO::class))
            ->willReturn(['entity' => $entity, 'key' => 'apk_test_key']);

        $result = $this->service->store($request);

        $this->assertInstanceOf(DataTransferObjectInterface::class, $result);
        $data = $result->toArray();
        $this->assertArrayHasKey('key', $data);
        $this->assertStringStartsWith('apk_', $data['key']);
    }

    // ==================== UPDATE TESTS ====================

    public function testUpdateModifiesApiKey(): void
    {
        $entity = $this->makeEntity(['id' => 1, 'name' => 'New Name', 'is_active' => 1]);
        $this->mockUpdateApiKeyAction->expects($this->once())
            ->method('execute')
            ->with(1, $this->isInstanceOf(ApiKeyUpdateRequestDTO::class))
            ->willReturn($entity);

        $request = new ApiKeyUpdateRequestDTO(['name' => 'New Name'], service('validation'));
        $result  = $this->service->update(1, $request);

        $this->assertInstanceOf(DataTransferObjectInterface::class, $result);
        $this->assertEquals('New Name', $result->toArray()['name']);
    }

    public function testUpdateNonExistentKeyThrowsNotFoundException(): void
    {
        $this->mockUpdateApiKeyAction->method('execute')->willThrowException(new NotFoundException(lang('Api.resourceNotFound')));
        $this->expectException(NotFoundException::class);
        $this->service->update(99, new ApiKeyUpdateRequestDTO(['name' => 'X'], service('validation')));
    }

    public function testUpdateWithNoFieldsThrowsBadRequestException(): void
    {
        $this->mockUpdateApiKeyAction->method('execute')->willThrowException(new BadRequestException(lang('Api.noFieldsToUpdate')));
        $this->expectException(BadRequestException::class);
        $this->service->update(1, new ApiKeyUpdateRequestDTO([], service('validation')));
    }

    // ==================== DESTROY TESTS ====================

    public function testDestroyDeletesApiKey(): void
    {
        $this->mockApiKeyModel->returnEntity = $this->makeEntity(['id' => 1, 'name' => 'To Delete']);
        $result = $this->service->destroy(1);
        $this->assertTrue($result);
    }

    public function testDestroyNonExistentKeyThrowsNotFoundException(): void
    {
        $this->mockApiKeyModel->returnEntity = null;
        $this->expectException(NotFoundException::class);
        $this->service->destroy(99);
    }

    // ==================== HELPER ====================

    private function makeEntity(array $data): ApiKeyEntity
    {
        $entity = new ApiKeyEntity();
        foreach ($data as $key => $value) {
            $entity->$key = $value;
        }
        return $entity;
    }
}
