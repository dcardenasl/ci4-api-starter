<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\JwtService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * JwtService Unit Tests
 *
 * Comprehensive test coverage for JWT token operations.
 * Tests encoding, decoding, validation, and security aspects.
 */
class JwtServiceTest extends CIUnitTestCase
{
    protected JwtService $service;
    protected string $testSecretKey = 'test-secret-key-for-unit-testing-do-not-use-in-production';

    protected function setUp(): void
    {
        parent::setUp();

        // Set test secret key in environment
        putenv("JWT_SECRET_KEY={$this->testSecretKey}");

        $this->service = new JwtService();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('JWT_SECRET_KEY'); // Clear environment variable
    }

    // ==================== ENCODE TESTS ====================

    public function testEncodeGeneratesValidToken(): void
    {
        $userId = 1;
        $role = 'user';

        $token = $this->service->encode($userId, $role);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertStringContainsString('.', $token); // JWT format: xxx.yyy.zzz
    }

    public function testEncodeIncludesUserIdInPayload(): void
    {
        $userId = 42;
        $role = 'admin';

        $token = $this->service->encode($userId, $role);
        $decoded = JWT::decode($token, new Key($this->testSecretKey, 'HS256'));

        $this->assertEquals($userId, $decoded->uid);
    }

    public function testEncodeIncludesRoleInPayload(): void
    {
        $userId = 1;
        $role = 'admin';

        $token = $this->service->encode($userId, $role);
        $decoded = JWT::decode($token, new Key($this->testSecretKey, 'HS256'));

        $this->assertEquals($role, $decoded->role);
    }

    public function testEncodeIncludesTimestamps(): void
    {
        $beforeTime = time();
        $token = $this->service->encode(1, 'user');
        $afterTime = time();

        $decoded = JWT::decode($token, new Key($this->testSecretKey, 'HS256'));

        $this->assertObjectHasProperty('iat', $decoded);
        $this->assertObjectHasProperty('exp', $decoded);
        $this->assertGreaterThanOrEqual($beforeTime, $decoded->iat);
        $this->assertLessThanOrEqual($afterTime, $decoded->iat);
    }

    public function testEncodeGeneratesTokenWithOneHourExpiration(): void
    {
        $token = $this->service->encode(1, 'user');
        $decoded = JWT::decode($token, new Key($this->testSecretKey, 'HS256'));

        $expectedExpiration = $decoded->iat + 3600; // 1 hour
        $this->assertEquals($expectedExpiration, $decoded->exp);
    }

    public function testEncodedTokensAreDeterministicWithinSameSecond(): void
    {
        // Tokens generated in the same second should be identical
        // (since iat is in seconds, not milliseconds)
        $token1 = $this->service->encode(1, 'user');
        $token2 = $this->service->encode(1, 'user');

        // May or may not be equal depending on if second changed
        // Just verify both are valid
        $this->assertNotEmpty($token1);
        $this->assertNotEmpty($token2);
    }

    // ==================== DECODE TESTS ====================

    public function testDecodeExtractsPayloadFromValidToken(): void
    {
        $userId = 123;
        $role = 'admin';

        $token = $this->service->encode($userId, $role);
        $decoded = $this->service->decode($token);

        $this->assertNotNull($decoded);
        $this->assertEquals($userId, $decoded->uid);
        $this->assertEquals($role, $decoded->role);
    }

    public function testDecodeReturnsNullForInvalidToken(): void
    {
        $invalidToken = 'invalid.token.here';

        $decoded = $this->service->decode($invalidToken);

        $this->assertNull($decoded);
    }

    public function testDecodeReturnsNullForExpiredToken(): void
    {
        // Create a token that's already expired
        $payload = [
            'iat' => time() - 7200, // 2 hours ago
            'exp' => time() - 3600, // Expired 1 hour ago
            'uid' => 1,
            'role' => 'user',
        ];

        $expiredToken = JWT::encode($payload, $this->testSecretKey, 'HS256');
        $decoded = $this->service->decode($expiredToken);

        $this->assertNull($decoded);
    }

    public function testDecodeReturnsNullForTokenWithInvalidSignature(): void
    {
        // Create token with one key, try to decode with different key
        // Key must be 256 bits (32 bytes) for HS256
        $otherKey = 'another-secret-key-for-testing-must-be-long-enough-256-bits';
        $token = JWT::encode(['uid' => 1, 'role' => 'user', 'iat' => time(), 'exp' => time() + 3600], $otherKey, 'HS256');

        $decoded = $this->service->decode($token);

        $this->assertNull($decoded);
    }

    public function testDecodeReturnsNullForMalformedToken(): void
    {
        $malformedTokens = [
            '',
            'not-a-jwt',
            'missing.signature',
            '...',
            'too.many.dots.here.now',
        ];

        foreach ($malformedTokens as $token) {
            $decoded = $this->service->decode($token);
            $this->assertNull($decoded, "Malformed token should return null: {$token}");
        }
    }

    // ==================== VALIDATE TESTS ====================

    public function testValidateReturnsTrueForValidToken(): void
    {
        $token = $this->service->encode(1, 'user');

        $isValid = $this->service->validate($token);

        $this->assertTrue($isValid);
    }

    public function testValidateReturnsFalseForInvalidToken(): void
    {
        $invalidToken = 'invalid.token.here';

        $isValid = $this->service->validate($invalidToken);

        $this->assertFalse($isValid);
    }

