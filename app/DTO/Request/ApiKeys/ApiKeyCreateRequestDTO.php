<?php

declare(strict_types=1);

namespace App\DTO\Request\ApiKeys;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Api Key Create Request DTO
 */
readonly class ApiKeyCreateRequestDTO implements DataTransferObjectInterface
{
    public string $name;
    public ?int $rateLimitRequests;
    public ?int $rateLimitWindow;
    public ?int $userRateLimit;
    public ?int $ipRateLimit;

    public function __construct(array $data)
    {
        // REUTILIZACIÓN: Usamos el sistema de validación centralizado
        // validación 'api_key.store' requiere 'name'
        validateOrFail($data, 'api_key', 'store');

        $this->name = (string) $data['name'];
        $this->rateLimitRequests = isset($data['rate_limit_requests']) ? (int) $data['rate_limit_requests'] : null;
        $this->rateLimitWindow = isset($data['rate_limit_window']) ? (int) $data['rate_limit_window'] : null;
        $this->userRateLimit = isset($data['user_rate_limit']) ? (int) $data['user_rate_limit'] : null;
        $this->ipRateLimit = isset($data['ip_rate_limit']) ? (int) $data['ip_rate_limit'] : null;
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'rate_limit_requests' => $this->rateLimitRequests,
            'rate_limit_window' => $this->rateLimitWindow,
            'user_rate_limit' => $this->userRateLimit,
            'ip_rate_limit' => $this->ipRateLimit,
        ], fn ($v) => $v !== null);
    }
}
