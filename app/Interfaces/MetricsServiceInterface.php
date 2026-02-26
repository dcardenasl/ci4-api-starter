<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Metrics Service Interface
 */
interface MetricsServiceInterface
{
    /**
     * Get system performance overview
     */
    public function getOverview(\App\Interfaces\DataTransferObjectInterface $request): \App\DTO\Response\Metrics\MetricsOverviewResponseDTO;

    /**
     * Get list of slow requests
     */
    public function getSlowRequests(int $threshold, int $limit): array;

    /**
     * Get raw request stats
     */
    public function getRequestStats(string $period): array;

    /**
     * Get custom metrics by name
     */
    public function getCustomMetric(string $name, string $period, bool $aggregate = false): array;

    /**
     * Record a custom metric
     */
    public function record(\App\DTO\Request\Metrics\RecordMetricRequestDTO $request): bool;
}
