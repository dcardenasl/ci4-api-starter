<?php

declare(strict_types=1);

namespace App\DTO\Request\ApiKeys;

use App\DTO\Request\BaseRequestDTO;

/**
 * Api Key Update Request DTO
 */
readonly class ApiKeyUpdateRequestDTO extends BaseRequestDTO
{
    public ?string $name;
    public ?int $isActive;
    public ?int $rateLimitRequests;
    public ?int $rateLimitWindow;
    public ?int $userRateLimit;
    public ?int $ipRateLimit;

    protected function rules(): array
    {
        return [
            'name'                => 'permit_empty|string|max_length[100]',
            'isActive'           => 'permit_empty|in_list[0,1]',
            'rateLimitRequests' => 'permit_empty|is_natural_no_zero',
            'rateLimitWindow'   => 'permit_empty|is_natural_no_zero',
            'userRateLimit'     => 'permit_empty|is_natural_no_zero',
            'ipRateLimit'       => 'permit_empty|is_natural_no_zero',
        ];
    }

    protected function map(array $data): void
    {
        $this->name = isset($data['name']) ? trim((string) $data['name']) : null;
        $this->isActive = isset($data['isActive']) ? (int) (bool) $data['isActive'] : null;
        $this->rateLimitRequests = isset($data['rateLimitRequests']) ? (int) $data['rateLimitRequests'] : null;
        $this->rateLimitWindow = isset($data['rateLimitWindow']) ? (int) $data['rateLimitWindow'] : null;
        $this->userRateLimit = isset($data['userRateLimit']) ? (int) $data['userRateLimit'] : null;
        $this->ipRateLimit = isset($data['ipRateLimit']) ? (int) $data['ipRateLimit'] : null;
    }

    public function toArray(): array
    {
        return array_filter([
            'name'                => $this->name,
            'isActive'           => $this->isActive,
            'rateLimitRequests' => $this->rateLimitRequests,
            'rateLimitWindow'   => $this->rateLimitWindow,
            'userRateLimit'     => $this->userRateLimit,
            'ipRateLimit'       => $this->ipRateLimit,
        ], fn ($v) => $v !== null);
    }
}
