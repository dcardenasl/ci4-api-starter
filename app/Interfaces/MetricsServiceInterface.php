<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DTO\SecurityContext;

/**
 * Metrics Service Interface
 */
interface MetricsServiceInterface
{
    /**
     * Get system performance overview
     */
    public function getOverview(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\DTO\Response\Metrics\MetricsOverviewResponseDTO;

    /**
     * Get list of slow requests
     */
    public function getSlowRequests(int $threshold, int $limit, ?SecurityContext $context = null): array;

    /**
     * Get raw request stats
     */
    public function getRequestStats(string $period, ?SecurityContext $context = null): array;

    /**
     * Get custom metrics by name
     */
    public function getCustomMetric(string $name, string $period, bool $aggregate = false, ?SecurityContext $context = null): array;

    /**
     * Record a custom metric
     */
    public function record(\App\DTO\Request\Metrics\RecordMetricRequestDTO $request, ?SecurityContext $context = null): bool;
}
