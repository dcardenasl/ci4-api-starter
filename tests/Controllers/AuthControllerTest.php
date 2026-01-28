<?php

namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\AuthenticationTrait;

class AuthControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;
    use AuthenticationTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDatabase();
    }

    protected function seedDatabase(): void
    {
        $seeder = \Config\Database::seeder();
        $seeder->call('Tests\Support\Database\Seeds\TestUserSeeder');
    }

    public function testRegisterSuccess()
    {
        $response = $this->withBodyFormat('json')
            ->post('/api/v1/auth/register', [
                'username' => 'newuser',
                'email'    => 'newuser@example.com',
                'password' => 'newpass123',
            ]);

        $response->assertStatus(201);
        $response->assertJSONFragment(['success' => true]);
        $response->assertJSONFragment(['message' => 'User registered successfully']);

        $json = json_decode($response->getJSON());
        $this->assertObjectHasProperty('token', $json->data);
        $this->assertObjectHasProperty('user', $json->data);
        $this->assertEquals('newuser', $json->data->user->username);
        $this->assertEquals('newuser@example.com', $json->data->user->email);
    }

    public function testRegisterValidationError()
    {
        $response = $this->withBodyFormat('json')
            ->post('/api/v1/auth/register', [
                'username' => 'nu', // Too short
                'email'    => 'invalid-email',
                'password' => 'pass',
            ]);

        $response->assertStatus(422);
        $response->assertJSONFragment(['success' => false]);
        $this->assertObjectHasProperty('errors', json_decode($response->getJSON()));
    }

    public function testRegisterDuplicateUser()
    {
        // First registration
        $this->withBodyFormat('json')
            ->post('/api/v1/auth/register', [
                'username' => 'testuser', // Already exists
                'email'    => 'duplicate@example.com',
                'password' => 'password123',
            ]);

        // Duplicate registration
        $response = $this->withBodyFormat('json')
            ->post('/api/v1/auth/register', [
                'username' => 'testuser', // Already exists
                'email'    => 'another@example.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(422);
        $response->assertJSONFragment(['success' => false]);
    }

    public function testLoginSuccess()
    {
        $response = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
                'password' => 'testpass123',
            ]);

        $response->assertStatus(200);
        $response->assertJSONFragment(['success' => true]);
        $response->assertJSONFragment(['message' => 'Login successful']);

        $json = json_decode($response->getJSON());
        $this->assertObjectHasProperty('token', $json->data);
        $this->assertObjectHasProperty('user', $json->data);
        $this->assertEquals('testuser', $json->data->user->username);
    }

    public function testLoginWithEmail()
    {
        $response = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'test@example.com', // Using email
                'password' => 'testpass123',
            ]);

        $response->assertStatus(200);
        $response->assertJSONFragment(['success' => true]);
    }

    public function testLoginInvalidCredentials()
    {
        $response = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
                'password' => 'wrongpassword',
            ]);

        $response->assertStatus(401);
        $response->assertJSONFragment(['success' => false]);
        $this->assertObjectHasProperty('errors', json_decode($response->getJSON()));
    }

    public function testLoginNonExistentUser()
    {
        $response = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'nonexistent',
                'password' => 'password123',
            ]);

        $response->assertStatus(401);
        $response->assertJSONFragment(['success' => false]);
    }

    public function testLoginMissingCredentials()
    {
        $response = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
            ]);

        $response->assertStatus(401);
        $response->assertJSONFragment(['success' => false]);
    }

    public function testMeEndpointSuccess()
    {
        $token = $this->loginUser('testuser', 'testpass123');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get('/api/v1/auth/me');

        $response->assertStatus(200);
        $response->assertJSONFragment(['success' => true]);

        $json = json_decode($response->getJSON());
        $this->assertEquals('testuser', $json->data->username);
        $this->assertEquals('test@example.com', $json->data->email);
        $this->assertObjectNotHasProperty('password', $json->data);
    }

    public function testMeEndpointWithoutToken()
    {
        $response = $this->get('/api/v1/auth/me');

        $response->assertStatus(401);
        $response->assertJSONFragment(['success' => false]);
        $response->assertJSONFragment(['message' => 'Authorization header missing']);
    }

    public function testMeEndpointWithInvalidToken()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_token_here',
        ])->get('/api/v1/auth/me');

        $response->assertStatus(401);
        $response->assertJSONFragment(['success' => false]);
        $response->assertJSONFragment(['message' => 'Invalid or expired token']);
    }

    public function testJwtTokenContainsCorrectPayload()
    {
        $response = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'testuser',
                'password' => 'testpass123',
            ]);

        $json = json_decode($response->getJSON());
        $token = $json->data->token;

        // Decode JWT token (simple base64 decode of payload)
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'JWT should have 3 parts');

        $payload = json_decode(base64_decode($parts[1]));

        $this->assertObjectHasProperty('uid', $payload);
        $this->assertObjectHasProperty('role', $payload);
        $this->assertObjectHasProperty('iat', $payload);
        $this->assertObjectHasProperty('exp', $payload);
        $this->assertEquals('user', $payload->role);
    }
}
