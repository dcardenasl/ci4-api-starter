<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Catalog;

use App\Models\DemoproductModel;
use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

class DemoproductControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    private DemoproductModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new DemoproductModel();
    }

    public function testEndpointsRequireAuthentication(): void
    {
        $result = $this->get('/api/v1/demo-products');
        $result->assertStatus(401);
    }

    public function testEndpointsRequireAdminRole(): void
    {
        $this->actAs('user');

        $list = $this->get('/api/v1/demo-products');
        $list->assertStatus(403);
    }

    public function testAdminCrudFlowWorks(): void
    {
        $this->actAs('admin');

        $create = $this->withBodyFormat('json')->post('/api/v1/demo-products', [
            'name' => 'Product A',
        ]);
        $create->assertStatus(201);
        $createJson = $this->getResponseJson($create);
        $this->assertSame('success', $createJson['status'] ?? null);
        $this->assertSame('Product A', $createJson['data']['name'] ?? null);
        $id = (int) ($createJson['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $id);

        $list = $this->get('/api/v1/demo-products');
        $list->assertStatus(200);

        $show = $this->get("/api/v1/demo-products/{$id}");
        $show->assertStatus(200);
        $showJson = $this->getResponseJson($show);
        $this->assertSame($id, (int) ($showJson['data']['id'] ?? 0));

        $update = $this->withBodyFormat('json')->put("/api/v1/demo-products/{$id}", [
            'name' => 'Product B',
        ]);
        $update->assertStatus(200);
        $updateJson = $this->getResponseJson($update);
        $this->assertSame('Product B', $updateJson['data']['name'] ?? null);

        $delete = $this->delete("/api/v1/demo-products/{$id}");
        $delete->assertStatus(200);

        $this->assertNull($this->model->find($id));
        $this->assertNotNull($this->model->withDeleted()->find($id));
    }

    public function testCreateWithoutNameReturns422(): void
    {
        $this->actAs('admin');

        $result = $this->withBodyFormat('json')->post('/api/v1/demo-products', []);
        $result->assertStatus(422);
    }
}
