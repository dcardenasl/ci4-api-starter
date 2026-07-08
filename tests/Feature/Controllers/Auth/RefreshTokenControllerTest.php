<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Auth;

use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

/**
 * POST /api/v1/auth/refresh Feature Tests
 *
 * Locks in the fix for RefreshTokenModel::findActiveForUpdate(), which used to
 * append a raw `FOR UPDATE` clause unconditionally. SQLite3 (the `tests` DB
 * group driver) doesn't support that syntax, so the query silently failed and
 * every refresh attempt — even with a genuinely valid, freshly-issued token —
 * was rejected with 401. No Feature test previously exercised this endpoint
 * over real HTTP; the only prior coverage (RefreshTokenServiceTest) mocked
 * RefreshTokenModel entirely, sidestepping the raw query.
 */
class RefreshTokenControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    public function testRefreshWithValidTokenReturnsNewTokenPair(): void
    {
        $email = 'refresh-valid@example.com';
        $password = 'ValidPass123!';
        $this->createUser($email, $password);

        $login = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => $email,
                'password' => $password,
            ]);

        $login->assertStatus(200);
        $loginJson = $this->getResponseJson($login);
        $refreshToken = $loginJson['data']['refresh_token'] ?? '';
        $this->assertNotSame('', $refreshToken, 'Login response must include a refresh_token.');

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/refresh', [
                'refresh_token' => $refreshToken,
            ]);

        $result->assertStatus(200);

        $json = $this->getResponseJson($result);
        $this->assertArrayHasKey('access_token', $json['data']);
        $this->assertArrayHasKey('refresh_token', $json['data']);
        $this->assertNotSame('', $json['data']['access_token']);
        $this->assertNotSame($refreshToken, $json['data']['refresh_token'], 'Refresh must rotate the refresh token.');
    }

    public function testRefreshWithInvalidTokenReturns401(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/refresh', [
                'refresh_token' => 'not-a-real-refresh-token-value',
            ]);

        $result->assertStatus(401);

        $json = $this->getResponseJson($result);
        $this->assertEquals('error', $json['status']);
    }

    public function testRefreshWithRevokedTokenReturns401(): void
    {
        $email = 'refresh-revoked@example.com';
        $password = 'ValidPass123!';
        $this->createUser($email, $password);

        $login = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'email' => $email,
                'password' => $password,
            ]);
        $login->assertStatus(200);
        $loginJson = $this->getResponseJson($login);
        $accessToken = $loginJson['data']['access_token'] ?? '';
        $refreshToken = $loginJson['data']['refresh_token'] ?? '';

        // Revoking all tokens marks the refresh token as revoked_at != null,
        // which findActiveForUpdate() must still correctly exclude.
        $this->withHeaders([
            'Authorization' => "Bearer {$accessToken}",
        ])->post('/api/v1/auth/revoke-all');

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/refresh', [
                'refresh_token' => $refreshToken,
            ]);

        $result->assertStatus(401);
    }
}
