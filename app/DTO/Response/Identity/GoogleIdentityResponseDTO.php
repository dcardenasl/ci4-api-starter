<?php

declare(strict_types=1);

namespace App\DTO\Response\Identity;

use App\Interfaces\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Google Identity Response DTO
 *
 * Encapsulates verified identity data from Google.
 */
#[OA\Schema(
    schema: 'GoogleIdentityResponse',
    title: 'Google Identity Response',
    description: 'Verified identity data returned by Google',
    required: ['provider', 'providerId', 'email']
)]
readonly class GoogleIdentityResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Identity provider', example: 'google')]
        public string $provider,
        #[OA\Property(property: 'providerId', description: 'Provider user identifier', example: '113337022221111122223')]
        public string $providerId,
        #[OA\Property(description: 'User email address', example: 'user@example.com')]
        public string $email,
        #[OA\Property(property: 'firstName', description: 'User first name', example: 'Alex', nullable: true)]
        public ?string $firstName,
        #[OA\Property(property: 'lastName', description: 'User last name', example: 'Doe', nullable: true)]
        public ?string $lastName,
        #[OA\Property(property: 'avatarUrl', description: 'Avatar URL', example: 'https://example.com/avatar.png', nullable: true)]
        public ?string $avatarUrl,
        #[OA\Property(description: 'Raw identity claims', type: 'object', additionalProperties: true)]
        public array $claims
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            provider: 'google',
            providerId: (string) ($data['provider_id'] ?? $data['sub'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            firstName: $data['first_name'] ?? $data['given_name'] ?? null,
            lastName: $data['last_name'] ?? $data['family_name'] ?? null,
            avatarUrl: $data['avatar_url'] ?? $data['picture'] ?? null,
            claims: $data['claims'] ?? $data
        );
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'providerId' => $this->providerId,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'avatarUrl' => $this->avatarUrl,
            'claims' => $this->claims,
        ];
    }
}
