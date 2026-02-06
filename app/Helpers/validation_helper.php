<?php

declare(strict_types=1);

/**
 * Validation Helper Functions
 *
 * Provides convenient shorthand functions for common validation operations.
 */

use App\Exceptions\ValidationException;
use App\Interfaces\InputValidationServiceInterface;

if (!function_exists('validateInputs')) {
    /**
     * Validate input data against rules
     *
     * @param array $data     Data to validate
     * @param array $rules    Validation rules
     * @param array $messages Custom error messages
     * @return array Empty array if valid, errors array if invalid
     */
    function validateInputs(array $data, array $rules, array $messages = []): array
    {
        return service('inputValidationService')->validate($data, $rules, $messages);
    }
}

if (!function_exists('validateOrFail')) {
    /**
     * Validate input data using domain/action rules, throw exception on failure
     *
     * @param array  $data   Data to validate
     * @param string $domain The domain name (e.g., 'auth', 'user')
     * @param string $action The action name (e.g., 'login', 'register')
     * @return void
     * @throws ValidationException If validation fails
     */
    function validateOrFail(array $data, string $domain, string $action): void
    {
        service('inputValidationService')->validateOrFail($data, $domain, $action);
    }
}

if (!function_exists('getValidationRules')) {
    /**
     * Get validation rules and messages for a domain/action
     *
     * @param string $domain The domain name
     * @param string $action The action name
     * @return array{rules: array, messages: array}
     */
    function getValidationRules(string $domain, string $action): array
    {
        return service('inputValidationService')->get($domain, $action);
    }
}

if (!function_exists('inputValidationService')) {
    /**
     * Get the InputValidationService instance
     *
     * @return InputValidationServiceInterface
     */
    function inputValidationService(): InputValidationServiceInterface
    {
        return service('inputValidationService');
    }
}
