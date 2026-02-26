<?php

declare(strict_types=1);

namespace App\DTO\Request\Audit;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Audit Index Request DTO
 *
 * Filters for searching audit logs.
 */
readonly class AuditIndexRequestDTO implements DataTransferObjectInterface
{
    public int $page;
    public int $perPage;
    public ?string $action;
    public ?string $entityType;
    public ?int $entityId;
    public ?int $userId;
    public ?string $fromDate;
    public ?string $toDate;

    public function __construct(array $data)
    {
        $this->page = isset($data['page']) ? (int) $data['page'] : 1;
        $this->perPage = isset($data['per_page']) ? (int) $data['per_page'] : 20;
        $this->action = $data['action'] ?? $data['filter']['action'] ?? null;
        $this->entityType = $data['entity_type'] ?? $data['filter']['entity_type'] ?? null;
        $this->entityId = isset($data['entity_id']) ? (int) $data['entity_id'] : (isset($data['filter']['entity_id']) ? (int)$data['filter']['entity_id'] : null);
        $this->userId = isset($data['user_id']) ? (int) $data['user_id'] : (isset($data['filter']['user_id']) ? (int)$data['filter']['user_id'] : null);
        $this->fromDate = $data['from_date'] ?? $data['filter']['from_date'] ?? null;
        $this->toDate = $data['to_date'] ?? $data['filter']['to_date'] ?? null;
    }

    public function toArray(): array
    {
        $data = [
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];

        if ($this->action) {
            $data['filter']['action'] = $this->action;
        }
        if ($this->entityType) {
            $data['filter']['entity_type'] = $this->entityType;
        }
        if ($this->entityId) {
            $data['filter']['entity_id'] = $this->entityId;
        }
        if ($this->userId) {
            $data['filter']['user_id'] = $this->userId;
        }
        if ($this->fromDate) {
            $data['filter']['created_at']['gte'] = $this->fromDate;
        }
        if ($this->toDate) {
            $data['filter']['created_at']['lte'] = $this->toDate;
        }

        return $data;
    }
}
