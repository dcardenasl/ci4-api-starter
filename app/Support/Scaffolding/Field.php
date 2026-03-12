<?php

declare(strict_types=1);

namespace App\Support\Scaffolding;

/**
 * Field Value Object
 * Represents a single field in the resource with its metadata.
 */
readonly class Field
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $required = true,
        public bool $nullable = false,
        public ?string $validationRules = null,
        public bool $searchable = false,
        public bool $filterable = false,
        public bool $sortable = false,
        public ?string $fkTable = null,
        public ?string $defaultValue = null,
        public ?int $length = null,
        public ?string $precision = null // For decimals e.g. "10,2"
    ) {
    }
}
