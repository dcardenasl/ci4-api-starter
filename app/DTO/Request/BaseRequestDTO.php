<?php

declare(strict_types=1);

namespace App\DTO\Request;

use App\Exceptions\ValidationException;
use App\Interfaces\DataTransferObjectInterface;

/**
 * Base Request DTO
 *
 * Provides self-validation capabilities for all incoming request data.
 */
abstract readonly class BaseRequestDTO implements DataTransferObjectInterface
{
    /**
     * @throws ValidationException If validation fails
     */
    public function __construct(array $data)
    {
        $enrichedData = $this->enrichWithContext($data);
        $this->assertNoSnakeCaseKeys($enrichedData);
        $this->validate($enrichedData);
        $this->map($enrichedData);
    }

    /**
     * Enrich input data with values from global ContextHolder if missing
     */
    private function enrichWithContext(array $data): array
    {
        $context = \App\Libraries\ContextHolder::get();

        // Fallback to ApiRequest if ContextHolder is empty (e.g. if filter hasn't run or was bypassed)
        if ($context === null) {
            $request = \Config\Services::request();
            if ($request instanceof \App\HTTP\ApiRequest) {
                $userId = $request->getAuthUserId();
                $role = $request->getAuthUserRole();
                if ($userId !== null) {
                    $context = new \App\DTO\SecurityContext($userId, $role);
                    \App\Libraries\ContextHolder::set($context);
                }
            }
        }

        if ($context === null) {
            return $data;
        }

        if (!isset($data['userId']) && $context->userId !== null) {
            $data['userId'] = $context->userId;
        }

        if (!isset($data['userRole']) && $context->role !== null) {
            $data['userRole'] = $context->role;
        }

        return $data;
    }

    /**
     * Define validation rules for this DTO
     */
    abstract protected function rules(): array;

    /**
     * Define custom validation messages (optional)
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Map data to DTO properties
     */
    abstract protected function map(array $data): void;

    /**
     * Perform validation against the defined rules
     */
    protected function validate(array $data): void
    {
        $validation = \Config\Services::validation();
        $validation->reset(); // Critical for Singleton usage in tests

        $rules = $this->rules();
        if (empty($rules)) {
            return;
        }

        if (!$validation->setRules($rules, $this->messages())->run($data)) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $validation->getErrors()
            );
        }
    }

    /**
     * Reject snake_case keys in incoming request payloads.
     */
    private function assertNoSnakeCaseKeys(array $data, string $path = ''): void
    {
        $errors = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && str_contains($key, '_')) {
                $fullKey = $path === '' ? $key : $path . '.' . $key;
                $errors[$fullKey] = 'Use camelCase for request fields.';
            }

            if (is_array($value)) {
                $nestedPath = $path === '' ? (string) $key : $path . '.' . $key;
                $this->assertNoSnakeCaseKeys($value, $nestedPath);
            }
        }

        if ($errors !== []) {
            throw new ValidationException(lang('Api.validationFailed'), $errors);
        }
    }
}
