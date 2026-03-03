<?php

declare(strict_types=1);

namespace App\DTO\Request\Catalog;

use App\DTO\Request\BaseRequestDTO;

readonly class DemoproductIndexRequestDTO extends BaseRequestDTO
{
    public int $page;
    public int $per_page;
    public ?string $search;

    protected function rules(): array
    {
        return [
            'page'     => 'permit_empty|is_natural_no_zero',
            'per_page'  => 'permit_empty|is_natural_no_zero|less_than[101]',
            'per_page' => 'permit_empty|is_natural_no_zero|less_than[101]',
            'search'   => 'permit_empty|string|max_length[100]',
        ];
    }

    protected function map(array $data): void
    {
        $this->page = isset($data['page']) ? (int) $data['page'] : 1;
        $this->per_page = isset($data['per_page'])
            ? (int) $data['per_page']
            : (isset($data['per_page']) ? (int) $data['per_page'] : 20);
        $this->search = $data['search'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'per_page' => $this->per_page,
            'search' => $this->search,
        ];
    }
}
