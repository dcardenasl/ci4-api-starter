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
    required: ['accessToken', 'refreshToken', 'expiresIn', 'user']
)]
readonly class LoginResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(property: 'accessToken', description: 'JWT access token', example: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...')]
        public string $accessToken,
        #[OA\Property(property: 'refreshToken', description: 'Opaque refresh token', example: 'apk_a1b2c3d4e5f6g7h8i9j0...')]
        public string $refreshToken,
        #[OA\Property(property: 'expiresIn', description: 'Token expiration time in seconds', example: 3600)]
        public int $expiresIn,
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
            accessToken: $data['access_token'],
            refreshToken: $data['refresh_token'],
            expiresIn: (int) ($data['expires_in'] ?? 3600),
            user: $data['user']
        );
    }

    public function toArray(): array
    {
        return [
            'accessToken' => $this->accessToken,
            'refreshToken' => $this->refreshToken,
            'expiresIn' => $this->expiresIn,
            'user' => $this->user,
        ];
    }
}
