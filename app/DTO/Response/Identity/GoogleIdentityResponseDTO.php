<?php

declare(strict_types=1);

namespace App\DTO\Response\Identity;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Google Identity Response DTO
 *
 * Encapsulates verified identity data from Google.
 */
readonly class GoogleIdentityResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        public string $provider,
        public string $providerId,
        public string $email,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $avatarUrl,
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
