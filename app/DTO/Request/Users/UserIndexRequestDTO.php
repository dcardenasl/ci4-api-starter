<?php

declare(strict_types=1);

namespace App\DTO\Request\Users;

use App\DTO\Request\BaseRequestDTO;

/**
 * User Index Request DTO
 *
 * Handles filtering, sorting, and pagination for user listing.
 */
readonly class UserIndexRequestDTO extends BaseRequestDTO
{
    public int $page;
    public int $perPage;
    public ?string $search;
    public ?string $role;
    public ?string $status;
    public string $orderBy;
    public string $orderDir;

    protected function rules(): array
    {
        return [
            'page'     => 'permit_empty|is_natural_no_zero',
            'perPage'  => 'permit_empty|is_natural_no_zero|less_than[101]',
            'search'   => 'permit_empty|string|max_length[100]',
            'role'     => 'permit_empty|in_list[user,admin,superadmin]',
            'status'   => 'permit_empty|in_list[active,inactive,pending_approval,invited]',
            'orderBy'  => 'permit_empty|in_list[id,email,created_at,role,status,first_name,last_name]',
            'orderDir' => 'permit_empty|in_list[ASC,DESC,asc,desc]',
        ];
    }

    protected function map(array $data): void
    {
        // Define default values if not provided
        $this->page = isset($data['page']) ? (int) $data['page'] : 1;
        $this->perPage = isset($data['perPage']) ? (int) $data['perPage'] : 20;
        $this->search = $data['search'] ?? null;

        $this->role = $data['role'] ?? null;
        $this->status = $data['status'] ?? null;

        $this->orderBy = $data['orderBy'] ?? 'id';
        $this->orderDir = strtoupper($data['orderDir'] ?? 'DESC');
    }

    public function toArray(): array
    {
        $data = [
            'page'     => $this->page,
            'perPage'  => $this->perPage,
            'search'   => $this->search,
            'orderBy'  => $this->orderBy,
            'orderDir' => $this->orderDir,
        ];

        if ($this->role) {
            $data['filter']['role'] = ['eq' => $this->role];
        }
        if ($this->status) {
            $data['filter']['status'] = ['eq' => $this->status];
        }

        return $data;
    }
}
