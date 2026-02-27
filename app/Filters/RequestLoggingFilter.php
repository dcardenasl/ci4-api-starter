<?php

declare(strict_types=1);

namespace App\Filters;

use App\HTTP\ApiRequest;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class RequestLoggingFilter implements FilterInterface
{
    /**
     * Before filter - record start time
     *
     * @param RequestInterface $request
     * @param array|null $arguments
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if ($request instanceof ApiRequest) {
            $request->setRequestStartTime(microtime(true));
        }

        return $request;
    }

    /**
     * After filter - log request/response
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array|null $arguments
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Check if request logging is enabled
        if (! env('REQUEST_LOGGING_ENABLED', true)) {
            return $response;
        }

        // Calculate response time
        $startTime = $request instanceof ApiRequest
            ? ($request->getRequestStartTime() ?? microtime(true))
            : microtime(true);
        $responseTime = (int) round((microtime(true) - $startTime) * 1000); // milliseconds

        // Get request data
        $method = $request->getMethod();
        $uri = $request->getUri()->getPath();
        $userId = $request instanceof ApiRequest ? $request->getAuthUserId() : null;
        $ipAddress = $request->getIPAddress();
        $userAgent = $request->getHeaderLine('User-Agent');
        $responseCode = $response->getStatusCode();

        // Queue the log entry (async to not slow down response)
        try {
            $queueManager = Services::queueManager(false);
            $queueManager->push(
                \App\Libraries\Queue\Jobs\LogRequestJob::class,
                [
                    'method' => $method,
                    'uri' => $uri,
                    'user_id' => $userId,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'response_code' => $responseCode,
                    'response_time' => $responseTime,
                ],
                'logs'
            );

            // Log slow queries
            $slowQueryThreshold = (int) env('SLOW_QUERY_THRESHOLD', 1000);
            if ($responseTime > $slowQueryThreshold) {
                log_message('warning', "Slow request detected: {$method} {$uri} ({$responseTime}ms)");
            }
        } catch (\Throwable $e) {
            // Don't fail the request if logging fails
            log_message('error', 'Failed to queue request log: ' . $e->getMessage());
        }

        return $response;
    }
}
