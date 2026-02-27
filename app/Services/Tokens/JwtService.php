<?php

declare(strict_types=1);

namespace App\Services\Tokens;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

/**
 * JWT Service
 *
 * Handles creation and validation of JSON Web Tokens.
 * Focused on high-level security and strict typing.
 */
readonly class JwtService implements \App\Interfaces\Tokens\JwtServiceInterface
{
    private string $algorithm;

    public function __construct(
        private string $secretKey,
        private int $expirationTime = 3600,
        private string $issuer = 'http://localhost:8080'
    ) {
        if (strlen($this->secretKey) < 32) {
            throw new RuntimeException(lang('Api.jwtSecretTooShort'));
        }
        $this->algorithm = 'HS256';
    }

    /**
     * Generate a JWT token with JTI (unique identifier)
     */
    public function encode(int $userId, string $role): string
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + $this->expirationTime;

        // JTI is essential for token revocation support
        $jti = bin2hex(random_bytes(16));

        $payload = [
            'iss'  => $this->issuer,
            'iat'  => $issuedAt,
            'nbf'  => $issuedAt,
            'exp'  => $expirationTime,
            'jti'  => $jti,
            'uid'  => $userId,
            'role' => $role,
        ];

        return JWT::encode($payload, $this->secretKey, $this->algorithm);
    }

    /**
     * Decode and validate a JWT token
     */
    public function decode(string $token): ?object
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));

            // Ensure issuer claim is valid
            if (!isset($decoded->iss) || $decoded->iss !== $this->issuer) {
                log_message('warning', "[JWT] Issuer mismatch. Expected: {$this->issuer}");
                return null;
            }

            return $decoded;
        } catch (Exception $e) {
            log_message('error', '[JWT] Decode error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate if a token is structurally valid and not expired
     */
    public function validate(string $token): bool
    {
        return $this->decode($token) !== null;
    }

    /**
     * Extract user ID from token
     */
    public function getUserId(string $token): ?int
    {
        $decoded = $this->decode($token);
        return isset($decoded->uid) ? (int) $decoded->uid : null;
    }

    /**
     * Extract role from token
     */
    public function getRole(string $token): ?string
    {
        $decoded = $this->decode($token);
        return isset($decoded->role) ? (string) $decoded->role : null;
    }
}
