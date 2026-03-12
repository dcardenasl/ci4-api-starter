<?php

declare(strict_types=1);

namespace App\DTO\Response\Auth;

use App\Interfaces\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Login Response DTO
 *
 * Ensures the API contract is respected for login responses.
 */
#[OA\Schema(
    schema: 'LoginResponse',
    title: 'Login Response',
    description: 'Successful authentication response containing JWT tokens and user data',
    required: ['access_token', 'refresh_token', 'expires_in', 'user']
)]
readonly class LoginResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(property: 'access_token', description: 'JWT access token', example: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...')]
        public string $access_token,
        #[OA\Property(property: 'refresh_token', description: 'Opaque refresh token', example: 'apk_a1b2c3d4e5f6g7h8i9j0...')]
        public string $refresh_token,
        #[OA\Property(property: 'expires_in', description: 'Token expiration time in seconds', example: 3600)]
        public int $expires_in,
        #[OA\Property(description: 'Authenticated user data', ref: '#/components/schemas/UserResponse')]
        public array $user
    ) {
    }

    /**
     * @param array $data Expected structure from AuthService::login
     */
    public static function fromArray(array $data): self
    {
        return new self(
            access_token: $data['access_token'],
            refresh_token: $data['refresh_token'],
            expires_in: (int) ($data['expires_in'] ?? 3600),
            user: $data['user']
        );
    }

    public function toArray(): array
    {
        return [
            'access_token' => $this->access_token,
            'refresh_token' => $this->refresh_token,
            'expires_in' => $this->expires_in,
            'user' => $this->user,
        ];
    }
}
