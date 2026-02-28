<?php

declare(strict_types=1);

namespace App\DTO\Request\ApiKeys;

use App\DTO\Request\BaseRequestDTO;

/**
 * Api Key Create Request DTO
 *
 * Validates data for creating a new API Key.
 */
readonly class ApiKeyCreateRequestDTO extends BaseRequestDTO
{
    public string $name;
    public ?int $rateLimitRequests;
    public ?int $rateLimitWindow;
    public ?int $userRateLimit;
    public ?int $ipRateLimit;

    protected function rules(): array
    {
        return [
            'name'              => 'required|string|max_length[100]',
            'rateLimitRequests' => 'permit_empty|is_natural_no_zero',
            'rateLimitWindow'   => 'permit_empty|is_natural_no_zero',
            'userRateLimit'     => 'permit_empty|is_natural_no_zero',
            'ipRateLimit'       => 'permit_empty|is_natural_no_zero',
        ];
    }

    protected function map(array $data): void
    {
        $this->name = trim((string) $data['name']);
        $this->rateLimitRequests = isset($data['rateLimitRequests']) ? (int) $data['rateLimitRequests'] : null;
        $this->rateLimitWindow = isset($data['rateLimitWindow']) ? (int) $data['rateLimitWindow'] : null;
        $this->userRateLimit = isset($data['userRateLimit']) ? (int) $data['userRateLimit'] : null;
        $this->ipRateLimit = isset($data['ipRateLimit']) ? (int) $data['ipRateLimit'] : null;
    }

    public function toArray(): array
    {
        return array_filter([
            'name'              => $this->name,
            'rateLimitRequests' => $this->rateLimitRequests,
            'rateLimitWindow'   => $this->rateLimitWindow,
            'userRateLimit'     => $this->userRateLimit,
            'ipRateLimit'       => $this->ipRateLimit,
        ], fn ($v) => $v !== null);
    }
}
