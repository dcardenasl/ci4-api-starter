<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Conflict Exception (HTTP 409)
 *
 * Thrown when a request conflicts with the current state of the resource.
 * Examples:
 * - Email already verified
 * - Resource already exists
 * - Version conflict
 * - State transition not allowed
 */
class ConflictException extends ApiException
{
    /**
     * HTTP status code
     *
     * @var int
     */
    protected int $statusCode = 409;

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param array $errors Structured error details
     */
    public function __construct(string $message = 'Request conflicts with current state', array $errors = [])
    {
        parent::__construct($message, $errors);
    }
}
