<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\ApiController;
use App\DTO\Request\ApiKeys\ApiKeyCreateRequestDTO;
use App\DTO\Request\ApiKeys\ApiKeyIndexRequestDTO;
use App\DTO\Request\ApiKeys\ApiKeyUpdateRequestDTO;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Modernized API Key Controller
 *
 * Admin-only CRUD endpoints for managing API keys with stratified rate limiting.
 * Uses automated DTO validation.
 */
class ApiKeyController extends ApiController
{
    protected string $serviceName = 'apiKeyService';

    /**
     * List all API keys
     */
    public function index(): ResponseInterface
    {
        return $this->handleRequest('index', ApiKeyIndexRequestDTO::class);
    }

    /**
     * Get a single API key
     */
    public function show(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->getService()->show($id, $context)
        );
    }

    /**
     * Create a new API key
     */
    public function create(): ResponseInterface
    {
        return $this->handleRequest('store', ApiKeyCreateRequestDTO::class);
    }

    /**
     * Update an API key
     */
    public function update(int $id): ResponseInterface
    {
        return $this->handleRequest(
            function ($dto, $context) use ($id) {
                $data = array_filter($dto->toArray(), fn ($v) => $v !== null);
                if (empty($data)) {
                    return $this->fail(lang('Api.invalidRequest'), 400);
                }
                return $this->getService()->update($id, $dto, $context);
            },
            ApiKeyUpdateRequestDTO::class
        );
    }

    /**
     * Delete an API key
     */
    public function delete(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->getService()->destroy($id, $context)
        );
    }
}
