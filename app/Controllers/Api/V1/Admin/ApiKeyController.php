<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\ApiController;

/**
 * API Key Controller
 *
 * Admin-only CRUD endpoints for managing API keys with stratified rate limiting.
 * All write operations require the 'admin' role (enforced via roleauth filter in routes).
 */
class ApiKeyController extends ApiController
{
    protected string $serviceName = 'apiKeyService';

    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $dto = $this->getDTO(\App\DTO\Request\ApiKeys\ApiKeyCreateRequestDTO::class);

        return $this->handleRequest(
            fn () => $this->getService()->store($dto)
        );
    }
}
