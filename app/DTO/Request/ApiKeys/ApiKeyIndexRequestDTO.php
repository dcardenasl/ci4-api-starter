<?php

declare(strict_types=1);

namespace App\DTO\Request\ApiKeys;

use App\DTO\Request\BaseRequestDTO;

/**
 * Api Key Index Request DTO
 */
readonly class ApiKeyIndexRequestDTO extends BaseRequestDTO
{
    public int $page;
    public int $per_page;
    public ?string $search;
    public ?int $is_active;

    protected function rules(): array
    {
        return [
            'page'      => 'permit_empty|is_natural_no_zero',
            'per_page'   => 'permit_empty|is_natural_no_zero|less_than[101]',
            'search'    => 'permit_empty|string|max_length[100]',
            'is_active'  => 'permit_empty|in_list[0,1]',
        ];
    }

    protected function map(array $data): void
    {
        $this->page = isset($data['page']) ? (int) $data['page'] : 1;
        $this->per_page = isset($data['per_page']) ? (int) $data['per_page'] : 20;
        $this->search = $data['search'] ?? null;
        $this->is_active = isset($data['is_active']) ? (int) $data['is_active'] : null;
    }

    public function toArray(): array
    {
        $data = [
            'page'    => $this->page,
            'per_page' => $this->per_page,
            'search'  => $this->search,
        ];

        if ($this->is_active !== null) {
            $data['filter']['is_active'] = ['eq' => $this->is_active];
        }

        return $data;
    }
}
