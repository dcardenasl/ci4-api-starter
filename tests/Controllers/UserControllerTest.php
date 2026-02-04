<?php

declare(strict_types=1);

namespace Tests\Controllers;

use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Traits\AuthenticationTrait;

/**
 * User Controller Tests
 *
 * Tests HTTP endpoints for user CRUD operations with authentication and authorization.
 */
class UserControllerTest extends DatabaseTestCase
{
    use FeatureTestTrait;
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

    // ==================== INDEX TESTS (GET /api/v1/users) ====================

    public function testIndexRequiresAuthentication(): void
    {
        $response = $this->get('/api/v1/users');

        $response->assertStatus(401);
        $response->assertJSONFragment(['status' => 'error']);
    }

    public function testIndexReturnsAllUsersForAuthenticatedUser(): void
    {
        $headers = $this->getAuthHeaders(1, 'user');

        $response = $this->withHeaders($headers)->get('/api/v1/users');

        $response->assertStatus(200);
        $response->assertJSONFragment(['status' => 'success']);

        $json = json_decode($response->getJSON());
        $this->assertObjectHasProperty('data', $json);
        $this->assertIsArray($json->data);
    }

    public function testIndexReturnsAllUsersForAdmin(): void
    {
        $headers = $this->getAuthHeaders(1, 'admin');

        $response = $this->withHeaders($headers)->get('/api/v1/users');

        $response->assertStatus(200);
        $response->assertJSONFragment(['status' => 'success']);
    }

    public function testIndexSupportsPagination(): void
    {
        $headers = $this->getAuthHeaders(1, 'user');

        $response = $this->withHeaders($headers)->get('/api/v1/users?page=1&perPage=10');

        $response->assertStatus(200);

        $json = json_decode($response->getJSON());
        $this->assertObjectHasProperty('meta', $json);
        $this->assertObjectHasProperty('total', $json->meta);
        $this->assertObjectHasProperty('page', $json->meta);
        $this->assertObjectHasProperty('perPage', $json->meta);
    }

    public function testIndexSupportsSearch(): void
    {
        $headers = $this->getAuthHeaders(1, 'user');

        $response = $this->withHeaders($headers)->get('/api/v1/users?search=testuser');

        $response->assertStatus(200);
        $response->assertJSONFragment(['status' => 'success']);
    }

    // ==================== SHOW TESTS (GET /api/v1/users/:id) ====================

    public function testShowRequiresAuthentication(): void
    {
        $response = $this->get('/api/v1/users/1');

        $response->assertStatus(401);
        $response->assertJSONFragment(['status' => 'error']);
    }

    public function testShowReturnsUserByIdWhenAuthenticated(): void
    {
        $headers = $this->getAuthHeaders(1, 'user');

        $response = $this->withHeaders($headers)->get('/api/v1/users/1');

        $response->assertStatus(200);
        $response->assertJSONFragment(['status' => 'success']);

        $json = json_decode($response->getJSON());
        $this->assertObjectHasProperty('data', $json);
        $this->assertObjectHasProperty('id', $json->data);
        $this->assertEquals(1, $json->data->id);
    }

    public function testShowReturns404WhenUserNotFound(): void
    {
        $headers = $this->getAuthHeaders(1, 'user');

        $response = $this->withHeaders($headers)->get('/api/v1/users/99999');

        $response->assertStatus(404);
        $response->assertJSONFragment(['status' => 'error']);
    }

    public function testShowAllowsUserToViewOwnProfile(): void
    {
        $headers = $this->getAuthHeaders(1, 'user');

        $response = $this->withHeaders($headers)->get('/api/v1/users/1');

        $response->assertStatus(200);
        $response->assertJSONFragment(['status' => 'success']);
    }

    // ==================== CREATE TESTS (POST /api/v1/users) ====================

    public function testCreateRequiresAuthentication(): void
    {
        $response = $this->withBodyFormat('json')
            ->post('/api/v1/users', [
                'username' => 'newuser',
                'email'    => 'newuser@example.com',
                'password' => 'Newpass123!',
            ]);

        $response->assertStatus(401);
    }

    public function testCreateRequiresAdminRole(): void
    {
        $headers = $this->getAuthHeaders(1, 'user'); // Regular user, not admin

        $response = $this->withHeaders($headers)
            ->withBodyFormat('json')
            ->post('/api/v1/users', [
                'username' => 'newuser',
                'email'    => 'newuser@example.com',
                'password' => 'Newpass123!',
            ]);

        $response->assertStatus(403);
        $response->assertJSONFragment(['status' => 'error']);
    }

