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
        $this->validate($enrichedData);
        $this->map($enrichedData);
    }

    /**
     * Enrich input data with values from global ContextHolder if missing
     */
    private function enrichWithContext(array $data): array
    {
        $context = \App\Libraries\ContextHolder::get();
        if ($context === null) {
            return $data;
        }

        if (!isset($data['user_id']) && $context->userId !== null) {
            $data['user_id'] = $context->userId;
        }

        if (!isset($data['user_role']) && $context->role !== null) {
            $data['user_role'] = $context->role;
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
}
