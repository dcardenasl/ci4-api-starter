<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DTO\Request\ApiKeys\ApiKeyCreateRequestDTO;
use App\DTO\Response\ApiKeys\ApiKeyResponseDTO;

/**
 * API Key Service Interface
 *
 * Defines the contract for managing API keys with stratified rate limiting.
 */
interface ApiKeyServiceInterface
{
    /**
     * List all API keys with pagination and filtering
     */
    public function index(array $data): array;

    /**
     * Get a single API key by ID
     */
    public function show(array $data): ApiKeyResponseDTO;

    /**
     * Create a new API key
     */
    public function store(ApiKeyCreateRequestDTO $request): ApiKeyResponseDTO;

    /**
     * Update an existing API key
     */
    public function update(array $data): ApiKeyResponseDTO;

    /**
     * Delete an API key
     */
    public function destroy(array $data): array;
}
