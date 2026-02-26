<?php

declare(strict_types=1);

namespace App\DTO\Request\Users;

use App\Interfaces\DataTransferObjectInterface;

/**
 * User Index Request DTO
 *
 * Handles filtering, sorting, and pagination for user listing.
 */
readonly class UserIndexRequestDTO implements DataTransferObjectInterface
{
    public int $page;
    public int $perPage;
    public ?string $search;
    public ?string $role;
    public ?string $status;
    public string $orderBy;
    public string $orderDir;

    public function __construct(array $data)
    {
        $this->page = isset($data['page']) ? (int) $data['page'] : 1;
        $this->perPage = isset($data['per_page']) ? (int) $data['per_page'] : 20;
        $this->search = $data['search'] ?? null;

        // Handle both flat and nested filter structure (legacy support for QueryBuilder)
        $this->role = $data['role'] ?? $data['filter']['role']['eq'] ?? $data['filter']['role'] ?? null;
        $this->status = $data['status'] ?? $data['filter']['status']['eq'] ?? $data['filter']['status'] ?? null;

        $this->orderBy = $data['order_by'] ?? 'id';
        $this->orderDir = strtoupper($data['order_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
    }

    public function toArray(): array
    {
        $data = [
            'page' => $this->page,
            'per_page' => $this->perPage,
            'search' => $this->search,
            'order_by' => $this->orderBy,
            'order_dir' => $this->orderDir,
        ];

        // Map specific properties to the filter structure for QueryBuilder compatibility
        if ($this->role) {
            $data['filter']['role'] = ['eq' => $this->role];
        }
        if ($this->status) {
            $data['filter']['status'] = ['eq' => $this->status];
        }

        return $data;
    }
}
