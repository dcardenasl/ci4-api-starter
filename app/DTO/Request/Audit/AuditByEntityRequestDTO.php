<?php

declare(strict_types=1);

namespace App\DTO\Request\Audit;

use App\DTO\Request\BaseRequestDTO;

/**
 * Audit By Entity Request DTO
 */
readonly class AuditByEntityRequestDTO extends BaseRequestDTO
{
    public string $entityType;
    public int $entityId;

    protected function rules(): array
    {
        return [
            'entity_type' => 'required|string|max_length[100]',
            'entity_id' => 'required|integer|greater_than[0]',
        ];
    }

    protected function map(array $data): void
    {
        $this->entityType = (string) ($data['entity_type'] ?? '');
        $this->entityId = (int) ($data['entity_id'] ?? 0);
    }

    public function toArray(): array
    {
        return [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
        ];
    }
}
