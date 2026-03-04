<?php

declare(strict_types=1);

namespace App\Support;

use App\DTO\Request\BaseRequestDTO;
use CodeIgniter\Validation\ValidationInterface;

/**
 * Central factory for constructing request DTOs with proper validation dependencies.
 */
class RequestDtoFactory
{
    public function __construct(
        protected ValidationInterface $validation
    ) {
    }

    public function make(string $dtoClass, array $data): BaseRequestDTO
    {
        if (!is_subclass_of($dtoClass, BaseRequestDTO::class)) {
            throw new \InvalidArgumentException("{$dtoClass} must extend " . BaseRequestDTO::class);
        }

        return new $dtoClass($data, $this->validation);
    }
}
