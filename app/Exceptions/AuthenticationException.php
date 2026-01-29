<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Authentication Exception
 *
 * Thrown when authentication fails or credentials are invalid.
 * HTTP Status: 401 Unauthorized
 */
class AuthenticationException extends ApiException
{
    protected int $statusCode = 401;

    /**
     * Constructor
     *
     * @param string $message Error message (default: "Authentication failed")
     * @param array $errors Additional error details
     */
    public function __construct(string $message = 'Authentication failed', array $errors = [])
    {
        parent::__construct($message, $errors);
    }
}
