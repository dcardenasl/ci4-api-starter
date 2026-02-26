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
    public int $perPage;
    public ?string $search;
    public ?int $isActive;

    protected function rules(): array
    {
        return [
            'page'      => 'permit_empty|is_natural_no_zero',
            'per_page'  => 'permit_empty|is_natural_no_zero|less_than[101]',
            'search'    => 'permit_empty|string|max_length[100]',
            'is_active' => 'permit_empty|in_list[0,1]',
        ];
    }

    protected function map(array $data): void
    {
        $this->page = isset($data['page']) ? (int) $data['page'] : 1;
        $this->perPage = isset($data['per_page']) ? (int) $data['per_page'] : 20;
        $this->search = $data['search'] ?? null;
        $this->isActive = isset($data['is_active']) ? (int) $data['is_active'] : null;
    }

    public function toArray(): array
    {
        $data = [
            'page'     => $this->page,
            'per_page' => $this->perPage,
            'search'   => $this->search,
        ];

        if ($this->isActive !== null) {
            $data['filter']['is_active'] = ['eq' => $this->isActive];
        }

        return $data;
    }
}
