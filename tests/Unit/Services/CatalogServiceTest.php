<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\Request\Catalog\AuditFacetsRequestDTO;
use App\Interfaces\System\AuditRepositoryInterface;
use App\Services\System\CatalogService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CatalogServiceTest extends CIUnitTestCase
{
    public function testIndexReturnsExpectedCatalogSections(): void
    {
        $repository = $this->createMock(AuditRepositoryInterface::class);
        $service = new CatalogService($repository);

        $data = $service->index()->toArray();

        $this->assertArrayHasKey('users', $data);
        $this->assertArrayHasKey('api_keys', $data);
        $this->assertArrayHasKey('files', $data);
        $this->assertArrayHasKey('metrics', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertSame([10, 25, 50, 100], $data['pagination']['limit_options']);
    }

    public function testAuditFacetsUsesModelDistinctData(): void
    {
        $repository = $this->createMock(AuditRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('getActionFacets')
            ->with(90, 100)
            ->willReturn([
                ['value' => 'login_success', 'count' => 12],
            ]);
        $repository->expects($this->once())
            ->method('getEntityTypeFacets')
            ->with(90, 100)
            ->willReturn([
                ['value' => 'users', 'count' => 7],
            ]);

        $service = new CatalogService($repository);
        $request = new AuditFacetsRequestDTO([], service('validation'));

        $data = $service->auditFacets($request)->toArray();

        $this->assertSame(90, $data['window_days']);
        $this->assertSame('login_success', $data['actions'][0]['value']);
        $this->assertSame('users', $data['entity_types'][0]['value']);
    }
}
