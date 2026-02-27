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
    public int $perPage;
    public ?string $search;
    public ?string $entityType;
    public ?int $entityId;
    public ?int $userId;

    protected function rules(): array
    {
        return [
            'page'       => 'permit_empty|is_natural_no_zero',
            'perPage'    => 'permit_empty|is_natural_no_zero|less_than[101]',
            'search'     => 'permit_empty|string|max_length[100]',
            'entityType' => 'permit_empty|string|max_length[50]',
            'entityId'   => 'permit_empty|is_natural_no_zero',
            'userId'     => 'permit_empty|is_natural_no_zero',
        ];
    }

    protected function map(array $data): void
    {
        $this->page = isset($data['page']) ? (int) $data['page'] : 1;
        $this->perPage = isset($data['perPage']) ? (int) $data['perPage'] : 20;
        $this->search = $data['search'] ?? null;
        $this->entityType = $data['entityType'] ?? null;
        $this->entityId = isset($data['entityId']) ? (int) $data['entityId'] : null;
        $this->userId = isset($data['userId']) ? (int) $data['userId'] : null;
    }

    public function toArray(): array
    {
        $data = [
            'page'    => $this->page,
            'perPage' => $this->perPage,
            'search'  => $this->search,
        ];

        if ($this->entityType) {
            $data['filter']['entity_type'] = ['eq' => $this->entityType];
        }
        if ($this->entityId) {
            $data['filter']['entity_id']   = ['eq' => $this->entityId];
        }
        if ($this->userId) {
            $data['filter']['user_id']     = ['eq' => $this->userId];
        }

        return $data;
    }
}
