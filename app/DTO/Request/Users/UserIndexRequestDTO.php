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
    public int $per_page;
    public ?string $search;
    public ?string $role;
    public ?string $status;
    public string $order_by;
    public string $order_dir;

    protected function rules(): array
    {
        return [
            'page'     => 'permit_empty|is_natural_no_zero',
            'per_page'  => 'permit_empty|is_natural_no_zero|less_than[101]',
            'search'   => 'permit_empty|string|max_length[100]',
            'role'     => 'permit_empty|in_list[user,admin,superadmin]',
            'status'   => 'permit_empty|in_list[active,inactive,pending_approval,invited]',
            'order_by'  => 'permit_empty|in_list[id,email,created_at,role,status,first_name,last_name]',
            'order_dir' => 'permit_empty|in_list[ASC,DESC,asc,desc]',
        ];
    }

    protected function map(array $data): void
    {
        // Define default values if not provided
        $this->page = isset($data['page']) ? (int) $data['page'] : 1;
        $this->per_page = isset($data['per_page']) ? (int) $data['per_page'] : 20;
        $this->search = $data['search'] ?? null;

        $this->role = $data['role'] ?? null;
        $this->status = $data['status'] ?? null;

        $this->order_by = $data['order_by'] ?? 'id';
        $this->order_dir = strtoupper($data['order_dir'] ?? 'DESC');
    }

    public function toArray(): array
    {
        $data = [
            'page'     => $this->page,
            'per_page'  => $this->per_page,
            'search'   => $this->search,
            'order_by'  => $this->order_by,
            'order_dir' => $this->order_dir,
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
