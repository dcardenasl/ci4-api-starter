<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\MetricsServiceInterface;
use App\Models\MetricModel;
use App\Models\RequestLogModel;

/**
 * Metrics Service
 *
 * Handles aggregation and recording of system performance metrics.
 */
class MetricsService implements MetricsServiceInterface
{
    protected RequestLogModel $requestLogModel;
    protected MetricModel $metricModel;

    public function __construct()
    {
        $this->requestLogModel = new RequestLogModel();
        $this->metricModel = new MetricModel();
    }

    public function getOverview(string $period): \App\DTO\Response\Metrics\MetricsOverviewResponseDTO
    {
        $requestStats = $this->requestLogModel->getStats($period);

        return \App\DTO\Response\Metrics\MetricsOverviewResponseDTO::fromArray([
            'request_stats' => $requestStats,
            'slow_requests' => $this->requestLogModel->getSlowRequests(
                (int) env('SLOW_QUERY_THRESHOLD', 1000),
                5
            ),
            'slo' => [
                'availability_percent' => $requestStats['availability_percent'] ?? 100,
                'error_rate_percent' => $requestStats['error_rate_percent'] ?? 0,
                'p95_response_time_ms' => $requestStats['p95_response_time_ms'] ?? 0,
                'p99_response_time_ms' => $requestStats['p99_response_time_ms'] ?? 0,
                'p95_target_ms' => $requestStats['slo']['p95_target_ms'] ?? (int) env('SLO_API_P95_TARGET_MS', 500),
                'p95_target_met' => $requestStats['slo']['p95_target_met'] ?? true,
            ],
        ]);
    }

    public function getRequestStats(string $period): array
    {
        return $this->requestLogModel->getStats($period);
    }

    public function getSlowRequests(int $threshold, int $limit): array
    {
        return $this->requestLogModel->getSlowRequests($threshold, $limit);
    }

    public function record(\App\DTO\Request\Metrics\RecordMetricRequestDTO $request): bool
    {
        return $this->metricModel->record($request->name, $request->value, $request->tags);
    }

    public function getCustomMetric(string $name, string $period, bool $aggregate = false): array
    {
        if ($aggregate) {
            return $this->metricModel->getAggregated($name, $period);
        }

        return $this->metricModel->getByName($name, $period);
    }
}