    public function testCreateCreatesNewUserWithValidDataAsAdmin(): void
    {
        $headers = $this->getAuthHeaders(1, 'admin');

        $response = $this->withHeaders($headers)
            ->withBodyFormat('json')
            ->post('/api/v1/users', [
                'username' => 'adminuser',
                'email'    => 'adminuser@example.com',
                'password' => 'Adminpass123!',
                'role'     => 'user',
            ]);

        $response->assertStatus(201);
        $response->assertJSONFragment(['status' => 'success']);

        $json = json_decode($response->getJSON());
        $this->assertEquals('adminuser', $json->data->username);
        $this->assertEquals('adminuser@example.com', $json->data->email);
    }

    public function testCreateValidatesRequiredFields(): void
    {
        $headers = $this->getAuthHeaders(1, 'admin');

        $response = $this->withHeaders($headers)
            ->withBodyFormat('json')
            ->post('/api/v1/users', [
                'username' => 'nu', // Too short
                'email'    => 'invalid-email',
            ]);

        $response->assertStatus(400);
        $response->assertJSONFragment(['status' => 'error']);
    }

    // ==================== UPDATE TESTS (PUT /api/v1/users/:id) ====================

    public function testUpdateRequiresAuthentication(): void
    {
        $response = $this->withBodyFormat('json')
            ->put('/api/v1/users/1', [
                'username' => 'updateduser',
            ]);

        $response->assertStatus(401);
    }

    public function testUpdateRequiresAdminRoleToUpdateOtherUsers(): void
    {
        $headers = $this->getAuthHeaders(2, 'user'); // User ID 2 trying to update user ID 1

        $response = $this->withHeaders($headers)
            ->withBodyFormat('json')
            ->put('/api/v1/users/1', [
                'username' => 'hackeduser',
            ]);

        $response->assertStatus(403);
        $response->assertJSONFragment(['status' => 'error']);
    }

    public function testUpdateUpdatesUserDataAsAdmin(): void
    {
        $headers = $this->getAuthHeaders(1, 'admin');

        $response = $this->withHeaders($headers)
            ->withBodyFormat('json')
            ->put('/api/v1/users/1', [
                'username' => 'updateduser',
                'email'    => 'updated@example.com',
            ]);

        $response->assertStatus(200);
        $response->assertJSONFragment(['status' => 'success']);

        $json = json_decode($response->getJSON());
        $this->assertEquals('updateduser', $json->data->username);
    }

    public function testUpdateValidatesEmailFormat(): void
    {
        $headers = $this->getAuthHeaders(1, 'admin');

        $response = $this->withHeaders($headers)
            ->withBodyFormat('json')
            ->put('/api/v1/users/1', [
                'email' => 'invalid-email-format',
            ]);

        $response->assertStatus(400);
        $response->assertJSONFragment(['status' => 'error']);
    }

    public function testUpdatePreventsRoleEscalation(): void
    {
        $headers = $this->getAuthHeaders(2, 'user'); // Regular user

        $response = $this->withHeaders($headers)
            ->withBodyFormat('json')
            ->put('/api/v1/users/2', [
                'role' => 'admin', // Trying to escalate to admin
            ]);

        // Should be blocked by roleauth filter (403) or business logic
        $this->assertTrue(in_array($response->getStatusCode(), [400, 403], true));
    }

    // ==================== DELETE TESTS (DELETE /api/v1/users/:id) ====================

    public function testDeleteRequiresAuthentication(): void
    {
        $response = $this->delete('/api/v1/users/1');

        $response->assertStatus(401);
    }

    public function testDeleteRequiresAdminRole(): void
    {
        $headers = $this->getAuthHeaders(1, 'user');

        $response = $this->withHeaders($headers)->delete('/api/v1/users/1');

        $response->assertStatus(403);
    }

    public function testDeleteRemovesUserAsAdmin(): void
    {
        $headers = $this->getAuthHeaders(1, 'admin');

        // First verify user exists
        $checkResponse = $this->withHeaders($headers)->get('/api/v1/users/2');
        $checkResponse->assertStatus(200);

        // Delete user
        $response = $this->withHeaders($headers)->delete('/api/v1/users/2');

        $response->assertStatus(204);
    }

    public function testDeleteReturns404WhenUserNotFound(): void
    {
        $headers = $this->getAuthHeaders(1, 'admin');

        $response = $this->withHeaders($headers)->delete('/api/v1/users/99999');

        $response->assertStatus(404);
    }
}
