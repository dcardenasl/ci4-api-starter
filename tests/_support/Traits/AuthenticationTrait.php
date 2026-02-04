<?php

namespace Tests\Support\Traits;

use App\Services\JwtService;

trait AuthenticationTrait
{
    /**
     * Generate a JWT token for testing
     *
     * @param int $userId
     * @param string $role
     * @return string
     */
    protected function getJwtToken(int $userId = 1, string $role = 'user'): string
    {
        $jwtService = new JwtService();
        return $jwtService->encode($userId, $role);
    }

    /**
     * Get authorization header with JWT token
     *
     * @param int $userId
     * @param string $role
     * @return array
     */
    protected function getAuthHeaders(int $userId = 1, string $role = 'user'): array
    {
        $token = $this->getJwtToken($userId, $role);
        return [
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    /**
     * Login a test user and get token
     *
     * @param string $username
     * @param string $password
     * @return string|null
     */
    protected function loginUser(string $username = 'testuser', string $password = 'Testpass123'): ?string
    {
        $response = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => $username,
                'password' => $password,
            ]);

        $result = json_decode($response->getJSON());

        return $result->data->access_token ?? null;
    }
}
