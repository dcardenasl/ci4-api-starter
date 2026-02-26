<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DTO\Request\Metrics\RecordMetricRequestDTO;
use App\DTO\Response\Metrics\MetricsOverviewResponseDTO;

/**
 * Metrics Service Interface
 */
interface MetricsServiceInterface
{
    /**
     * Get system metrics overview
     */
    public function getOverview(string $period): MetricsOverviewResponseDTO;

    /**
     * Get request statistics
     */
    public function getRequestStats(string $period): array;

    /**
     * Get slow requests list
     */
    public function getSlowRequests(int $threshold, int $limit): array;

    /**
     * Record a custom metric
     */
    public function record(RecordMetricRequestDTO $request): bool;

    /**
     * Get custom metric data
     */
    public function getCustomMetric(string $name, string $period, bool $aggregate = false): array;
}
