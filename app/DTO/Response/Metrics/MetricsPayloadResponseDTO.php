<?php

declare(strict_types=1);

namespace App\DTO\Response\Metrics;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Metrics Payload Response DTO
 *
 * Wraps arbitrary metrics payloads while preserving original shape.
 */
readonly class MetricsPayloadResponseDTO implements DataTransferObjectInterface
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
