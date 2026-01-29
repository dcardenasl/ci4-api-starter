<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Validation Exception
 *
 * Thrown when input validation fails.
 * HTTP Status: 422 Unprocessable Entity
 */
class ValidationException extends ApiException
{
    protected int $statusCode = 422;

    /**
     * Constructor
     *
     * @param string $message Error message (default: "Validation failed")
     * @param array $errors Validation error details
     */
    public function __construct(string $message = 'Validation failed', array $errors = [])
    {
        parent::__construct($message, $errors);
    }
}
