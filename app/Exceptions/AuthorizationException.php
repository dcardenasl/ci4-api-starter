<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Authorization Exception
 *
 * Thrown when user lacks permission to access a resource.
 * HTTP Status: 403 Forbidden
 */
class AuthorizationException extends ApiException
{
    protected int $statusCode = 403;

    /**
     * Constructor
     *
     * @param string $message Error message (default: "Insufficient permissions")
     * @param array $errors Additional error details
     */
    public function __construct(string $message = 'Insufficient permissions', array $errors = [])
    {
        parent::__construct($message, $errors);
    }
}
