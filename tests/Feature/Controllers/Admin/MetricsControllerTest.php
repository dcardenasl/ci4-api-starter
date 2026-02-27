<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

class MetricsControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actAs('admin');

        // Ensure static context is set for background model operations (Auditable trait)
        \App\Libraries\ContextHolder::set(new \App\DTO\SecurityContext($this->currentUserId, $this->currentUserRole));
    }

    public function testMetricsRequiresAdmin(): void
    {
        $this->actAs('user');

        $result = $this->get('/api/v1/metrics');

        $result->assertStatus(403);
    }

    public function testMetricsEndpointsReturnSuccessForAdmin(): void
    {
        $endpoints = [
            '/api/v1/metrics',
            '/api/v1/metrics/requests',
            '/api/v1/metrics/slow-requests',
            '/api/v1/metrics/custom/example',
        ];

        foreach ($endpoints as $endpoint) {
            $result = $this->get($endpoint);

            $result->assertStatus(200);
        }
    }

    public function testRecordMetricValidationAndSuccess(): void
    {
        $invalid = $this->withBodyFormat('json')->post('/api/v1/metrics/record', [
            'value' => 1.5,
        ]);

        $invalid->assertStatus(422);

        $valid = $this->withBodyFormat('json')->post('/api/v1/metrics/record', [
            'name' => 'example',
            'value' => 1.5,
            'tags' => ['env' => 'test'],
        ]);

        $valid->assertStatus(201);
    }
}
