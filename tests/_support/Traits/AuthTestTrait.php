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
        \App\Libraries\ContextHolder::set(new \App\DTO\SecurityContext((int) $this->currentUserId, (string) $role));

        $headers = [
            'Authorization' => "Bearer {$token}",
            'X-Test-User-Id' => (string) $this->currentUserId,
            'X-Test-User-Role' => (string) $role
        ];

        $this->setTestRequestHeaders($headers);

        return [
            'userId' => $this->currentUserId,
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
            return (int) $existing->id;
        }

        return (int) $userModel->insert([
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'role' => $role,
            'status' => $status,
            'email_verified_at' => $verified ? date('Y-m-d H:i:s') : null,
        ]);
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
        return $json['accessToken'] ?? ($json['data']['accessToken'] ?? '');
    }
}
