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
    required: ['id', 'action', 'entity_type', 'created_at']
)]
readonly class AuditResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Unique log identifier', example: 10)]
        public int $id,
        #[OA\Property(description: 'Action performed', example: 'update', enum: ['create', 'update', 'delete', 'login_success', 'login_failure'])]
        public string $action,
        #[OA\Property(property: 'entity_type', description: 'Affected entity type', example: 'users')]
        public string $entityType,
        #[OA\Property(property: 'entity_id', description: 'Affected entity ID', example: 5, nullable: true)]
        public ?int $entityId,
        #[OA\Property(property: 'old_values', description: 'Values before the change', type: 'object', nullable: true)]
        public array $oldValues,
        #[OA\Property(property: 'new_values', description: 'Values after the change', type: 'object', nullable: true)]
        public array $newValues,
        #[OA\Property(property: 'user_id', description: 'ID of user who performed the action', example: 1, nullable: true)]
        public ?int $userId,
        #[OA\Property(property: 'user_email', description: 'Email of user who performed the action', example: 'admin@example.com', nullable: true)]
        public ?string $userEmail,
        #[OA\Property(property: 'ip_address', description: 'IP address of the requester', example: '127.0.0.1', nullable: true)]
        public ?string $ipAddress,
        #[OA\Property(property: 'user_agent', description: 'User-Agent of the requester', example: 'Mozilla/5.0...', nullable: true)]
        public ?string $userAgent,
        #[OA\Property(property: 'created_at', description: 'Log timestamp', example: '2026-02-26 12:00:00')]
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
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'old_values' => $this->oldValues,
            'new_values' => $this->newValues,
            'user_id' => $this->userId,
            'user_email' => $this->userEmail,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'created_at' => $this->createdAt,
        ];
    }
}
