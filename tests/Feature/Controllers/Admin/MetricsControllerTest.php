<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\AuthTestTrait;

class MetricsControllerTest extends CIUnitTestCase
{
    use AuthTestTrait;
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    public function testMetricsRequiresAdmin(): void
    {
        $email = 'metrics-user@example.com';
        $password = 'ValidPass123!';
        $this->createUser($email, $password, 'user');

        $token = $this->loginAndGetToken($email, $password);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->get('/api/v1/metrics');

        $result->assertStatus(403);
    }

    public function testMetricsEndpointsReturnSuccessForAdmin(): void
    {
        $email = 'metrics-admin@example.com';
        $password = 'ValidPass123!';
        $this->createUser($email, $password, 'admin');

        $token = $this->loginAndGetToken($email, $password);

        $endpoints = [
            '/api/v1/metrics',
            '/api/v1/metrics/requests',
            '/api/v1/metrics/slow-requests',
            '/api/v1/metrics/custom/example',
        ];

        foreach ($endpoints as $endpoint) {
            $result = $this->withHeaders([
                'Authorization' => "Bearer {$token}",
            ])->get($endpoint);

            $result->assertStatus(200);
        }
    }

    public function testRecordMetricValidationAndSuccess(): void
    {
        $email = 'metrics-record@example.com';
        $password = 'ValidPass123!';
        $this->createUser($email, $password, 'admin');

        $token = $this->loginAndGetToken($email, $password);

        $invalid = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->post('/api/v1/metrics/record', [
            'value' => 1.5,
        ]);

        $invalid->assertStatus(422);

        $valid = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->post('/api/v1/metrics/record', [
            'name' => 'example',
            'value' => 1.5,
            'tags' => ['env' => 'test'],
        ]);

        $valid->assertStatus(201);
    }
}
