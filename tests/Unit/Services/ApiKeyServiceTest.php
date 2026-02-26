<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entities\ApiKeyEntity;
use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\ApiKeyModel;
use App\Services\ApiKeyService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Traits\CustomAssertionsTrait;

/**
 * ApiKeyService Unit Tests
 *
 * Tests API key CRUD operations with mocked model dependencies.
 */
class ApiKeyServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected ApiKeyService $service;
    protected ApiKeyModel $mockApiKeyModel;

    protected function setUp(): void
    {
        parent::setUp();

        // Use anonymous class mock to support chained query builder methods
        $this->mockApiKeyModel = new class () extends ApiKeyModel {
            public ?ApiKeyEntity $returnEntity = null;
            public int|false $insertReturn    = 1;
            public bool $updateReturn         = true;
            public bool $deleteReturn         = true;
            public array $validationErrors    = [];

            public function __construct()
            {
                // Skip parent constructor (no DB)
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

        $this->service = new ApiKeyService($this->mockApiKeyModel);
    }

    // ==================== SHOW TESTS ====================

    public function testShowReturnsApiKeyData(): void
    {
        $entity = $this->makeEntity(['id' => 1, 'name' => 'Test Key', 'key_prefix' => 'apk_abc123de']);
        $this->mockApiKeyModel->returnEntity = $entity;

        $result = $this->service->show(['id' => 1]);

        $this->assertInstanceOf(\App\DTO\Response\ApiKeys\ApiKeyResponseDTO::class, $result);
        $data = $result->toArray();
        $this->assertEquals(1, $data['id']);
        $this->assertEquals('Test Key', $data['name']);
    }

    public function testShowWithoutIdThrowsBadRequestException(): void
    {
        $this->expectException(BadRequestException::class);

        $this->service->show([]);
    }

    public function testShowNonExistentKeyThrowsNotFoundException(): void
    {
        $this->mockApiKeyModel->returnEntity = null;

        $this->expectException(NotFoundException::class);

        $this->service->show(['id' => 999]);
    }

    // ==================== STORE TESTS ====================

    public function testStoreCreatesApiKeyAndReturnsRawKey(): void
    {
        $entity = $this->makeEntity([
            'id'                   => 1,
            'name'                 => 'My App',
            'key_prefix'           => 'apk_',
            'is_active'            => true,
            'rate_limit_requests'  => 600,
            'rate_limit_window'    => 60,
            'user_rate_limit'      => 60,
            'ip_rate_limit'        => 200,
        ]);
        $this->mockApiKeyModel->insertReturn = 1;
        $this->mockApiKeyModel->returnEntity = $entity;

        $result = $this->service->store(new \App\DTO\Request\ApiKeys\ApiKeyCreateRequestDTO(['name' => 'My App']));

        $this->assertInstanceOf(\App\DTO\Response\ApiKeys\ApiKeyResponseDTO::class, $result);
        $data = $result->toArray();

        // Raw key must be present in response
        $this->assertArrayHasKey('key', $data);
        $this->assertStringStartsWith('apk_', $data['key']);
    }

    public function testStoreWithoutNameThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);

        new \App\DTO\Request\ApiKeys\ApiKeyCreateRequestDTO([]);
    }

    public function testStoreUsesProvidedRateLimits(): void
    {
        $capturedData = null;

        $model = new class ($capturedData) extends ApiKeyModel {
            public int $insertReturn    = 1;
            public ?ApiKeyEntity $entity = null;
            private mixed $captured;

            public function __construct(mixed &$ref)
            {
                $this->captured = &$ref;
            }

            public function insert($row = null, bool $returnID = true)
            {
                $this->captured = $row;
                return $this->insertReturn;
            }

            public function find($id = null, bool $purge = false)
            {
                return $this->entity;
            }

            public function errors(bool $forceDB = false): array
            {
                return [];
            }
        };

        $entity = $this->makeEntity(['id' => 1, 'name' => 'Custom Limits', 'key_prefix' => 'apk_']);
        $model->entity = $entity;

        $service = new ApiKeyService($model);
        $service->store(new \App\DTO\Request\ApiKeys\ApiKeyCreateRequestDTO([
            'name'                => 'Custom Limits',
            'rate_limit_requests' => 1200,
            'rate_limit_window'   => 30,
            'user_rate_limit'     => 120,
            'ip_rate_limit'       => 400,
        ]));

        $this->assertNotNull($capturedData);
        $this->assertEquals(1200, $capturedData['rate_limit_requests']);
        $this->assertEquals(30, $capturedData['rate_limit_window']);
        $this->assertEquals(120, $capturedData['user_rate_limit']);
        $this->assertEquals(400, $capturedData['ip_rate_limit']);
    }

    public function testStoreFailsWhenInsertReturnsFalse(): void
    {
        $this->mockApiKeyModel->insertReturn  = false;
        $this->mockApiKeyModel->validationErrors = ['key_hash' => 'Duplicate'];

        $this->expectException(ValidationException::class);

        $this->service->store(new \App\DTO\Request\ApiKeys\ApiKeyCreateRequestDTO(['name' => 'Failing Key']));
    }

    // ==================== UPDATE TESTS ====================

    public function testUpdateModifiesApiKey(): void
    {
        $original = $this->makeEntity(['id' => 1, 'name' => 'Old Name', 'is_active' => true]);
        $updated  = $this->makeEntity(['id' => 1, 'name' => 'New Name', 'is_active' => true]);

        // First call returns original (existence check), second returns updated
        $callCount = 0;
        $model = new class ($original, $updated, $callCount) extends ApiKeyModel {
            private ApiKeyEntity $first;
            private ApiKeyEntity $second;
            private int $count;

            public function __construct(ApiKeyEntity $first, ApiKeyEntity $second, int &$count)
            {
                $this->first  = $first;
                $this->second = $second;
                $this->count  = &$count;
            }

            public function find($id = null, bool $purge = false)
            {
                $this->count++;
                return $this->count === 1 ? $this->first : $this->second;
            }

            public function update($id = null, $row = null): bool
            {
                return true;
            }

            public function errors(bool $forceDB = false): array
            {
                return [];
            }
        };

        $service = new ApiKeyService($model);
        $result  = $service->update(['id' => 1, 'name' => 'New Name']);

        $this->assertInstanceOf(\App\DTO\Response\ApiKeys\ApiKeyResponseDTO::class, $result);
        $data = $result->toArray();
        $this->assertEquals('New Name', $data['name']);
    }

    public function testUpdateWithoutIdThrowsBadRequestException(): void
    {
        $this->expectException(BadRequestException::class);

        $this->service->update(['name' => 'Something']);
    }

    public function testUpdateNonExistentKeyThrowsNotFoundException(): void
    {
        $this->mockApiKeyModel->returnEntity = null;

        $this->expectException(NotFoundException::class);

        $this->service->update(['id' => 99, 'name' => 'X']);
    }

    public function testUpdateWithNoFieldsThrowsBadRequestException(): void
    {
        $this->mockApiKeyModel->returnEntity = $this->makeEntity(['id' => 1, 'name' => 'X']);

        $this->expectException(BadRequestException::class);

        $this->service->update(['id' => 1]);
    }

    // ==================== DESTROY TESTS ====================

    public function testDestroyDeletesApiKey(): void
    {
        $this->mockApiKeyModel->returnEntity = $this->makeEntity(['id' => 1, 'name' => 'To Delete']);

        $result = $this->service->destroy(['id' => 1]);

        $this->assertSuccessResponse($result);
    }

    public function testDestroyWithoutIdThrowsBadRequestException(): void
    {
        $this->expectException(BadRequestException::class);

        $this->service->destroy([]);
    }

    public function testDestroyNonExistentKeyThrowsNotFoundException(): void
    {
        $this->mockApiKeyModel->returnEntity = null;

        $this->expectException(NotFoundException::class);

        $this->service->destroy(['id' => 99]);
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
