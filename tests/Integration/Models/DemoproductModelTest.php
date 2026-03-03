<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\DemoproductModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class DemoproductModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $namespace = 'App';

    private DemoproductModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new DemoproductModel();
    }

    public function testInsertAndFindReturnsEntity(): void
    {
        $id = $this->model->insert(['name' => 'Demo Product']);

        $this->assertIsInt($id);
        $found = $this->model->find($id);

        $this->assertNotNull($found);
        $this->assertSame('Demo Product', (string) $found->name);
    }

    public function testSoftDeleteHidesRecordByDefault(): void
    {
        $id = $this->model->insert(['name' => 'To Delete']);
        $this->assertIsInt($id);

        $this->assertTrue($this->model->delete($id));
        $this->assertNull($this->model->find($id));
        $this->assertNotNull($this->model->withDeleted()->find($id));
    }

    public function testValidationRejectsMissingName(): void
    {
        $this->expectException(\CodeIgniter\Database\Exceptions\DataException::class);
        $this->model->insert([]);
    }
}
