<?php

namespace App\Controllers\Api\V1;

use App\Libraries\ApiResponse;
use App\Models\MetricModel;
use App\Models\RequestLogModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

class MetricsController extends Controller
{
    protected RequestLogModel $requestLogModel;
    protected MetricModel $metricModel;

    public function __construct()
    {
        $this->requestLogModel = new RequestLogModel();
        $this->metricModel = new MetricModel();
    }

    /**
     * Get metrics overview
     *
     * GET /api/v1/metrics
     *
     * @return ResponseInterface
     */
    public function index(): ResponseInterface
    {
        // Check if metrics are enabled
        if (! env('METRICS_ENABLED', true)) {
            return $this->response->setJSON(
                ApiResponse::error([], 'Metrics are disabled', 503)
            )->setStatusCode(503);
        }

        $period = $this->request->getGet('period') ?? 'day';
        $requestStats = $this->requestLogModel->getStats((string) $period);

        $data = [
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
        ];

        return $this->response->setJSON(ApiResponse::success($data));
    }

    /**
     * Get request statistics
     *
     * GET /api/v1/metrics/requests
     *
     * @return ResponseInterface
     */
    public function requests(): ResponseInterface
    {
        $period = $this->request->getGet('period') ?? 'day';

        $stats = $this->requestLogModel->getStats((string) $period);

        return $this->response->setJSON(ApiResponse::success($stats));
    }

    /**
     * Get slow requests
     *
     * GET /api/v1/metrics/slow-requests
     *
     * @return ResponseInterface
     */
    public function slowRequests(): ResponseInterface
    {
        $threshold = (int) ($this->request->getGet('threshold') ?? env('SLOW_QUERY_THRESHOLD', 1000));
        $limit = min((int) ($this->request->getGet('limit') ?? 10), 100);

        $slowRequests = $this->requestLogModel->getSlowRequests($threshold, $limit);

        return $this->response->setJSON(ApiResponse::success([
            'threshold_ms' => $threshold,
            'count' => count($slowRequests),
            'requests' => $slowRequests,
        ]));
    }

    /**
     * Get custom metrics
     *
     * GET /api/v1/metrics/custom/:name
     *
     * @param string $name
     * @return ResponseInterface
     */
    public function custom(string $name): ResponseInterface
    {
        $period = $this->request->getGet('period') ?? 'day';
        $aggregate = $this->request->getGet('aggregate') === 'true';

        if ($aggregate) {
            $data = $this->metricModel->getAggregated($name, $period);
        } else {
            $data = $this->metricModel->getByName($name, $period);
        }

        return $this->response->setJSON(ApiResponse::success($data));
    }

    /**
     * Record a custom metric
     *
     * POST /api/v1/metrics/record
     *
     * @return ResponseInterface
     */
    public function record(): ResponseInterface
    {
        $data = $this->request->getJSON(true);

        $name = $data['name'] ?? '';
        $value = $data['value'] ?? 0;
        $tags = $data['tags'] ?? [];

        if (empty($name)) {
            return $this->response->setJSON(
                ApiResponse::validationError(['name' => 'Metric name is required'])
            )->setStatusCode(422);
        }

        $this->metricModel->record($name, (float) $value, $tags);

        return $this->response->setJSON(
            ApiResponse::success(['message' => 'Metric recorded successfully'])
        )->setStatusCode(201);
    }
}
