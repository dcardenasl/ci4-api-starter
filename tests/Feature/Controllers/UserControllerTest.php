<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\AuthTestTrait;

class UserControllerTest extends CIUnitTestCase
{
    use AuthTestTrait;
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected UserModel $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new UserModel();
    }

    public function testListUsersRequiresAuth(): void
    {
        $result = $this->get('/api/v1/users');

        $result->assertStatus(401);
    }

    public function testListUsersReturnsSuccess(): void
    {
        $email = 'list-users@example.com';
        $password = 'ValidPass123!';
        $this->createUser($email, $password);

        $token = $this->loginAndGetToken($email, $password);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->get('/api/v1/users');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertEquals('success', $json['status']);
    }

    public function testAdminCanCreateUpdateAndDeleteUser(): void
    {
        $adminEmail = 'admin-users@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        $createResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->post('/api/v1/users', [
            'email' => 'created@example.com',
            'password' => 'ValidPass123!',
            'role' => 'user',
        ]);

        $createResult->assertStatus(201);
        $createJson = json_decode($createResult->getJSON(), true);
        $createdId = $createJson['data']['id'] ?? null;
        $this->assertNotNull($createdId);

        $updateResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->put("/api/v1/users/{$createdId}", [
            'first_name' => 'Updated',
        ]);

        $updateResult->assertStatus(200);

        $deleteResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->delete("/api/v1/users/{$createdId}");

        $deleteResult->assertStatus(200);
    }

    public function testNonAdminCannotCreateUser(): void
    {
        $email = 'non-admin@example.com';
        $password = 'ValidPass123!';
        $this->createUser($email, $password, 'user');

        $token = $this->loginAndGetToken($email, $password);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->post('/api/v1/users', [
            'email' => 'blocked@example.com',
            'password' => 'ValidPass123!',
            'role' => 'user',
        ]);

        $result->assertStatus(403);
    }
}
