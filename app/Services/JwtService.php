<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\JwtServiceInterface;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService implements JwtServiceInterface
{
    private string $secretKey;
    private string $algorithm = 'HS256';
    private int $expirationTime = 3600; // 1 hour in seconds

    public function __construct()
    {
        $this->secretKey = env('JWT_SECRET_KEY', 'your-secret-key-change-in-production');
        $this->expirationTime = (int) env('JWT_ACCESS_TOKEN_TTL', 3600);
    }

    /**
     * Generate a JWT token with JTI (unique identifier)
     *
     * @param int $userId
     * @param string $role
     * @return string
     */
    public function encode(int $userId, string $role): string
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + $this->expirationTime;

        // Generate unique JTI (token identifier) for revocation support
        $jti = bin2hex(random_bytes(16));

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'jti' => $jti,
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
