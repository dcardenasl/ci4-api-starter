<?php

namespace Tests\Filters;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

class CorsFilterTest extends CIUnitTestCase
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

    public function testCorsHeadersAreSetForAllowedOrigin()
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
        ])->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
                'password' => 'testpass123',
            ]);

        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
        $this->assertEquals('http://localhost:3000', $response->header('Access-Control-Allow-Origin')->getValue());
    }

    public function testCorsAllowMethodsHeaderIsSet()
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
        ])->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
                'password' => 'testpass123',
            ]);

        $this->assertTrue($response->hasHeader('Access-Control-Allow-Methods'));
        $methodsHeader = $response->header('Access-Control-Allow-Methods')->getValue();
        $this->assertStringContainsString('GET', $methodsHeader);
        $this->assertStringContainsString('POST', $methodsHeader);
    }

    public function testCorsAllowHeadersIsSet()
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
        ])->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
                'password' => 'testpass123',
            ]);

        $this->assertTrue($response->hasHeader('Access-Control-Allow-Headers'));
        $headersValue = $response->header('Access-Control-Allow-Headers')->getValue();
        $this->assertStringContainsString('Authorization', $headersValue);
        $this->assertStringContainsString('Content-Type', $headersValue);
    }

    public function testCorsMaxAgeIsSet()
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
        ])->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
                'password' => 'testpass123',
            ]);

        $this->assertTrue($response->hasHeader('Access-Control-Max-Age'));
        $this->assertEquals('86400', $response->header('Access-Control-Max-Age')->getValue());
    }

    public function testUnauthorizedOriginNotAllowed()
    {
        $response = $this->withHeaders([
            'Origin' => 'http://malicious-site.com',
        ])->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
                'password' => 'testpass123',
            ]);

        // Should not have Access-Control-Allow-Origin header for unauthorized origin
        if ($response->hasHeader('Access-Control-Allow-Origin')) {
            $allowedOrigin = $response->header('Access-Control-Allow-Origin')->getValue();
            $this->assertNotEquals('http://malicious-site.com', $allowedOrigin);
        }
    }

    public function testRequestWithoutOriginWorksNormally()
    {
        $response = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
                'password' => 'testpass123',
            ]);

        $response->assertStatus(200);
    }

    public function testAllowedOriginCanAccessProtectedRoute()
    {
        // First login to get token
        $loginResponse = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
                'password' => 'testpass123',
            ]);

        $json = json_decode($loginResponse->getJSON());
        $token = $json->data->token;

        // Access protected route with CORS
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Origin' => 'http://localhost:3000',
        ])->get('/api/v1/auth/me');

        $response->assertStatus(200);
        $this->assertTrue($response->hasHeader('Access-Control-Allow-Origin'));
        $this->assertEquals('http://localhost:3000', $response->header('Access-Control-Allow-Origin')->getValue());
    }

    public function testCorsCredentialsHeaderIsSet()
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
        ])->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
                'password' => 'testpass123',
            ]);

        $this->assertTrue($response->hasHeader('Access-Control-Allow-Credentials'));
        $this->assertEquals('true', $response->header('Access-Control-Allow-Credentials')->getValue());
    }
}
