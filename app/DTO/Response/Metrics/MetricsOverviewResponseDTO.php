<?php

declare(strict_types=1);

namespace App\DTO\Response\Metrics;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Metrics Overview Response DTO
 */
readonly class MetricsOverviewResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        public array $requestStats,
        public array $slowRequests,
        public array $slo,
        public string $timestamp
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            requestStats: $data['request_stats'] ?? [],
            slowRequests: $data['slow_requests'] ?? [],
            slo: $data['slo'] ?? [],
            timestamp: date('Y-m-d H:i:s')
        );
    }

    public function toArray(): array
    {
        return [
            'requestStats' => $this->requestStats,
            'slowRequests' => $this->slowRequests,
            'slo' => $this->slo,
            'timestamp' => $this->timestamp,
        ];
    }
}
