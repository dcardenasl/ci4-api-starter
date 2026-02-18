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
     * List all API keys with pagination and optional filters
     *
     * @param array $data Request data (page, limit, search, filter, sort)
     * @return array Paginated list of API keys
     */
    public function index(array $data): array;

    /**
     * Get a single API key by ID
     *
     * @param array $data Request data containing 'id'
     * @return array API key data
     */
    public function show(array $data): array;

    /**
     * Create a new API key
     *
     * Returns the raw key once in the response — it cannot be retrieved again.
     *
     * @param array $data Request data (name, rate limits)
     * @return array Created API key data including the one-time raw key
     */
    public function store(array $data): array;

    /**
     * Update an existing API key
     *
     * @param array $data Request data including 'id'
     * @return array Updated API key data
     */
    public function update(array $data): array;

    /**
     * Delete an API key permanently
     *
     * @param array $data Request data containing 'id'
     * @return array Success message
     */
    public function destroy(array $data): array;
}
