<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\UserModel;
use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

class UserControllerTest extends ApiTestCase
{
    use AuthTestTrait;

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

        $json = $this->getResponseJson($result);
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
            'role' => 'user',
        ]);

        $createResult->assertStatus(201);
        $createJson = $this->getResponseJson($createResult);
        $createdId = $createJson['data']['id'] ?? null;
        $this->assertNotNull($createdId);
        $this->assertEquals('invited', $createJson['data']['status'] ?? null);

        $this->resetRequest();

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

    public function testAdminCreateUserIsLoggedInAudit(): void
    {
        $adminEmail = 'admin-audit-users@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        $createResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->post('/api/v1/users', [
            'email' => 'audit-created@example.com',
            'role' => 'user',
        ]);

        $createResult->assertStatus(201);
        $createJson = $this->getResponseJson($createResult);
        $createdId = (int) ($createJson['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $createdId);

        $auditCount = $this->db->table('audit_logs')
            ->where('entity_type', 'users')
            ->where('entity_id', $createdId)
            ->where('action', 'create')
            ->countAllResults();

        $this->assertGreaterThan(0, $auditCount);
    }

    public function testAdminCannotCreateAdminUser(): void
    {
        $adminEmail = 'admin-create-admin@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->post('/api/v1/users', [
            'email' => 'new-admin@example.com',
            'role' => 'admin',
        ]);

        $result->assertStatus(403);
    }

    public function testAdminCannotCreateSuperadminUser(): void
    {
        $adminEmail = 'admin-create-superadmin@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->post('/api/v1/users', [
            'email' => 'new-superadmin@example.com',
            'role' => 'superadmin',
        ]);

        $result->assertStatus(403);
    }

    public function testAdminCannotUpdateAnotherAdmin(): void
    {
        $adminEmail = 'admin-update-admin@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');
        $targetAdminId = $this->createUser('target-admin@example.com', 'ValidPass123!', 'admin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->put("/api/v1/users/{$targetAdminId}", [
            'first_name' => 'Blocked',
        ]);

        $result->assertStatus(403);
    }

    public function testAdminCannotDeleteAnotherAdmin(): void
    {
        $adminEmail = 'admin-delete-admin@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');
        $targetAdminId = $this->createUser('target-delete-admin@example.com', 'ValidPass123!', 'admin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->delete("/api/v1/users/{$targetAdminId}");

        $result->assertStatus(403);
    }

    public function testAdminCannotDeleteSuperadmin(): void
    {
        $adminEmail = 'admin-delete-superadmin@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');
        $superadminId = $this->createUser('target-superadmin@example.com', 'ValidPass123!', 'superadmin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->delete("/api/v1/users/{$superadminId}");

        $result->assertStatus(403);
    }

    public function testListUsersDoesNotIncludeSuperadmin(): void
    {
        $userEmail = 'list-regular-user@example.com';
        $userPassword = 'ValidPass123!';
        $this->createUser($userEmail, $userPassword, 'user');
        $this->createUser('hidden-superadmin@example.com', 'ValidPass123!', 'superadmin');

        $token = $this->loginAndGetToken($userEmail, $userPassword);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->get('/api/v1/users');

        $result->assertStatus(200);
        $json = $this->getResponseJson($result);
        $roles = array_map(
            static fn ($item) => $item['role'] ?? null,
            $json['data'] ?? []
        );

        $this->assertNotContains('superadmin', $roles);
    }

    public function testSuperadminCanManageAdminUsers(): void
    {
        $superadminEmail = 'root@example.com';
        $superadminPassword = 'ValidPass123!';
        $this->createUser($superadminEmail, $superadminPassword, 'superadmin');

        $token = $this->loginAndGetToken($superadminEmail, $superadminPassword);

        $createResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->post('/api/v1/users', [
            'email' => 'managed-admin@example.com',
            'role' => 'admin',
        ]);

        $createResult->assertStatus(201);
        $createJson = $this->getResponseJson($createResult);
        $createdId = (int) ($createJson['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $createdId);

        $this->resetRequest();

        $updateResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->put("/api/v1/users/{$createdId}", [
            'first_name' => 'Managed',
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
            'role' => 'user',
        ]);

        $result->assertStatus(403);
    }

    public function testAdminCreateUserRejectsPasswordField(): void
    {
        $adminEmail = 'admin-password-block@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        $result = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->post('/api/v1/users', [
            'email' => 'blocked-password@example.com',
            'password' => 'ValidPass123!',
            'role' => 'user',
        ]);

        $result->assertStatus(422);

        $json = $this->getResponseJson($result);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('password', $json['errors']);
    }

    public function testAdminCannotApproveInvitedUser(): void
    {
        $adminEmail = 'admin-approve-invited@example.com';
        $adminPassword = 'ValidPass123!';
        $this->createUser($adminEmail, $adminPassword, 'admin');

        $token = $this->loginAndGetToken($adminEmail, $adminPassword);

        $createResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->withBodyFormat('json')->post('/api/v1/users', [
            'email' => 'already-invited@example.com',
            'role' => 'user',
        ]);

        $createResult->assertStatus(201);
        $createJson = $this->getResponseJson($createResult);
        $createdId = $createJson['data']['id'] ?? null;
        $this->assertNotNull($createdId);

        $approveResult = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->post("/api/v1/users/{$createdId}/approve");

        $approveResult->assertStatus(409);
    }
}
