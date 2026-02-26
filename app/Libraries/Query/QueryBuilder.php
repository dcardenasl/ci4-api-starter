<?php

declare(strict_types=1);

namespace App\Libraries\Query;

use CodeIgniter\Model;

/**
 * QueryBuilder
 *
 * Provides a fluent interface for building complex database queries
 * with filtering, sorting, searching, and pagination.
 */
class QueryBuilder
{
    protected Model $model;

    /**
     * @var array<string, array<int, mixed>>
     */
    protected array $filters = [];

    /**
     * @var array<int, array<int, string>>
     */
    protected array $sorts = [];

    protected ?string $searchQuery = null;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Apply filters to the query
     *
     * @param array $filters Filter array in format: ['field' => 'value'] or ['field' => ['operator', 'value']]
     * @return self
     */
    public function filter(array $filters): self
    {
        // Validate fields against model's filterableFields whitelist
        if (property_exists($this->model, 'filterableFields') && !empty($this->model->filterableFields)) {
            $filters = FilterParser::filterAllowedFields($filters, $this->model->filterableFields);
        }

        $this->filters = FilterParser::parse($filters);

        foreach ($this->filters as $field => $condition) {
            [$operator, $value] = $condition;
            FilterOperatorApplier::apply($this->model, $field, $operator, $value);
        }

        return $this;
    }

    /**
     * Apply sorting to the query
     *
     * SECURITY: Validates sort fields against model's sortableFields whitelist
     * to prevent SQL injection attacks via sort parameter.
     *
     * @param string $sort Sort string in format: "-created_at,email" (- prefix for DESC)
     * @return self
     */
    public function sort(string $sort): self
    {
        // Get sortable fields from model
        $sortableFields = [];

        if (property_exists($this->model, 'sortableFields')) {
            $sortableFields = $this->model->sortableFields;
        }

        // Parse sort with whitelist validation
        $parsedSorts = FilterParser::parseSort($sort, $sortableFields);

        // Apply only validated fields
        foreach ($parsedSorts as [$field, $direction]) {
            $this->model->orderBy($field, $direction);
        }

        return $this;
    }

    /**
     * Apply full-text search to the query
     *
     * @param string $query Search query
     * @return self
     */
    public function search(string $query): self
    {
        $this->searchQuery = $query;

        // Get searchable fields from model if available
        $searchableFields = [];

        if (property_exists($this->model, 'searchableFields')) {
            $searchableFields = $this->model->searchableFields;
        }

        if (empty($searchableFields)) {
            return $this;
        }

        // Use FULLTEXT search if enabled
        $useFulltext = env('SEARCH_ENABLED', 'true') === 'true';

        SearchQueryApplier::apply($this->model, $query, $searchableFields, $useFulltext);

        return $this;
    }

    /**
     * Paginate results
     *
     * @param int $page Current page number
     * @param int $limit Items per page
     * @return array Returns ['data' => [], 'total' => 0, 'page' => 1, 'perPage' => 20, 'lastPage' => 1]
     */
    public function paginate(int $page = 1, int $limit = 20): array
    {
        // Enforce limits
        $defaultLimit = (int) env('PAGINATION_DEFAULT_LIMIT', 20);
        $maxLimit = (int) env('PAGINATION_MAX_LIMIT', 100);

        $limit = min($limit > 0 ? $limit : $defaultLimit, $maxLimit);
        $page = max($page, 1);

        // Get total count before pagination
        $total = (int) $this->model->countAllResults(false);

        // Calculate pagination
        $offset = ($page - 1) * $limit;
        $lastPage = $total > 0 ? (int) ceil($total / $limit) : 1;

        // Get paginated data
        $data = $this->model->findAll($limit, $offset);

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $limit,
            'lastPage' => $lastPage,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $limit, $total),
        ];
    }

    /**
     * Get all results without pagination
     *
     * @return array
     */
    public function get(): array
    {
        return $this->model->findAll();
    }

    /**
     * Get first result
     *
     * @return object|array|null
     */
    public function first()
    {
        return $this->model->first();
    }

    /**
     * Get count of results
     *
     * @return int
     */
    public function count(): int
    {
        return $this->model->countAllResults();
    }
}
