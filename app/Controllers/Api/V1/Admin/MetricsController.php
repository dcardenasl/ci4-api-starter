<?php

namespace App\Controllers\Api\V1\Admin;

use App\DTO\Request\Metrics\RecordMetricRequestDTO;
use App\Libraries\ApiResponse;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Metrics Controller
 *
 * Infrastructure/observability endpoints.
 */
class MetricsController extends Controller
{
    protected function getService()
    {
        return service('metricsService');
    }

    /**
     * Get metrics overview
     */
    public function index(): ResponseInterface
    {
        if (! env('METRICS_ENABLED', true)) {
            return $this->response->setJSON(
                ApiResponse::error([], lang('Metrics.disabled'), 503)
            )->setStatusCode(503);
        }

        $period = (string) ($this->request->getGet('period') ?? 'day');
        $result = $this->getService()->getOverview($period);

        return $this->response->setJSON(ApiResponse::success($result->toArray()));
    }

    /**
     * Get request statistics
     */
    public function requests(): ResponseInterface
    {
        $period = (string) ($this->request->getGet('period') ?? 'day');
        $stats = $this->getService()->getRequestStats($period);

        return $this->response->setJSON(ApiResponse::success($stats));
    }

    /**
     * Get slow requests
     */
    public function slowRequests(): ResponseInterface
    {
        $threshold = (int) ($this->request->getGet('threshold') ?? env('SLOW_QUERY_THRESHOLD', 1000));
        $limit = min((int) ($this->request->getGet('limit') ?? 10), 100);

        $slowRequests = $this->getService()->getSlowRequests($threshold, $limit);

        return $this->response->setJSON(ApiResponse::success([
            'threshold_ms' => $threshold,
            'count' => count($slowRequests),
            'requests' => $slowRequests,
        ]));
    }

    /**
     * Get custom metrics
     */
    public function custom(string $name): ResponseInterface
    {
        $period = (string) ($this->request->getGet('period') ?? 'day');
        $aggregate = $this->request->getGet('aggregate') === 'true';

        $data = $this->getService()->getCustomMetric($name, $period, $aggregate);

        return $this->response->setJSON(ApiResponse::success($data));
    }

    /**
     * Record a custom metric
     */
    public function record(): ResponseInterface
    {
        try {
            $dto = new RecordMetricRequestDTO($this->request->getJSON(true) ?? []);
            $this->getService()->record($dto);

            return $this->response->setJSON(
                ApiResponse::success(['message' => lang('Metrics.recordedSuccessfully')])
            )->setStatusCode(201);
        } catch (\App\Exceptions\ValidationException $e) {
            return $this->response->setJSON(
                ApiResponse::validationError($e->getErrors())
            )->setStatusCode(422);
        }
    }
}
