<?php

declare(strict_types=1);

namespace App\Traits;

use App\Exceptions\BadRequestException;

/**
 * ValidatesRequiredFields Trait
 *
 * Provides validation methods for common required field patterns across services.
 * Reduces code duplication and standardizes validation error messages.
 */
trait ValidatesRequiredFields
{
    /**
     * Validate and extract required ID from data array
     *
     * @param array $data Request data
     * @return int Validated ID
     * @throws BadRequestException If ID is missing or empty
     */
    protected function validateRequiredId(array $data): int
    {
        if (!isset($data['id']) || $data['id'] === null || $data['id'] === '') {
            throw new BadRequestException(
                'Invalid request',
                ['id' => lang('Users.idRequired')]
            );
        }

        return (int) $data['id'];
    }

    /**
     * Validate and extract required field from data array
     *
     * @param array $data Request data
     * @param string $field Field name to validate
     * @param string|null $langKey Optional language key for error message
     * @return mixed Validated field value
     * @throws BadRequestException If field is missing or empty
     */
    protected function validateRequiredField(array $data, string $field, ?string $langKey = null): mixed
    {
        if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
            $errorMessage = $langKey ? lang($langKey) : "{$field} is required";

            throw new BadRequestException(
                'Invalid request',
                [$field => $errorMessage]
            );
        }

        return $data[$field];
    }
}
