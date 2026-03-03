<?php

declare(strict_types=1);

namespace App\Services\System;

use App\DTO\Response\Common\PayloadResponseDTO;
use App\DTO\SecurityContext;
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
    private const SENSITIVE_FIELDS = [
        'password', 'password_confirmation', 'token', 'accesstoken', 'refreshtoken', 'apikey', 'access_token', 'refresh_token', 'api_key', 'key_hash'
    ];

    /**
     * @var bool Allow enabling audit logging during tests
     */
    public static bool $forceEnabledInTests = false;

    public function __construct(
        protected readonly AuditRepositoryInterface $auditRepository,
        ResponseMapperInterface $responseMapper,
        protected string $defaultIpAddress = '127.0.0.1',
        protected string $defaultUserAgent = 'system'
    ) {
        parent::__construct($responseMapper);
        $this->repository = $auditRepository;
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
        ?SecurityContext $context = null
    ): void {
        // BYPASS IN TESTING: Audit logging is disabled in tests unless explicitly forced
        if (ENVIRONMENT === 'testing' && !self::$forceEnabledInTests) {
            return;
        }

        $userId = $context?->user_id;

        // Network metadata resolution (prefer context, fallback to request helper if safe)
        $ipAddress = trim((string) ($context?->metadata['ip_address'] ?? ''));
        $userAgent = trim((string) ($context?->metadata['user_agent'] ?? ''));

        if ($ipAddress === '') {
            $ipAddress = $this->defaultIpAddress;
        }

        if ($userAgent === '') {
            $userAgent = $this->defaultUserAgent;
        }

        $data = [
            'user_id'     => $userId,
            'action'      => $action,
            'entity_type' => $this->normalizeEntityType($entityType),
            'entity_id'   => $entityId,
            'old_values'  => ($old = $this->sanitizeAuditValues($oldValues)) ? json_encode($old) : null,
            'new_values'  => ($new = $this->sanitizeAuditValues($newValues)) ? json_encode($new) : null,
            'ip_address'  => $ipAddress,
            'user_agent'  => $userAgent,
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
        $sanitizedOld = $this->sanitizeAuditValues($oldValues);
        $sanitizedNew = $this->sanitizeAuditValues($newValues);

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

    private function sanitizeAuditValues(array $values): array
    {
        $sanitized = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_FIELDS, true)) {
                continue;
            }
            $sanitized[$key] = is_array($value) ? $this->sanitizeAuditValues($value) : $value;
        }
        return $sanitized;
    }
}
