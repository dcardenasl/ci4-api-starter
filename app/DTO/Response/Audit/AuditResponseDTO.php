<?php

declare(strict_types=1);

namespace App\DTO\Response\Audit;

use App\Interfaces\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Audit Response DTO
 *
 * Detailed view of an audit log entry.
 */
#[OA\Schema(
    schema: 'AuditResponse',
    title: 'Audit Response',
    description: 'Audit log entry metadata',
    required: ['id', 'action', 'entityType', 'createdAt']
)]
readonly class AuditResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Unique log identifier', example: 10)]
        public int $id,
        #[OA\Property(description: 'Action performed', example: 'update', enum: ['create', 'update', 'delete', 'login_success', 'login_failure'])]
        public string $action,
        #[OA\Property(property: 'entityType', description: 'Affected entity type', example: 'users')]
        public string $entityType,
        #[OA\Property(property: 'entityId', description: 'Affected entity ID', example: 5, nullable: true)]
        public ?int $entityId,
        #[OA\Property(property: 'oldValues', description: 'Values before the change', type: 'object', nullable: true)]
        public array $oldValues,
        #[OA\Property(property: 'newValues', description: 'Values after the change', type: 'object', nullable: true)]
        public array $newValues,
        #[OA\Property(property: 'userId', description: 'ID of user who performed the action', example: 1, nullable: true)]
        public ?int $userId,
        #[OA\Property(property: 'userEmail', description: 'Email of user who performed the action', example: 'admin@example.com', nullable: true)]
        public ?string $userEmail,
        #[OA\Property(property: 'ipAddress', description: 'IP address of the requester', example: '127.0.0.1', nullable: true)]
        public ?string $ipAddress,
        #[OA\Property(property: 'userAgent', description: 'User-Agent of the requester', example: 'Mozilla/5.0...', nullable: true)]
        public ?string $userAgent,
        #[OA\Property(property: 'createdAt', description: 'Log timestamp', example: '2026-02-26 12:00:00')]
        public string $createdAt
    ) {
    }

    public static function fromArray(array $data): self
    {
        $createdAt = $data['created_at'] ?? null;
        if ($createdAt instanceof \DateTimeInterface) {
            $createdAt = $createdAt->format('Y-m-d H:i:s');
        }

        return new self(
            id: (int) ($data['id'] ?? 0),
            action: (string) ($data['action'] ?? ''),
            entityType: (string) ($data['entity_type'] ?? ''),
            entityId: isset($data['entity_id']) ? (int) $data['entity_id'] : null,
            oldValues: is_string($data['old_values'] ?? null) ? json_decode($data['old_values'], true) : ($data['old_values'] ?? []),
            newValues: is_string($data['new_values'] ?? null) ? json_decode($data['new_values'], true) : ($data['new_values'] ?? []),
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            userEmail: $data['user_email'] ?? null,
            ipAddress: $data['ip_address'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            createdAt: (string) $createdAt
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'entityType' => $this->entityType,
            'entityId' => $this->entityId,
            'oldValues' => $this->oldValues,
            'newValues' => $this->newValues,
            'userId' => $this->userId,
            'userEmail' => $this->userEmail,
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
            'createdAt' => $this->createdAt,
        ];
    }
}
