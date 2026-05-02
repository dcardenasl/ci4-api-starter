<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use App\Models\AppUserMembershipModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Smoke tests for AppUserMembershipModel. Extend with persistence scenarios as
 * domain behavior solidifies.
 *
 * @internal
 */
final class AppUserMembershipModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    public function testModelReportsCorrectTable(): void
    {
        $model = new AppUserMembershipModel();

        $this->assertSame('app_user_memberships', $model->getTable());
    }
}
