<?php

declare(strict_types=1);

namespace App\DTO\Request;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Base Request DTO
 *
 * Provides a standardized structure for all incoming request data.
 * All properties are readonly, ensuring immutability once instantiated.
 */
abstract readonly class BaseRequestDTO implements DataTransferObjectInterface
{
    public function __construct(array $data)
    {
        $this->map($data);
    }

    /**
     * Define validation rules for this DTO.
     * Used by RequestDtoFactory to validate data BEFORE instantiation.
     */
    abstract public function rules(): array;

    /**
     * Define custom validation messages (optional)
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Map data to DTO properties
     */
    abstract protected function map(array $data): void;
}
