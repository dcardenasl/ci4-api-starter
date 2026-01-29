<?php

namespace App\Models;

use CodeIgniter\Model;

class MetricModel extends Model
{
    protected $table = 'metrics';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'metric_name',
        'metric_value',
        'tags',
        'created_at',
    ];
    protected $useTimestamps = false;

    /**
     * Record a metric
     *
     * @param string $name
     * @param float $value
     * @param array<string, mixed> $tags
     * @return int|false
     */
    public function record(string $name, float $value, array $tags = [])
    {
        return $this->insert([
            'metric_name' => $name,
            'metric_value' => $value,
            'tags' => empty($tags) ? null : json_encode($tags),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get metrics by name
     *
     * @param string $name
     * @param string $period
     * @return array<int, object>
     */
    public function getByName(string $name, string $period = 'day'): array
    {
        $since = match ($period) {
            'hour' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'day' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'week' => date('Y-m-d H:i:s', strtotime('-1 week')),
            'month' => date('Y-m-d H:i:s', strtotime('-1 month')),
            default => date('Y-m-d H:i:s', strtotime('-1 day')),
        };

        return $this->where('metric_name', $name)
            ->where('created_at >=', $since)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get aggregated metrics
     *
     * @param string $name
     * @param string $period
     * @return array<string, mixed>
     */
    public function getAggregated(string $name, string $period = 'day'): array
    {
        $since = match ($period) {
            'hour' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'day' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'week' => date('Y-m-d H:i:s', strtotime('-1 week')),
            'month' => date('Y-m-d H:i:s', strtotime('-1 month')),
            default => date('Y-m-d H:i:s', strtotime('-1 day')),
        };

        $result = $this->select('
            COUNT(*) as count,
            AVG(metric_value) as average,
            MIN(metric_value) as minimum,
            MAX(metric_value) as maximum,
            SUM(metric_value) as sum
        ')
            ->where('metric_name', $name)
            ->where('created_at >=', $since)
            ->get()
            ->getRow();

        return [
            'metric_name' => $name,
            'period' => $period,
            'count' => (int) $result->count,
            'average' => round((float) $result->average, 2),
            'minimum' => round((float) $result->minimum, 2),
            'maximum' => round((float) $result->maximum, 2),
            'sum' => round((float) $result->sum, 2),
        ];
    }
}
