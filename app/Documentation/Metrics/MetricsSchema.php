<?php

declare(strict_types=1);

namespace App\Documentation\Metrics;

use OpenApi\Attributes as OA;

/**
 * Metrics Schemas
 */
#[OA\Schema(
    schema: 'MetricsOverview',
    title: 'Metrics Overview',
    description: 'Overview of request stats and slow requests',
    properties: [
        new OA\Property(property: 'request_stats', type: 'object'),
        new OA\Property(
            property: 'slow_requests',
            type: 'array',
            items: new OA\Items(type: 'object')
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'MetricRecord',
    title: 'Metric Record',
    description: 'Recorded metric acknowledgement',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Metric recorded successfully'),
    ],
    type: 'object'
)]
class MetricsSchema
{
}
