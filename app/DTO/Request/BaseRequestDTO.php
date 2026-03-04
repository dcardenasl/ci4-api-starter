<?php

declare(strict_types=1);

namespace App\DTO\Request;

use App\Exceptions\ValidationException;
use App\Interfaces\DataTransferObjectInterface;
use CodeIgniter\Validation\ValidationInterface;

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
    protected ValidationInterface $validation;

    public function __construct(array $data, ValidationInterface $validation)
    {
        $this->validation = $validation;
        $this->validate($data);
        $this->map($data);
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
        /** @var \CodeIgniter\Validation\ValidationInterface $validation */
        $this->validation->reset(); // Critical for Singleton usage in tests

        $rules = $this->rules();
        if (empty($rules)) {
            return;
        }

        if (!$this->validation->setRules($rules, $this->messages())->run($data)) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $this->validation->getErrors()
            );
        }
    }
}
