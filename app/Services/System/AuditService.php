<?php

declare(strict_types=1);

namespace App\Services\System;

use App\DTO\Response\Common\PayloadResponseDTO;
use App\DTO\SecurityContext;
use App\DTO\System\AuditEventDTO;
use App\Interfaces\DataTransferObjectInterface;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Interfaces\System\AuditRepositoryInterface;
use App\Services\Core\BaseCrudService;

/**
 * Modernized Audit Service
 *
 * Handles automated trail logging and provides queryable access to logs.
 * Inherits BaseCrudService for automated index and show operations.
 */
class AuditService extends BaseCrudService implements \App\Interfaces\System\AuditServiceInterface
{
    /**
     * @var bool Allow enabling audit logging during tests
     */
    public static bool $forceEnabledInTests = false;

    public function __construct(
        protected readonly AuditRepositoryInterface $auditRepository,
        ResponseMapperInterface $responseMapper,
        protected bool $enabled = true,
        protected string $defaultIpAddress = '127.0.0.1',
        protected string $defaultUserAgent = 'system',
        protected ?AuditPayloadSanitizer $payloadSanitizer = null
    ) {
        parent::__construct($responseMapper);
        $this->repository = $auditRepository;
        $this->payloadSanitizer ??= new AuditPayloadSanitizer();
    }

    /**
     * Log an audit event
     */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId,
        array $oldValues,
        array $newValues,
        ?SecurityContext $context = null,
        string $result = 'success',
        string $severity = 'info',
        array $metadata = [],
        ?string $requestId = null
    ): void {
        if (!$this->enabled && !self::$forceEnabledInTests) {
            return;
        }

        $event = $this->buildEvent(
            $action,
            $entityType,
            $entityId,
            $oldValues,
            $newValues,
            $context,
            $result,
            $severity,
            $metadata,
            $requestId
        );

        $userId = $event->context?->user_id;
        $ipAddress = trim((string) ($event->context?->metadata['ip_address'] ?? ''));
        $userAgent = trim((string) ($event->context?->metadata['user_agent'] ?? ''));

        $ipAddress = $ipAddress !== '' ? $ipAddress : $this->defaultIpAddress;
        $userAgent = $userAgent !== '' ? $userAgent : $this->defaultUserAgent;

        $data = [
            'user_id'     => $userId,
            'action'      => $event->action,
            'entity_type' => $event->entity_type,
            'entity_id'   => $event->entity_id,
            'old_values'  => $event->old_values !== [] ? json_encode($event->old_values) : null,
            'new_values'  => $event->new_values !== [] ? json_encode($event->new_values) : null,
            'ip_address'  => $ipAddress,
            'user_agent'  => $userAgent,
            'result'      => $event->result,
            'severity'    => $event->severity,
            'request_id'  => $event->request_id,
            'metadata'    => $event->metadata !== [] ? json_encode($event->metadata) : null,
            'created_at'  => date('Y-m-d H:i:s'),
        ];

        try {
            $this->auditRepository->insert($data);
        } catch (\Throwable $e) {
            if (!($e instanceof \CodeIgniter\Database\Exceptions\DatabaseException)) {
                throw $e;
            }

            // Retry logic for foreign key constraints (when user might have been deleted)
            if ($userId !== null && (str_contains($e->getMessage(), '1452') || str_contains($e->getMessage(), 'foreign key'))) {
                $data['user_id'] = null;
                $this->auditRepository->insert($data);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Log a create action
     */
    public function logCreate(string $entityType, int $entityId, array $data, ?SecurityContext $context = null): void
    {
        $this->log('create', $entityType, $entityId, [], $data, $context);
    }

    /**
     * Log an update action
     */
    public function logUpdate(string $entityType, int $entityId, array $oldValues, array $newValues, ?SecurityContext $context = null): void
    {
        $sanitizedOld = $this->payloadSanitizer->sanitize($oldValues);
        $sanitizedNew = $this->payloadSanitizer->sanitize($newValues);

        if (json_encode($sanitizedOld) !== json_encode($sanitizedNew)) {
            $this->log('update', $entityType, $entityId, $sanitizedOld, $sanitizedNew, $context);
        }
    }

    /**
     * Log a delete action
     */
    public function logDelete(string $entityType, int $entityId, array $data, ?SecurityContext $context = null): void
    {
        $this->log('delete', $entityType, $entityId, $data, [], $context);
    }

    private function normalizeEntityType(string $entityType): string
    {
        $normalized = strtolower(trim($entityType));
        $aliases = [
            'user' => 'users',
            'api-key' => 'api_keys',
            'file' => 'files',
        ];
        return $aliases[$normalized] ?? $normalized;
    }

    private function normalizeResult(string $result): string
    {
        $normalized = strtolower(trim($result));
        return in_array($normalized, ['success', 'failure', 'denied'], true) ? $normalized : 'success';
    }

    private function normalizeSeverity(string $severity): string
    {
        $normalized = strtolower(trim($severity));
        return in_array($normalized, ['info', 'warning', 'critical'], true) ? $normalized : 'info';
    }

    private function buildEvent(
        string $action,
        string $entityType,
        ?int $entityId,
        array $oldValues,
        array $newValues,
        ?SecurityContext $context,
        string $result,
        string $severity,
        array $metadata,
        ?string $requestId
    ): AuditEventDTO {
        return new AuditEventDTO(
            action: $action,
            entity_type: $this->normalizeEntityType($entityType),
            entity_id: $entityId,
            old_values: $this->payloadSanitizer->sanitize($oldValues),
            new_values: $this->payloadSanitizer->sanitize($newValues),
            context: $context,
            result: $this->normalizeResult($result),
            severity: $this->normalizeSeverity($severity),
            metadata: $this->payloadSanitizer->sanitize($metadata),
            request_id: $requestId ?: ($context?->metadata['request_id'] ?? null)
        );
    }

    /**
     * Get audit logs for a specific entity
     */
    public function byEntity(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Audit\AuditByEntityRequestDTO $request */
        $entityId = $request->entity_id;
        $entityType = $this->normalizeEntityType($request->entity_type);

        $logs = $this->auditRepository->getByEntity($entityType, $entityId);

        $payload = array_map(
            fn ($log) => $this->mapToResponse($log)->toArray(),
            $logs
        );

        return PayloadResponseDTO::fromArray($payload);
    }

    /**
     * Audit logs are immutable via API
     */
    public function store(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        throw new \BadMethodCallException(lang('Audit.cannotCreateManual'));
    }

    /**
     * Audit logs are immutable via API
     */
    public function update(int $id, DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        throw new \BadMethodCallException(lang('Audit.immutable'));
    }
}
