<?php

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
    protected array $filters = [];
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
        $this->filters = FilterParser::parse($filters);

        foreach ($this->filters as $field => $condition) {
            [$operator, $value] = $condition;

            switch ($operator) {
                case '=':
                    $this->model->where($field, $value);
                    break;
                case '!=':
                    $this->model->where($field . ' !=', $value);
                    break;
                case '>':
                    $this->model->where($field . ' >', $value);
                    break;
                case '<':
                    $this->model->where($field . ' <', $value);
                    break;
                case '>=':
                    $this->model->where($field . ' >=', $value);
                    break;
                case '<=':
                    $this->model->where($field . ' <=', $value);
                    break;
                case 'LIKE':
                    $this->model->like($field, $value);
                    break;
                case 'IN':
                    $this->model->whereIn($field, $value);
                    break;
                case 'NOT IN':
                    $this->model->whereNotIn($field, $value);
                    break;
                case 'BETWEEN':
                    if (is_array($value) && count($value) === 2) {
                        $this->model->where($field . ' >=', $value[0]);
                        $this->model->where($field . ' <=', $value[1]);
                    }
                    break;
                case 'IS NULL':
                    $this->model->where($field, null);
                    break;
                case 'IS NOT NULL':
                    $this->model->where($field . ' !=', null);
                    break;
            }
        }

        return $this;
    }

    /**
     * Apply sorting to the query
     *
     * @param string $sort Sort string in format: "-created_at,username" (- prefix for DESC)
     * @return self
     */
    public function sort(string $sort): self
    {
        $sortFields = explode(',', $sort);

        foreach ($sortFields as $field) {
            $field = trim($field);
            $direction = 'ASC';

            if (str_starts_with($field, '-')) {
                $direction = 'DESC';
                $field = substr($field, 1);
            }

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

        // Check minimum search length
        $minLength = (int) env('SEARCH_MIN_LENGTH', 3);
        if (strlen($query) < $minLength) {
            return $this;
        }

        // Use FULLTEXT search if enabled and index exists
        if (env('SEARCH_ENABLED', 'true') === 'true' && count($searchableFields) > 0) {
            // Try FULLTEXT search first
            $fields = implode(', ', $searchableFields);
            $this->model->where("MATCH($fields) AGAINST(? IN BOOLEAN MODE)", [$query]);
        } else {
            // Fallback to LIKE search
            $this->model->groupStart();
            foreach ($searchableFields as $index => $field) {
                if ($index === 0) {
                    $this->model->like($field, $query);
                } else {
                    $this->model->orLike($field, $query);
                }
            }
            $this->model->groupEnd();
        }

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
