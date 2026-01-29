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
        $since = match ($period) {
            'hour' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'day' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'week' => date('Y-m-d H:i:s', strtotime('-1 week')),
            'month' => date('Y-m-d H:i:s', strtotime('-1 month')),
            default => date('Y-m-d H:i:s', strtotime('-1 day')),
        };

        $totalRequests = $this->where('created_at >=', $since)->countAllResults(false);

        $successfulRequests = $this->where('created_at >=', $since)
            ->where('response_code >=', 200)
            ->where('response_code <', 400)
            ->countAllResults(false);

        $failedRequests = $this->where('created_at >=', $since)
            ->where('response_code >=', 400)
            ->countAllResults(false);

        $avgResponseTime = $this->selectAvg('response_time')
            ->where('created_at >=', $since)
            ->get()
            ->getRow()
            ->response_time ?? 0;

        return [
            'period' => $period,
            'since' => $since,
            'total_requests' => $totalRequests,
            'successful_requests' => $successfulRequests,
            'failed_requests' => $failedRequests,
            'avg_response_time_ms' => round($avgResponseTime, 2),
        ];
    }

    /**
     * Get slow requests
     *
     * @param int $threshold Threshold in milliseconds
     * @param int $limit
     * @return array<int, object>
     */
    public function getSlowRequests(int $threshold = 1000, int $limit = 10): array
    {
        return $this->select('method, uri, response_time, created_at')
            ->where('response_time >', $threshold)
            ->orderBy('response_time', 'DESC')
            ->limit($limit)
            ->find();
    }
}
