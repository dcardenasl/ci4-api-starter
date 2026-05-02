<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use App\Models\UserModel;
use CodeIgniter\Test\FeatureTestTrait;

trait AuthTestTrait
{
    use FeatureTestTrait;

    protected ?int $currentUserId = null;
    protected ?string $currentUserRole = null;

    /**
     * Creates a user and returns their identity and token.
     * Uses real login process to ensure framework compatibility.
     *
     * @return array{userId: int, role: string, token: string}
     */
    protected function actAs(string $role = 'user', array $overrides = []): array
    {
        $email = $overrides['email'] ?? 'testuser' . uniqid() . '@example.com';
        $password = 'ValidPass123!';

        $this->currentUserId = $this->createUser(
            $email,
            $password,
            $role,
            $overrides['status'] ?? 'active',
            $overrides['verified'] ?? true
        );
        $this->currentUserRole = $role;

        // Perform real login to get a valid JWT token
        $token = $this->loginAndGetToken($email, $password);

        // Inject identity into static holder for direct service access in tests
        $permissions = \App\Support\TestPermissionResolver::permissionsForRole($role);
        \App\Libraries\ContextHolder::set(new \App\DTO\SecurityContext(
            (int) $this->currentUserId,
            [],
            $permissions
        ));

        $headers = [
            'Authorization' => "Bearer {$token}",
            'X-Test-User-Id' => (string) $this->currentUserId,
            'X-Test-User-Role' => (string) $role
        ];

        $this->setTestRequestHeaders($headers);

        return [
            'user_id' => $this->currentUserId,
            'role'   => $role,
            'token'  => $token
        ];
    }

    /**
     * Explicitly enables audit logging for the current test.
     */
    protected function enableAudit(): void
    {
        \App\Services\System\AuditService::$forceEnabledInTests = true;
    }

    /**
     * Disables audit logging (default in tests).
     */
    protected function disableAudit(): void
    {
        \App\Services\System\AuditService::$forceEnabledInTests = false;
    }    protected function createUser(
        string $email,
        string $password,
        string $role = 'user',
        string $status = 'active',
        bool $verified = true
    ): int {
        $userModel = new UserModel();

        // Ensure we don't try to re-create the same user in a test run
        $existing = $userModel->where('email', $email)->first();
        if ($existing) {
            $userId = (int) $existing->id;
            $this->ensureMembership($userId, $role);
            return $userId;
        }

        $userId = (int) $userModel->insert([
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'role' => $role,
            'status' => $status,
            'email_verified_at' => $verified ? date('Y-m-d H:i:s') : null,
        ]);

        $this->ensureMembership($userId, $role);

        return $userId;
    }

    /**
     * Mirrors the role assignment in the new IAM tables so the user resolves
     * the same effective permissions through real auth flows.
     */
    private function ensureMembership(int $userId, string $roleCode): void
    {
        $db = \Config\Database::connect();

        $role = $db->table('roles')->where('code', $roleCode)->where('application_id', 1)->get()?->getRowArray();
        if ($role === null) {
            return;
        }

        $membership = $db->table('app_user_memberships')
            ->where('user_id', $userId)
            ->where('application_id', 1)
            ->get()?->getRowArray();

        if ($membership === null) {
            $now = date('Y-m-d H:i:s');
            $db->table('app_user_memberships')->insert([
                'user_id'        => $userId,
                'application_id' => 1,
                'status'         => 'active',
                'accepted_at'    => $now,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            $membershipId = (int) $db->insertID();
        } else {
            $membershipId = (int) $membership['id'];
        }

        $exists = $db->table('membership_roles')
            ->where('membership_id', $membershipId)
            ->where('role_id', (int) $role['id'])
            ->countAllResults() > 0;

        if (! $exists) {
            $db->table('membership_roles')->insert([
                'membership_id' => $membershipId,
                'role_id'       => (int) $role['id'],
            ]);
        }
    }

    protected function loginAndGetToken(string $email, string $password): string
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => $email,
                'password' => $password,
            ]);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        return $json['access_token'] ?? ($json['data']['access_token'] ?? '');
    }
}
