<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Input Validation Service Interface
 *
 * Defines the contract for centralized input validation.
 */
interface InputValidationServiceInterface
{
    /**
     * Validate data against rules and return errors
     *
     * @param array $data    Data to validate
     * @param array $rules   Validation rules
     * @param array $messages Custom error messages
     * @return array Empty array if valid, error messages array if invalid
     */
    public function validate(array $data, array $rules, array $messages = []): array;

    /**
     * Get validation rules for a domain/action combination
     *
     * @param string $domain The domain name (e.g., 'auth', 'user')
     * @param string $action The action name (e.g., 'login', 'register')
     * @return array Validation rules array
     */
    public function getRules(string $domain, string $action): array;

    /**
     * Get custom error messages for a domain/action combination
     *
     * @param string $domain The domain name
     * @param string $action The action name
     * @return array Custom error messages array
     */
    public function getMessages(string $domain, string $action): array;

    /**
     * Validate data using domain/action rules, throw exception on failure
     *
     * @param array  $data   Data to validate
     * @param string $domain The domain name
     * @param string $action The action name
     * @return void
     * @throws \App\Exceptions\ValidationException If validation fails
     */
    public function validateOrFail(array $data, string $domain, string $action): void;

    /**
     * Get both rules and messages for a domain/action
     *
     * @param string $domain The domain name
     * @param string $action The action name
     * @return array{rules: array, messages: array}
     */
    public function get(string $domain, string $action): array;
}
