<?php

declare(strict_types=1);

namespace App\DTO\Request\Audit;

use App\DTO\Request\BaseRequestDTO;

/**
 * Audit Index Request DTO
 *
 * Validates filters and pagination for audit logs.
 */
readonly class AuditIndexRequestDTO extends BaseRequestDTO
{
    public int $page;
    public int $per_page;
    public ?string $search;
    public ?string $entity_type;
    public ?int $entity_id;
    public ?int $user_id;

    protected function rules(): array
    {
        return [
            'page'       => 'permit_empty|is_natural_no_zero',
            'per_page'    => 'permit_empty|is_natural_no_zero|less_than[101]',
            'search'     => 'permit_empty|string|max_length[100]',
            'entity_type' => 'permit_empty|string|max_length[50]',
            'entity_id'   => 'permit_empty|is_natural_no_zero',
            'user_id'     => 'permit_empty|is_natural_no_zero',
        ];
    }

    protected function map(array $data): void
    {
        $this->page = isset($data['page']) ? (int) $data['page'] : 1;
        $this->per_page = isset($data['per_page']) ? (int) $data['per_page'] : 20;
        $this->search = $data['search'] ?? null;
        $this->entity_type = $data['entity_type'] ?? null;
        $this->entity_id = isset($data['entity_id']) ? (int) $data['entity_id'] : null;
        $this->user_id = isset($data['user_id']) ? (int) $data['user_id'] : null;
    }

    public function toArray(): array
    {
        $data = [
            'page'    => $this->page,
            'per_page' => $this->per_page,
            'search'  => $this->search,
        ];

        if ($this->entity_type) {
            $data['filter']['entity_type'] = ['eq' => $this->entity_type];
        }
        if ($this->entity_id) {
            $data['filter']['entity_id']   = ['eq' => $this->entity_id];
        }
        if ($this->user_id) {
            $data['filter']['user_id']     = ['eq' => $this->user_id];
        }

        return $data;
    }
}
