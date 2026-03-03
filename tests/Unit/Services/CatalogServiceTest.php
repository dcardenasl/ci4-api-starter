<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\Request\Catalogs\AuditFacetsRequestDTO;
use App\Models\AuditLogModel;
use App\Services\System\CatalogService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CatalogServiceTest extends CIUnitTestCase
{
    public function testIndexReturnsExpectedCatalogSections(): void
    {
        $model = $this->createMock(AuditLogModel::class);
        $service = new CatalogService($model);

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
        $model = $this->createMock(AuditLogModel::class);
        $model->expects($this->once())
            ->method('getActionFacets')
            ->with(90, 100)
            ->willReturn([
                ['value' => 'login_success', 'count' => 12],
            ]);
        $model->expects($this->once())
            ->method('getEntityTypeFacets')
            ->with(90, 100)
            ->willReturn([
                ['value' => 'users', 'count' => 7],
            ]);

        $service = new CatalogService($model);
        $request = new AuditFacetsRequestDTO([]);

        $data = $service->auditFacets($request)->toArray();

        $this->assertSame(90, $data['window_days']);
        $this->assertSame('login_success', $data['actions'][0]['value']);
        $this->assertSame('users', $data['entity_types'][0]['value']);
    }
}
