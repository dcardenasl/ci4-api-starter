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
            'entityType' => 'required|string|max_length[100]',
            'entityId' => 'required|integer|greater_than[0]',
        ];
    }

    protected function map(array $data): void
    {
        $this->entityType = (string) ($data['entityType'] ?? '');
        $this->entityId = (int) ($data['entityId'] ?? 0);
    }

    public function toArray(): array
    {
        return [
            'entityType' => $this->entityType,
            'entityId' => $this->entityId,
        ];
    }
}
