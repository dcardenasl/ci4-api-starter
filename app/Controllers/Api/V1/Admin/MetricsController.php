<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\ApiController;
use App\DTO\Request\Metrics\MetricsQueryRequestDTO;
use App\DTO\Request\Metrics\RecordMetricRequestDTO;
use App\Libraries\ApiResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Modernized Metrics Controller
 *
 * Provides administrative access to system performance and usage metrics.
 */
class MetricsController extends ApiController
{
    protected string $serviceName = 'metricsService';

    /**
     * Map upload to 201 Created status
     */
    protected array $statusCodes = [
        'record' => 201,
    ];

    /**
     * Get system metrics overview
     */
    public function index(): ResponseInterface
    {
        if (! env('METRICS_ENABLED', true)) {
            return $this->respond(ApiResponse::error([], lang('Metrics.disabled'), 503), 503);
        }

        return $this->handleRequest('getOverview', MetricsQueryRequestDTO::class);
    }

    /**
     * Get request statistics
     */
    public function requests(): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->getService()->getRequestStats($dto->period, $context),
            MetricsQueryRequestDTO::class
        );
    }

    /**
     * Get list of slow requests
     */
    public function slowRequests(): ResponseInterface
    {
        return $this->handleRequest(function ($dto, $context) {
            /** @var \App\HTTP\ApiRequest $request */
            $request = $this->request;

            $thresholdVal = $request->getVar('threshold');
            $threshold = is_numeric($thresholdVal) ? (int) $thresholdVal : (int) env('SLOW_QUERY_THRESHOLD', 1000);

            $limitVal = $request->getVar('limit');
            $limit = min(is_numeric($limitVal) ? (int) $limitVal : 10, 100);

            return $this->getService()->getSlowRequests($threshold, $limit, $context);
        });
    }


    /**
     * Get custom metrics by name
     */
    public function custom(string $name): ResponseInterface
    {
        return $this->handleRequest(function ($dto, $context) use ($name) {
            $aggregate = $this->request->getGet('aggregate') === 'true';
            return $this->getService()->getCustomMetric($name, $dto->period, $aggregate, $context);
        }, MetricsQueryRequestDTO::class);
    }

    /**
     * Record a new custom metric
     */
    public function record(): ResponseInterface
    {
        return $this->handleRequest('record', RecordMetricRequestDTO::class);
    }
}
