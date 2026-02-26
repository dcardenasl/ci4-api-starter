<?php

namespace App\Models;

use CodeIgniter\Model;

class RequestLogModel extends Model
{
    protected $table = 'request_logs';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'method',
        'uri',
        'user_id',
        'ip_address',
        'user_agent',
        'response_code',
        'response_time',
        'created_at',
    ];
    protected $useTimestamps = false;

    /**
     * Get request statistics
     *
     * @param string $period (hour, day, week, month)
     * @return array<string, mixed>
     */
    public function getStats(string $period = 'day'): array
    {
        $since = $this->getSinceFromPeriod($period);

        $totalRequests = (int) $this->db->table($this->table)
            ->where('created_at >=', $since)
            ->countAllResults();

        $successfulRequests = (int) $this->db->table($this->table)
            ->where('created_at >=', $since)
            ->where('response_code >=', 200)
            ->where('response_code <', 400)
            ->countAllResults();

        $failedRequests = (int) $this->db->table($this->table)
            ->where('created_at >=', $since)
            ->where('response_code >=', 400)
            ->countAllResults();

        $avgResponseTimeQuery = $this->db->table($this->table)
            ->select('AVG(response_time) as avg_response_time')
            ->where('created_at >=', $since)
            ->get();

        $avgResponseTimeRaw = $avgResponseTimeQuery ? $avgResponseTimeQuery->getRow() : null;
        $avgResponseTime = $avgResponseTimeRaw ? (float) ($avgResponseTimeRaw->avg_response_time ?? 0) : 0.0;

        $responseTimesQuery = $this->db->table($this->table)
            ->select('response_time')
            ->where('created_at >=', $since)
            ->orderBy('response_time', 'ASC')
            ->get();

        $responseTimes = $responseTimesQuery ? $responseTimesQuery->getResultArray() : [];

        $latencies = array_map(
            static fn (array $row): int => (int) ($row['response_time'] ?? 0),
            $responseTimes
        );

        $p95 = $this->percentile($latencies, 0.95);
        $p99 = $this->percentile($latencies, 0.99);

        $errorRate = $totalRequests > 0 ? ($failedRequests / $totalRequests) * 100 : 0.0;
        $availability = $totalRequests > 0 ? ($successfulRequests / $totalRequests) * 100 : 100.0;
        $latencyTarget = (int) env('SLO_API_P95_TARGET_MS', 500);

        return [
            'period' => $period,
            'since' => $since,
            'total_requests' => $totalRequests,
            'successful_requests' => $successfulRequests,
            'failed_requests' => $failedRequests,
            'avg_response_time_ms' => round($avgResponseTime, 2),
            'p95_response_time_ms' => $p95,
            'p99_response_time_ms' => $p99,
            'error_rate_percent' => round($errorRate, 2),
            'availability_percent' => round($availability, 2),
            'status_code_breakdown' => $this->getStatusCodeBreakdown($since),
            'slo' => [
                'p95_target_ms' => $latencyTarget,
                'p95_target_met' => $p95 <= $latencyTarget,
            ],
        ];
    }

    /**
     * Get slow requests
     *
     * @param int $threshold Threshold in milliseconds
     * @param int $limit
     * @return array<int, array<int|string, bool|float|int|object|string|null>|object>
     */
    public function getSlowRequests(int $threshold = 1000, int $limit = 10): array
    {
        return $this->select('method, uri, response_time, created_at')
            ->where('response_time >', $threshold)
            ->orderBy('response_time', 'DESC')
            ->limit($limit)
            ->find();
    }

    private function getSinceFromPeriod(string $period): string
    {
        return match ($period) {
            'hour' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'day' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'week' => date('Y-m-d H:i:s', strtotime('-1 week')),
            'month' => date('Y-m-d H:i:s', strtotime('-1 month')),
            default => date('Y-m-d H:i:s', strtotime('-1 day')),
        };
    }

    /**
     * @param array<int, int> $values
     */
    private function percentile(array $values, float $percentile): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        $index = (int) ceil($percentile * $count) - 1;
        $index = max(0, min($index, $count - 1));

        return (float) $values[$index];
    }

    /**
     * @return array{'2xx':int,'3xx':int,'4xx':int,'5xx':int}
     */
    private function getStatusCodeBreakdown(string $since): array
    {
        return [
            '2xx' => (int) $this->db->table($this->table)
                ->where('created_at >=', $since)
                ->where('response_code >=', 200)
                ->where('response_code <', 300)
                ->countAllResults(),
            '3xx' => (int) $this->db->table($this->table)
                ->where('created_at >=', $since)
                ->where('response_code >=', 300)
                ->where('response_code <', 400)
                ->countAllResults(),
            '4xx' => (int) $this->db->table($this->table)
                ->where('created_at >=', $since)
                ->where('response_code >=', 400)
                ->where('response_code <', 500)
                ->countAllResults(),
            '5xx' => (int) $this->db->table($this->table)
                ->where('created_at >=', $since)
                ->where('response_code >=', 500)
                ->where('response_code <', 600)
                ->countAllResults(),
        ];
    }
}
