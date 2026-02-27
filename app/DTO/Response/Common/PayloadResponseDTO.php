<?php

declare(strict_types=1);

namespace App\DTO\Response\Common;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Generic payload response DTO
 */
readonly class PayloadResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        public array $payload
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(payload: $payload);
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}
