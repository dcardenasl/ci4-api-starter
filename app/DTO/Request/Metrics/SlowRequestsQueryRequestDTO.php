<?php

declare(strict_types=1);

namespace App\DTO\Request\Metrics;

use App\DTO\Request\BaseRequestDTO;

/**
 * Slow Requests Query Request DTO
 */
readonly class SlowRequestsQueryRequestDTO extends BaseRequestDTO
{
    public int $threshold;
    public int $limit;

    protected function rules(): array
    {
        return [
            'threshold' => 'permit_empty|integer|greater_than_equal_to[1]',
            'limit' => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[100]',
        ];
    }

    protected function map(array $data): void
    {
        $threshold = is_numeric($data['threshold'] ?? null)
            ? (int) $data['threshold']
            : (int) env('SLOW_QUERY_THRESHOLD', 1000);

        $limit = is_numeric($data['limit'] ?? null)
            ? (int) $data['limit']
            : 10;

        $this->threshold = max(1, $threshold);
        $this->limit = min(max(1, $limit), 100);
    }

    public function toArray(): array
    {
        return [
            'threshold' => $this->threshold,
            'limit' => $this->limit,
        ];
    }
}
