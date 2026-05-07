<?php

declare(strict_types=1);

namespace App\Interfaces\Auth;

use App\DTO\Request\Auth\IntrospectRequestDTO;
use App\DTO\Response\Auth\IntrospectResponseDTO;

interface TokenIntrospectionServiceInterface
{
    /**
     * Validate a JWT and return its introspection result.
     *
     * Always returns a DTO — invalid/expired/revoked tokens are reported
     * via the `valid` flag rather than thrown as exceptions.
     */
    public function introspect(IntrospectRequestDTO $request): IntrospectResponseDTO;
}
