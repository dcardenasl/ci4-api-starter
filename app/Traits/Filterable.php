<?php

namespace App\Traits;

use App\Libraries\Query\FilterParser;

/**
 * Filterable Trait
 *
 * Adds filtering capabilities to models. Models using this trait should
 * define a $filterableFields property listing allowed filter fields.
 */
trait Filterable
{
    /**
     * Apply filters to the query
     *
     * @param array $filters Raw filter array from request
     * @return self
     */
    public function applyFilters(array $filters): self
    {
        // Validate fields against filterable fields
        if (! empty($this->filterableFields)) {
            $filters = FilterParser::filterAllowedFields($filters, $this->filterableFields);
        }

        // Parse filters
        $parsedFilters = FilterParser::parse($filters);

        // Apply each filter
        foreach ($parsedFilters as $field => $condition) {
            [$operator, $value] = $condition;

            switch ($operator) {
                case '=':
                    $this->where($field, $value);
                    break;
                case '!=':
                    $this->where($field . ' !=', $value);
                    break;
                case '>':
                    $this->where($field . ' >', $value);
                    break;
                case '<':
                    $this->where($field . ' <', $value);
                    break;
                case '>=':
                    $this->where($field . ' >=', $value);
                    break;
                case '<=':
                    $this->where($field . ' <=', $value);
                    break;
                case 'LIKE':
                    $this->like($field, $value);
                    break;
                case 'IN':
                    $this->whereIn($field, $value);
                    break;
                case 'NOT IN':
                    $this->whereNotIn($field, $value);
                    break;
                case 'BETWEEN':
                    if (is_array($value) && count($value) === 2) {
                        $this->where($field . ' >=', $value[0]);
                        $this->where($field . ' <=', $value[1]);
                    }
                    break;
                case 'IS NULL':
                    $this->where($field, null);
                    break;
                case 'IS NOT NULL':
                    $this->where($field . ' !=', null);
                    break;
            }
        }

        return $this;
    }

    /**
     * Get filterable fields
     *
     * @return array
     */
    public function getFilterableFields(): array
    {
        return $this->filterableFields;
    }

    /**
     * Check if a field is filterable
     *
     * @param string $field Field name
     * @return bool
     */
    public function isFilterable(string $field): bool
    {
        return in_array($field, $this->filterableFields, true);
    }
}
