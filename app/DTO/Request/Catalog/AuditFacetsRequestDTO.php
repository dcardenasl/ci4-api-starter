<?php

declare(strict_types=1);

namespace App\DTO\Request\Catalog;

use App\DTO\Request\BaseRequestDTO;

readonly class AuditFacetsRequestDTO extends BaseRequestDTO
{
    public int $window_days;
    public int $limit;

    protected function rules(): array
    {
        return [
            'window_days' => 'permit_empty|is_natural_no_zero|less_than_equal_to[3650]',
            'limit'       => 'permit_empty|is_natural_no_zero|less_than_equal_to[500]',
        ];
    }

    protected function map(array $data): void
    {
        $this->window_days = isset($data['window_days']) ? (int) $data['window_days'] : 90;
        $this->limit = isset($data['limit']) ? (int) $data['limit'] : 100;
    }

    public function toArray(): array
    {
        return [
            'window_days' => $this->window_days,
            'limit'       => $this->limit,
        ];
    }
}
