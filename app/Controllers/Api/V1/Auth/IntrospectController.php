<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Auth;

use App\Controllers\ApiController;
use App\DTO\Request\Auth\IntrospectRequestDTO;
use App\Interfaces\Auth\TokenIntrospectionServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Token Introspection Controller
 *
 * Exposes JWT validation as an HTTP endpoint so domain apps can verify
 * user tokens without sharing the JWT secret. Caller is authenticated
 * via X-App-Key (appKeyRequired filter).
 */
class IntrospectController extends ApiController
{
    protected TokenIntrospectionServiceInterface $introspectionService;

    protected function resolveDefaultService(): object
    {
        $this->introspectionService = Services::tokenIntrospectionService();

        return $this->introspectionService;
    }

    public function introspect(): ResponseInterface
    {
        return $this->handleRequest('introspect', IntrospectRequestDTO::class);
    }
}
