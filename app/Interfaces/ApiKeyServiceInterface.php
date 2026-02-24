<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * API Key Service Interface
 *
 * Defines the contract for managing API keys with stratified rate limiting.
 */
interface ApiKeyServiceInterface extends CrudServiceContract
{
    /**
     * List all API keys with pagination and optional filters
     *
     * @param array $data Request data (page, limit, search, filter, sort)
     * @return array Paginated list of API keys
     */
    // CRUD contract inherited from CrudServiceContract.
}
