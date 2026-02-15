<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class RateLimitResponseTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    /**
     * @var array<string, string|false>
     */
    private array $previousEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousEnv = [
            'AUTH_RATE_LIMIT_REQUESTS' => getenv('AUTH_RATE_LIMIT_REQUESTS'),
            'AUTH_RATE_LIMIT_WINDOW' => getenv('AUTH_RATE_LIMIT_WINDOW'),
            'RATE_LIMIT_REQUESTS' => getenv('RATE_LIMIT_REQUESTS'),
            'RATE_LIMIT_WINDOW' => getenv('RATE_LIMIT_WINDOW'),
        ];

        service('cache')->clean();
    }

    protected function tearDown(): void
    {
        foreach ($this->previousEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
                continue;
            }

            putenv("{$key}={$value}");
        }

        service('cache')->clean();
        parent::tearDown();
    }

    public function testAuthThrottleExceededReturnsCanonicalErrorResponse(): void
    {
        putenv('AUTH_RATE_LIMIT_REQUESTS=1');
        putenv('AUTH_RATE_LIMIT_WINDOW=60');

        $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => 'ratelimit@example.com',
                'password' => 'WrongPass123!',
            ]);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => 'ratelimit@example.com',
                'password' => 'WrongPass123!',
            ]);

        $result->assertStatus(429);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
        $this->assertArrayHasKey('message', $json);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('rate_limit', $json['errors']);
        $this->assertEquals(429, $json['code']);
        $this->assertArrayHasKey('retry_after', $json);
        $this->assertSame('60', $result->response()->getHeaderLine('Retry-After'));
        $this->assertSame('0', $result->response()->getHeaderLine('X-RateLimit-Remaining'));
    }

    public function testGeneralThrottleExceededReturnsCanonicalErrorResponse(): void
    {
        putenv('RATE_LIMIT_REQUESTS=1');
        putenv('RATE_LIMIT_WINDOW=60');

        $this->get('/api/v1/auth/validate-reset-token?token=fake-token&email=test@example.com');

        $result = $this->get('/api/v1/auth/validate-reset-token?token=fake-token&email=test@example.com');

        $result->assertStatus(429);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('error', $json['status']);
        $this->assertArrayHasKey('message', $json);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('rate_limit', $json['errors']);
        $this->assertEquals(429, $json['code']);
        $this->assertArrayHasKey('retry_after', $json);
        $this->assertSame('60', $result->response()->getHeaderLine('Retry-After'));
        $this->assertSame('0', $result->response()->getHeaderLine('X-RateLimit-Remaining'));
    }
}
