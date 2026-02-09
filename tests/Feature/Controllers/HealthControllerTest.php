<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class HealthControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    public function testReadyEndpointResponds(): void
    {
        $result = $this->get('/ready');

        $this->assertTrue(in_array($result->response()->getStatusCode(), [200, 503], true));
    }

    public function testLiveEndpointReturnsAlive(): void
    {
        $result = $this->get('/live');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('alive', $json['status']);
    }
}
