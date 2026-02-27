<?php

declare(strict_types=1);

namespace App\Interfaces\System;

use App\DTO\SecurityContext;
use App\Support\OperationResult;

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
    public function getSlowRequests(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Get raw request stats
     */
    public function getRequestStats(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Get custom metrics by name
     */
    public function getCustomMetric(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Record a custom metric
     */
    public function record(\App\DTO\Request\Metrics\RecordMetricRequestDTO $request, ?SecurityContext $context = null): OperationResult;
}
