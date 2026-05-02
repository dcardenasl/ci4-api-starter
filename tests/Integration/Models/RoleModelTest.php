<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\RoleModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Smoke tests for RoleModel. Extend with persistence scenarios as
 * domain behavior solidifies.
 *
 * @internal
 */
final class RoleModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    public function testModelReportsCorrectTable(): void
    {
        $model = new RoleModel();

        $this->assertSame('roles', $model->getTable());
    }
}