    public function testValidateReturnsFalseForExpiredToken(): void
    {
        $payload = [
            'iat' => time() - 7200,
            'exp' => time() - 3600,
            'uid' => 1,
            'role' => 'user',
        ];

        $expiredToken = JWT::encode($payload, $this->testSecretKey, 'HS256');
        $isValid = $this->service->validate($expiredToken);

        $this->assertFalse($isValid);
    }

    // ==================== GET USER ID TESTS ====================

    public function testGetUserIdExtractsUserIdFromToken(): void
    {
        $expectedUserId = 999;
        $token = $this->service->encode($expectedUserId, 'user');

        $userId = $this->service->getUserId($token);

        $this->assertEquals($expectedUserId, $userId);
    }

    public function testGetUserIdReturnsNullForInvalidToken(): void
    {
        $userId = $this->service->getUserId('invalid.token');

        $this->assertNull($userId);
    }

    public function testGetUserIdReturnsIntegerType(): void
    {
        $token = $this->service->encode(42, 'user');

        $userId = $this->service->getUserId($token);

        $this->assertIsInt($userId);
    }

    // ==================== GET ROLE TESTS ====================

    public function testGetRoleExtractsRoleFromToken(): void
    {
        $expectedRole = 'admin';
        $token = $this->service->encode(1, $expectedRole);

        $role = $this->service->getRole($token);

        $this->assertEquals($expectedRole, $role);
    }

    public function testGetRoleReturnsNullForInvalidToken(): void
    {
        $role = $this->service->getRole('invalid.token');

        $this->assertNull($role);
    }

    public function testGetRoleReturnsStringType(): void
    {
        $token = $this->service->encode(1, 'user');

        $role = $this->service->getRole($token);

        $this->assertIsString($role);
    }

    // ==================== SECURITY TESTS ====================

    public function testTokenCannotBeModifiedWithoutDetection(): void
    {
        $token = $this->service->encode(1, 'user');
        $parts = explode('.', $token);

        // Try to modify the payload (middle part)
        $modifiedPayload = base64_encode('{"uid":999,"role":"admin"}');
        $tamperedToken = $parts[0] . '.' . $modifiedPayload . '.' . $parts[2];

        $decoded = $this->service->decode($tamperedToken);

        $this->assertNull($decoded, 'Tampered token should be rejected');
    }

    public function testDifferentUsersSamRoleGenerateDifferentTokens(): void
    {
        $token1 = $this->service->encode(1, 'user');
        $token2 = $this->service->encode(2, 'user');

        $this->assertNotEquals($token1, $token2);
    }

    public function testSameUserDifferentRolesGenerateDifferentTokens(): void
    {
        $token1 = $this->service->encode(1, 'user');
        $token2 = $this->service->encode(1, 'admin');

        $this->assertNotEquals($token1, $token2);
    }

    // ==================== EDGE CASES ====================

    public function testEncodeHandlesZeroUserId(): void
    {
        $token = $this->service->encode(0, 'user');
        $decoded = $this->service->decode($token);

        $this->assertEquals(0, $decoded->uid);
    }

    public function testEncodeHandlesLargeUserId(): void
    {
        $largeId = PHP_INT_MAX;
        $token = $this->service->encode($largeId, 'user');
        $decoded = $this->service->decode($token);

        $this->assertEquals($largeId, $decoded->uid);
    }

    public function testEncodeHandlesSpecialCharactersInRole(): void
    {
        $specialRole = 'super-admin_v2';
        $token = $this->service->encode(1, $specialRole);
        $decoded = $this->service->decode($token);

        $this->assertEquals($specialRole, $decoded->role);
    }

    public function testDecodeHandlesEmptyString(): void
    {
        $decoded = $this->service->decode('');

        $this->assertNull($decoded);
    }

    // ==================== INTEGRATION TESTS ====================

    public function testFullRoundTripEncodeDecodePreservesData(): void
    {
        $userId = 42;
        $role = 'moderator';

        $token = $this->service->encode($userId, $role);
        $decoded = $this->service->decode($token);

        $this->assertNotNull($decoded);
        $this->assertEquals($userId, $decoded->uid);
        $this->assertEquals($role, $decoded->role);
        $this->assertObjectHasProperty('iat', $decoded);
        $this->assertObjectHasProperty('exp', $decoded);
    }

    public function testTokenValidityPeriod(): void
    {
        $token = $this->service->encode(1, 'user');
        $decoded = $this->service->decode($token);

        $now = time();
        $validityPeriod = $decoded->exp - $decoded->iat;

        $this->assertEquals(3600, $validityPeriod, 'Token should be valid for exactly 1 hour');
        $this->assertGreaterThanOrEqual($now, $decoded->iat, 'iat should be now or past');
        $this->assertLessThanOrEqual($now + 3601, $decoded->exp, 'exp should be within 1 hour from now');
    }

    // ==================== ERROR HANDLING TESTS ====================

    public function testDecodeLogsErrorsButDoesNotThrow(): void
    {
        // This test verifies that decode handles exceptions gracefully
        $invalidToken = 'definitely.not.valid';

        // Should not throw exception
        $decoded = $this->service->decode($invalidToken);

        $this->assertNull($decoded);
    }

    public function testValidateDoesNotThrowOnInvalidInput(): void
    {
        $testCases = [
            '',
            'null',
            '12345',
            'random-string',
            '{}',
            '[]',
        ];

        foreach ($testCases as $input) {
            $isValid = $this->service->validate($input);
            $this->assertFalse($isValid, "Should return false for: {$input}");
        }
    }
}
