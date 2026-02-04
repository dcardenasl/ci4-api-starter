<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Service Unavailable Exception (HTTP 503)
 *
 * Thrown when a service is temporarily unavailable.
 * Examples:
 * - Database maintenance
 * - External API unavailable
 * - System overload
 * - Scheduled downtime
 */
class ServiceUnavailableException extends ApiException
{
    /**
     * HTTP status code
     *
     * @var int
     */
    protected int $statusCode = 503;

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param array $errors Structured error details
     */
    public function __construct(string $message = 'Service temporarily unavailable', array $errors = [])
    {
        parent::__construct($message, $errors);
    }
}
