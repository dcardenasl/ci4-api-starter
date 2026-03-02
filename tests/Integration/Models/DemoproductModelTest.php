<?php

declare(strict_types=1);

namespace Tests\Integration\Models;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class DemoproductModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $namespace = 'App';

    public function testPlaceholder(): void
    {
        $this->markTestIncomplete('Implement DemoproductModel integration tests.');
    }
}
