<?php

declare(strict_types=1);

namespace App\Validations;

/**
 * Base Validation Class
 *
 * Abstract class providing common functionality for domain validation classes.
 * Each domain validation class (AuthValidation, UserValidation, etc.) extends this.
 */
abstract class BaseValidation
{
    /**
     * Get validation rules for a specific action
     *
     * @param string $action The action name (e.g., 'login', 'register')
     * @return array<string, string|array>
     */
    abstract public function getRules(string $action): array;

    /**
     * Get custom error messages for a specific action
     *
     * @param string $action The action name
     * @return array<string, string>
     */
    abstract public function getMessages(string $action): array;

    /**
     * Get both rules and messages for a specific action
     *
     * @param string $action The action name
     * @return array{rules: array, messages: array}
     */
    public function get(string $action): array
    {
        return [
            'rules'    => $this->getRules($action),
            'messages' => $this->getMessages($action),
        ];
    }

    /**
     * Check if an action exists in this validation class
     *
     * @param string $action The action name
     * @return bool
     */
    public function hasAction(string $action): bool
    {
        return !empty($this->getRules($action));
    }

    /**
     * Common pagination rules
     *
     * @return array<string, string>
     */
    protected function paginationRules(): array
    {
        return [
            'page'     => 'permit_empty|is_natural_no_zero',
            'per_page' => 'permit_empty|is_natural_no_zero|less_than_equal_to[100]',
            'sort_by'  => 'permit_empty|alpha_dash',
            'sort_dir' => 'permit_empty|in_list[asc,desc,ASC,DESC]',
            'search'   => 'permit_empty|string|max_length[255]',
        ];
    }

    /**
     * Common pagination error messages
     *
     * @return array<string, string>
     */
    protected function paginationMessages(): array
    {
        return [
            'page.is_natural_no_zero'     => lang('InputValidation.common.pageMustBePositive'),
            'per_page.is_natural_no_zero' => lang('InputValidation.common.perPageMustBePositive'),
            'per_page.less_than_equal_to' => lang('InputValidation.common.perPageExceedsMax'),
            'sort_by.alpha_dash'          => lang('InputValidation.common.sortFieldInvalid'),
            'sort_dir.in_list'            => lang('InputValidation.common.sortDirInvalid'),
            'search.max_length'           => lang('InputValidation.common.searchTooLong'),
        ];
    }

    /**
     * Common ID validation rules
     *
     * @param string $field Field name (default: 'id')
     * @return array<string, string>
     */
    protected function idRules(string $field = 'id'): array
    {
        return [
            $field => 'required|is_natural_no_zero',
        ];
    }

    /**
     * Common ID validation messages
     *
     * @param string $field Field name (default: 'id')
     * @return array<string, string>
     */
    protected function idMessages(string $field = 'id'): array
    {
        $fieldName = ucfirst($field);
        return [
            "{$field}.required"           => lang('InputValidation.common.idRequired', [$fieldName]),
            "{$field}.is_natural_no_zero" => lang('InputValidation.common.idMustBePositive', [$fieldName]),
        ];
    }

    /**
     * Merge multiple rule arrays
     *
     * @param array ...$ruleArrays
     * @return array
     */
    protected function mergeRules(array ...$ruleArrays): array
    {
        return array_merge(...$ruleArrays);
    }

    /**
     * Merge multiple message arrays
     *
     * @param array ...$messageArrays
     * @return array
     */
    protected function mergeMessages(array ...$messageArrays): array
    {
        return array_merge(...$messageArrays);
    }
}
