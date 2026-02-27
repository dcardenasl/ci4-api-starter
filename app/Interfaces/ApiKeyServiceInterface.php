<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DTO\SecurityContext;

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
    public function index(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Get a single API key by ID
     */
    public function show(int $id, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Create a new API key
     */
    public function store(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Update an existing API key
     */
    public function update(int $id, \App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Delete an API key
     */
    public function destroy(int $id, ?SecurityContext $context = null): bool;
}
