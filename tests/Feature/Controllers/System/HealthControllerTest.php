<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use Tests\Support\ApiTestCase;

class HealthControllerTest extends ApiTestCase
{
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
