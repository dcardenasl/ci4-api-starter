<?php

declare(strict_types=1);

namespace App\DTO\Response\ApiKeys;

use App\Interfaces\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Api Key Response DTO
 */
#[OA\Schema(
    schema: 'ApiKeyResponse',
    title: 'API Key Response',
    description: 'API key object metadata',
    required: ['id', 'name', 'keyPrefix', 'isActive']
)]
readonly class ApiKeyResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Unique API key identifier', example: 1)]
        public int $id,
        #[OA\Property(description: 'Human-readable label for the API key', example: 'My Mobile App')]
        public string $name,
        #[OA\Property(property: 'keyPrefix', description: 'First characters of the key (safe to display)', example: 'apk_a3f9c2b1')]
        public string $keyPrefix,
        #[OA\Property(property: 'isActive', description: 'Whether the key is currently active', example: true)]
        public bool $isActive,
        #[OA\Property(property: 'rateLimitRequests', description: 'Max requests per window', example: 600)]
        public int $rateLimitRequests,
        #[OA\Property(property: 'rateLimitWindow', description: 'Window in seconds', example: 60)]
        public int $rateLimitWindow,
        #[OA\Property(property: 'userRateLimit', description: 'Per-user limit', example: 60)]
        public int $userRateLimit,
        #[OA\Property(property: 'ipRateLimit', description: 'Per-IP limit', example: 200)]
        public int $ipRateLimit,
        #[OA\Property(description: 'Full raw API key (only returned once)', example: 'apk_a3f9...', nullable: true)]
        public ?string $key = null,
        #[OA\Property(property: 'createdAt', description: 'Creation timestamp', example: '2026-02-26 12:00:00', nullable: true)]
        public ?string $createdAt = null,
        #[OA\Property(property: 'updatedAt', description: 'Last update timestamp', example: '2026-02-26 12:00:00', nullable: true)]
        public ?string $updatedAt = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        $createdAt = $data['created_at'] ?? null;
        $updatedAt = $data['updated_at'] ?? null;

        foreach ([$createdAt, $updatedAt] as &$date) {
            if ($date instanceof \DateTimeInterface) {
                $date = $date->format('Y-m-d H:i:s');
            }
        }

        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            keyPrefix: (string) ($data['key_prefix'] ?? ''),
            isActive: (bool) ($data['is_active'] ?? true),
            rateLimitRequests: (int) ($data['rate_limit_requests'] ?? 600),
            rateLimitWindow: (int) ($data['rate_limit_window'] ?? 60),
            userRateLimit: (int) ($data['user_rate_limit'] ?? 60),
            ipRateLimit: (int) ($data['ip_rate_limit'] ?? 200),
            key: $data['key'] ?? null,
            createdAt: $createdAt ? (string) $createdAt : null,
            updatedAt: $updatedAt ? (string) $updatedAt : null
        );
    }

    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
            'name' => $this->name,
            'keyPrefix' => $this->keyPrefix,
            'isActive' => $this->isActive,
            'rateLimitRequests' => $this->rateLimitRequests,
            'rateLimitWindow' => $this->rateLimitWindow,
            'userRateLimit' => $this->userRateLimit,
            'ipRateLimit' => $this->ipRateLimit,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];

        if ($this->key !== null) {
            $result['key'] = $this->key;
        }

        return $result;
    }
}
