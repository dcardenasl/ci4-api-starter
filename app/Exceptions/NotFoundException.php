<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Not Found Exception
 *
 * Thrown when a requested resource does not exist.
 * HTTP Status: 404
 */
class NotFoundException extends ApiException
{
    protected int $statusCode = 404;

    /**
     * Constructor
     *
     * @param string $message Error message (default: "Resource not found")
     * @param array $errors Additional error details
     */
    public function __construct(string $message = 'Resource not found', array $errors = [])
    {
        parent::__construct($message, $errors);
    }
}
