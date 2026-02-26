<?php

declare(strict_types=1);

namespace App\Interfaces;

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
    public function index(\App\Interfaces\DataTransferObjectInterface $request): array;

    /**
     * Get a single API key by ID
     */
    public function show(int $id): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Create a new API key
     */
    public function store(\App\Interfaces\DataTransferObjectInterface $request): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Update an existing API key
     */
    public function update(int $id, \App\Interfaces\DataTransferObjectInterface $request): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Delete an API key
     */
    public function destroy(int $id): array;
}
