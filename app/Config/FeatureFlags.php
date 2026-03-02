<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

class FeatureFlags extends BaseConfig
{
    public bool $monitoringEnabled = true;
    public bool $metricsEnabled = true;

    public function __construct()
    {
        parent::__construct();

        $this->monitoringEnabled = (bool) env('MONITORING_ENABLED', true);
        $this->metricsEnabled = (bool) env('METRICS_ENABLED', true);
    }

    public function isEnabled(string $flag): bool
    {
        return match ($flag) {
            'monitoring' => $this->monitoringEnabled,
            'metrics' => $this->metricsEnabled,
            default => true,
        };
    }
}
