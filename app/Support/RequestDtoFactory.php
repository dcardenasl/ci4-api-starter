<?php

declare(strict_types=1);

namespace App\Support;

use App\DTO\Request\BaseRequestDTO;
use App\Exceptions\ValidationException;
use CodeIgniter\Validation\ValidationInterface;

/**
 * Central factory for constructing request DTOs with proper validation.
 * Orchestrates data validation before DTO instantiation to ensure objects are always valid.
 */
class RequestDtoFactory
{
    public function __construct(
        protected ValidationInterface $validation
    ) {
    }

    /**
     * @template T of BaseRequestDTO
     * @param class-string<T> $dtoClass
     * @return T
     */
    public function make(string $dtoClass, array $data): BaseRequestDTO
    {
        if (!is_subclass_of($dtoClass, BaseRequestDTO::class)) {
            throw new \InvalidArgumentException("{$dtoClass} must extend " . BaseRequestDTO::class);
        }

        // 1. Resolve validation rules from DTO metadata
        $this->validateData($dtoClass, $data);

        // 2. Instantiate the pure data object
        return new $dtoClass($data);
    }

    /**
     * Validate data against DTO rules before instantiation.
     */
    protected function validateData(string $dtoClass, array $data): void
    {
        // We use a reflection or a temporary instance to get the rules.
        // For simplicity in this architecture, we'll use a temporary instance
        // or a static method if we were to refactor further.
        // But the most compatible way now is to let the DTO define its own rules.

        $this->validation->reset();

        /** @var BaseRequestDTO $dummy */
        $dummy = new $dtoClass([]); // Initial data is empty, we only need the rules.

        $rules = $dummy->rules();
        if (empty($rules)) {
            return;
        }

        if (!$this->validation->setRules($rules, $dummy->messages())->run($data)) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $this->validation->getErrors()
            );
        }
    }
}
