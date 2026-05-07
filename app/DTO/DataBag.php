<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Lightweight array-to-object adapter.
 *
 * Used to feed assembled data into a ResponseMapperInterface, which expects an
 * object with a `toArray()` method. Useful when traits/decorators need to merge
 * extra fields into an entity's representation before mapping to a Response DTO
 * without mutating the entity itself.
 */
final class DataBag
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private readonly array $data)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
