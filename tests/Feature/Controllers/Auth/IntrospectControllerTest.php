<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Auth;

use App\Services\Tokens\JwtService;
use Config\Services;
use Tests\Support\ApiTestCase;
use Tests\Support\Traits\AuthTestTrait;

/**
 * IntrospectController Feature Tests
 *
 * Covers POST /api/v1/auth/introspect: token verdicts (valid, expired,
 * revoked, malformed) plus X-App-Key auth (missing, invalid, inactive)
 * and request body validation.
 */
class IntrospectControllerTest extends ApiTestCase
{
    use AuthTestTrait;

    private const APP_KEY_NAME = 'introspect-test-key';

    public function testIntrospectValidTokenReturnsValid(): void
    {
        $userId = $this->createUser('introspect-valid@example.com', 'ValidPass123!');
        $rawKey = $this->createActiveApiKey();
        $token  = Services::jwtService()->encode($userId, ['users.read', 'iam.admin-access']);

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->withBodyFormat('json')
            ->post('/api/v1/auth/introspect', ['token' => $token]);

        $result->assertStatus(200);
        $json = $this->getResponseJson($result);

        $this->assertSame('success', $json['status']);
        $this->assertTrue($json['data']['valid']);
        $this->assertSame($userId, $json['data']['uid']);
        $this->assertEqualsCanonicalizing(['users.read', 'iam.admin-access'], $json['data']['permissions']);
        $this->assertGreaterThan(time(), $json['data']['exp']);
        $this->assertNull($json['data']['error']);
    }

    public function testIntrospectExpiredTokenReturnsInvalid(): void
    {
        $userId = $this->createUser('introspect-expired@example.com', 'ValidPass123!');
        $rawKey = $this->createActiveApiKey();

        $apiConfig    = config('Api');
        $expiredJwt   = new JwtService(
            $apiConfig->jwtSecretKey,
            -10,
            (string) env('app.baseURL', 'http://localhost:8080')
        );
        $expiredToken = $expiredJwt->encode($userId, ['users.read']);

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->withBodyFormat('json')
            ->post('/api/v1/auth/introspect', ['token' => $expiredToken]);

        $result->assertStatus(200);
        $json = $this->getResponseJson($result);

        $this->assertFalse($json['data']['valid']);
        $this->assertNull($json['data']['uid']);
        $this->assertSame([], $json['data']['permissions']);
        $this->assertNull($json['data']['exp']);
        $this->assertSame('invalid_or_expired', $json['data']['error']);
    }

    public function testIntrospectRevokedTokenReturnsInvalid(): void
    {
        $userId = $this->createUser('introspect-revoked@example.com', 'ValidPass123!');
        $rawKey = $this->createActiveApiKey();

        $jwtService = Services::jwtService();
        $token      = $jwtService->encode($userId, ['users.read']);
        $decoded    = $jwtService->decode($token);
        $this->assertNotNull($decoded, 'Freshly minted token must decode');

        Services::tokenRevocationService()->revokeToken(
            (string) $decoded->jti,
            (int) $decoded->exp
        );

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->withBodyFormat('json')
            ->post('/api/v1/auth/introspect', ['token' => $token]);

        $result->assertStatus(200);
        $json = $this->getResponseJson($result);

        $this->assertFalse($json['data']['valid']);
        $this->assertSame('revoked', $json['data']['error']);
    }

    public function testIntrospectMalformedTokenReturnsInvalid(): void
    {
        $rawKey = $this->createActiveApiKey();

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->withBodyFormat('json')
            ->post('/api/v1/auth/introspect', ['token' => 'not-a-real-jwt-payload']);

        $result->assertStatus(200);
        $json = $this->getResponseJson($result);

        $this->assertFalse($json['data']['valid']);
        $this->assertSame('invalid_or_expired', $json['data']['error']);
    }

    public function testIntrospectMissingAppKeyReturns401(): void
    {
        $userId = $this->createUser('introspect-noappkey@example.com', 'ValidPass123!');
        $token  = Services::jwtService()->encode($userId, ['users.read']);

        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/introspect', ['token' => $token]);

        $result->assertStatus(401);
        $json = $this->getResponseJson($result);
        $this->assertSame('error', $json['status']);
    }

    public function testIntrospectInvalidAppKeyReturns403(): void
    {
        $userId = $this->createUser('introspect-badkey@example.com', 'ValidPass123!');
        $token  = Services::jwtService()->encode($userId, ['users.read']);

        $result = $this->withHeaders(['X-App-Key' => 'apk_doesnotexist0000000000000000000'])
            ->withBodyFormat('json')
            ->post('/api/v1/auth/introspect', ['token' => $token]);

        $result->assertStatus(403);
        $json = $this->getResponseJson($result);
        $this->assertSame('error', $json['status']);
    }

    public function testIntrospectInactiveAppKeyReturns403(): void
    {
        $userId = $this->createUser('introspect-inactive@example.com', 'ValidPass123!');
        $rawKey = $this->createActiveApiKey('inactive-introspect-key', isActive: false);
        $token  = Services::jwtService()->encode($userId, ['users.read']);

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->withBodyFormat('json')
            ->post('/api/v1/auth/introspect', ['token' => $token]);

        $result->assertStatus(403);
        $json = $this->getResponseJson($result);
        $this->assertSame('error', $json['status']);
    }

    public function testIntrospectMissingTokenInBodyReturns422(): void
    {
        $rawKey = $this->createActiveApiKey();

        $result = $this->withHeaders(['X-App-Key' => $rawKey])
            ->withBodyFormat('json')
            ->post('/api/v1/auth/introspect', []);

        $result->assertStatus(422);
        $json = $this->getResponseJson($result);
        $this->assertSame('error', $json['status']);
    }

    /**
     * Insert an API key directly and return the raw key value.
     */
    private function createActiveApiKey(string $name = self::APP_KEY_NAME, bool $isActive = true): string
    {
        $material = Services::apiKeyMaterialService();
        $rawKey   = $material->generateRawKey();

        \Config\Database::connect()
            ->table('api_keys')
            ->insert([
                'name'                => $name,
                'key_prefix'          => substr($rawKey, 0, 8),
                'key_hash'            => $material->hash($rawKey),
                'is_active'           => $isActive ? 1 : 0,
                'rate_limit_requests' => 600,
                'rate_limit_window'   => 60,
                'user_rate_limit'     => 60,
                'ip_rate_limit'       => 200,
                'created_at'          => date('Y-m-d H:i:s'),
            ]);

        return $rawKey;
    }
}
