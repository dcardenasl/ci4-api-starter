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
            'is_active'           => 'permit_empty|in_list[0,1]',
            'rate_limit_requests' => 'permit_empty|is_natural_no_zero',
            'rate_limit_window'   => 'permit_empty|is_natural_no_zero',
            'user_rate_limit'     => 'permit_empty|is_natural_no_zero',
            'ip_rate_limit'       => 'permit_empty|is_natural_no_zero',
        ];
    }

    protected function map(array $data): void
    {
        $this->name = isset($data['name']) ? trim((string) $data['name']) : null;
        $this->isActive = isset($data['is_active']) ? (int) (bool) $data['is_active'] : null;
        $this->rateLimitRequests = isset($data['rate_limit_requests']) ? (int) $data['rate_limit_requests'] : null;
        $this->rateLimitWindow = isset($data['rate_limit_window']) ? (int) $data['rate_limit_window'] : null;
        $this->userRateLimit = isset($data['user_rate_limit']) ? (int) $data['user_rate_limit'] : null;
        $this->ipRateLimit = isset($data['ip_rate_limit']) ? (int) $data['ip_rate_limit'] : null;
    }

    public function toArray(): array
    {
        return array_filter([
            'name'                => $this->name,
            'is_active'           => $this->isActive,
            'rate_limit_requests' => $this->rateLimitRequests,
            'rate_limit_window'   => $this->rateLimitWindow,
            'user_rate_limit'     => $this->userRateLimit,
            'ip_rate_limit'       => $this->ipRateLimit,
        ], fn ($v) => $v !== null);
    }
}
