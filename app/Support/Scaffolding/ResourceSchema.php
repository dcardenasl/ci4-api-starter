<?php

declare(strict_types=1);

namespace App\Support\Scaffolding;

/**
 * ResourceSchema
 * Aggregates all fields and metadata for the resource to be scaffolded.
 */
readonly class ResourceSchema
{
    /**
     * @param Field[] $fields
     */
    public function __construct(
        public string $resource,
        public string $domain,
        public string $route,
        public array $fields,
        public bool $softDelete = true,
        public bool $publicRead = true,
        public bool $adminWrite = true
    ) {
    }

    public function getResourceLower(): string
    {
        return lcfirst($this->resource);
    }

    public function getResourcePlural(): string
    {
        // Simple pluralization logic for the schema
        if (preg_match('/y$/i', $this->resource)) {
            return preg_replace('/y$/i', 'ies', $this->resource) ?? ($this->resource . 's');
        }
        if (preg_match('/(s|x|z|ch|sh)$/i', $this->resource)) {
            return $this->resource . 'es';
        }

        return $this->resource . 's';
    }

    public function getResourcePluralLower(): string
    {
        return lcfirst($this->getResourcePlural());
    }

    public function getResourcePluralKebab(): string
    {
        return $this->toKebab($this->getResourcePlural());
    }

    public function toKebab(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $value));
    }
}
