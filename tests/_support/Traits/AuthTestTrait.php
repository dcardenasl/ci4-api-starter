<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use App\Models\UserModel;

trait AuthTestTrait
{
    protected function createUser(
        string $email,
        string $password,
        string $role = 'user',
        string $status = 'active',
        bool $verified = true
    ): int {
        $userModel = new UserModel();

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
        return $json['data']['access_token'] ?? '';
    }
}
