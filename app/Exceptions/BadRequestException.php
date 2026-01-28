<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Bad Request Exception
 *
 * Thrown when the request is malformed or contains invalid data.
 * HTTP Status: 400 Bad Request
 */
class BadRequestException extends ApiException
{
    protected int $statusCode = 400;

    /**
     * Constructor
     *
     * @param string $message Error message (default: "Bad request")
     * @param array $errors Additional error details
     */
    public function __construct(string $message = 'Bad request', array $errors = [])
    {
        parent::__construct($message, $errors);
    }
}
