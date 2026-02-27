<?php

declare(strict_types=1);

namespace App\DTO\Response\Common;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Generic Paginated Response DTO
 */
readonly class PaginatedResponseDTO implements DataTransferObjectInterface
{
    /**
     * @param array<int, mixed> $data
     */
    public function __construct(
        public array $data,
        public int $total,
        public int $page,
        public int $perPage
    ) {
    }

    /**
     * @param array{data?: array<int, mixed>, total?: int, page?: int, perPage?: int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            data: $data['data'] ?? [],
            total: (int) ($data['total'] ?? 0),
            page: (int) ($data['page'] ?? 1),
            perPage: (int) ($data['perPage'] ?? 10)
        );
    }

    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'total' => $this->total,
            'page' => $this->page,
            'perPage' => $this->perPage,
        ];
    }
}
