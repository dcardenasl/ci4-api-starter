<?php

namespace Tests\Filters;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

class ThrottleFilterTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDatabase();

        // Clear cache before each test
        Services::cache()->clean();
    }

    protected function seedDatabase(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call('Tests\Support\Database\Seeds\TestUserSeeder');
    }

    public function testRateLimitHeadersAreSet()
    {
        $response = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
                'password' => 'testpass123',
            ]);

        $this->assertTrue($response->hasHeader('X-RateLimit-Limit'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Remaining'));
        $this->assertTrue($response->hasHeader('X-RateLimit-Reset'));
    }

    public function testRateLimitRemainingDecrements()
    {
        // First request
        $response1 = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
                'password' => 'testpass123',
            ]);

        $remaining1 = (int) $response1->header('X-RateLimit-Remaining')->getValue();

        // Second request
        $response2 = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
                'password' => 'testpass123',
            ]);

        $remaining2 = (int) $response2->header('X-RateLimit-Remaining')->getValue();

        // Remaining should decrease
        $this->assertLessThan($remaining1, $remaining2);
    }

    public function testRateLimitExceededReturns429()
    {
        $maxRequests = (int) env('RATE_LIMIT_REQUESTS', 60);

        // Make requests up to the limit
        for ($i = 0; $i < $maxRequests; $i++) {
            $response = $this->withBodyFormat('json')
                ->post('/api/v1/auth/login', [
                    'username' => 'testuser',
                    'password' => 'wrongpassword',
                ]);

            // Should not be rate limited yet
            $this->assertNotEquals(429, $response->getStatusCode(), "Request $i should not be rate limited");
        }

        // Next request should be rate limited
        $response = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
                'password' => 'wrongpassword',
            ]);

        $response->assertStatus(429);
    }

    public function testRateLimitExceededResponseFormat()
    {
        $maxRequests = (int) env('RATE_LIMIT_REQUESTS', 60);

        // Exhaust rate limit
        for ($i = 0; $i <= $maxRequests; $i++) {
            $this->withBodyFormat('json')
                ->post('/api/v1/auth/login', [
                    'username' => 'testuser',
                    'password' => 'wrongpassword',
                ]);
        }

        // Get rate limited response
        $response = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
                'password' => 'wrongpassword',
            ]);

        $response->assertStatus(429);
        $response->assertJSONFragment(['success' => false]);

        $json = json_decode($response->getJSON());
        $this->assertObjectHasProperty('message', $json);
        $this->assertObjectHasProperty('retry_after', $json);
        $this->assertStringContainsString('Rate limit exceeded', $json->message);
    }

    public function testRateLimitRetryAfterHeader()
    {
        $maxRequests = (int) env('RATE_LIMIT_REQUESTS', 60);

        // Exhaust rate limit
        for ($i = 0; $i <= $maxRequests; $i++) {
            $this->withBodyFormat('json')
                ->post('/api/v1/auth/login', [
                    'username' => 'testuser',
                    'password' => 'wrongpassword',
                ]);
        }

        // Get rate limited response
        $response = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
                'password' => 'wrongpassword',
            ]);

        $this->assertTrue($response->hasHeader('Retry-After'));
        $retryAfter = (int) $response->header('Retry-After')->getValue();
        $this->assertGreaterThan(0, $retryAfter);
    }

    public function testRateLimitRemainingZeroWhenExceeded()
    {
        $maxRequests = (int) env('RATE_LIMIT_REQUESTS', 60);

        // Exhaust rate limit
        for ($i = 0; $i <= $maxRequests; $i++) {
            $this->withBodyFormat('json')
                ->post('/api/v1/auth/login', [
                    'username' => 'testuser',
                    'password' => 'wrongpassword',
                ]);
        }

        // Get rate limited response
        $response = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
                'password' => 'wrongpassword',
            ]);

        $this->assertEquals('0', $response->header('X-RateLimit-Remaining')->getValue());
    }
}
