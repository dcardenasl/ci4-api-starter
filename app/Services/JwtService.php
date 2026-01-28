<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JwtService
{
    private string $secretKey;
    private string $algorithm = 'HS256';
    private int $expirationTime = 3600; // 1 hour in seconds

    public function __construct()
    {
        $this->secretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
    }

    /**
     * Generate a JWT token
     *
     * @param int $userId
     * @param string $role
     * @return string
     */
    public function encode(int $userId, string $role): string
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + $this->expirationTime;

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'uid' => $userId,
            'role' => $role,
        ];

        return JWT::encode($payload, $this->secretKey, $this->algorithm);
    }

    /**
     * Decode and validate a JWT token
     *
     * @param string $token
     * @return object|null
     */
    public function decode(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->secretKey, $this->algorithm));
        } catch (Exception $e) {
            log_message('error', 'JWT decode error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate if a token is valid
     *
     * @param string $token
     * @return bool
     */
    public function validate(string $token): bool
    {
        return $this->decode($token) !== null;
    }

    /**
     * Extract user ID from token
     *
     * @param string $token
     * @return int|null
     */
    public function getUserId(string $token): ?int
    {
        $decoded = $this->decode($token);
        return $decoded ? (int)$decoded->uid : null;
    }

    /**
     * Extract role from token
     *
     * @param string $token
     * @return string|null
     */
    public function getRole(string $token): ?string
    {
        $decoded = $this->decode($token);
        return $decoded ? $decoded->role : null;
    }
}
