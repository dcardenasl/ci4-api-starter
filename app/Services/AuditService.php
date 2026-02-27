<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\SecurityContext;
use App\Interfaces\AuditServiceInterface;
use App\Interfaces\DataTransferObjectInterface;
use App\Models\AuditLogModel;

/**
 * Modernized Audit Service
 *
 * Handles automated trail logging and provides queryable access to logs.
 * Inherits BaseCrudService for automated index and show operations.
 */
class AuditService extends BaseCrudService implements AuditServiceInterface
{
    use \App\Traits\AppliesQueryOptions;

    protected string $responseDtoClass = \App\DTO\Response\Audit\AuditResponseDTO::class;

    private const SENSITIVE_FIELDS = [
        'password', 'password_confirmation', 'token', 'access_token', 'refresh_token', 'api_key', 'key_hash'
    ];

    public function __construct(protected AuditLogModel $auditLogModel)
    {
        $this->model = $auditLogModel;
    }

    /**
     * @var bool Allow enabling audit logging during tests
     */
    public static bool $forceEnabledInTests = false;

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

        $userId = $context?->userId;
        $request = \Config\Services::request();

        // Use network info from context metadata if available, otherwise fallback to request
        $ipAddress = $context?->metadata['ip_address'] ?? $request->getIPAddress();
        $userAgent = $context?->metadata['user_agent'] ?? $request->getHeaderLine('User-Agent');

        $data = [
            'user_id'     => $userId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_values'  => ($old = $this->sanitizeAuditValues($oldValues)) ? json_encode($old) : null,
            'new_values'  => ($new = $this->sanitizeAuditValues($newValues)) ? json_encode($new) : null,
            'ip_address'  => $ipAddress,
            'user_agent'  => $userAgent,
            'created_at'  => date('Y-m-d H:i:s'),
        ];

        try {
            $this->model->insert($data);
        } catch (\Throwable $e) {
            if (!($e instanceof \CodeIgniter\Database\Exceptions\DatabaseException)) {
                throw $e;
            }

            // If it's a foreign key error, retry without user_id
            if ($userId !== null && (str_contains($e->getMessage(), '1452') || str_contains($e->getMessage(), 'foreign key'))) {
                $data['user_id'] = null;
                $this->model->insert($data);
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

        // Only log if there are actual non-sensitive changes
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
    public function byEntity(array $data, ?SecurityContext $context = null): array
    {
        $entityId = (int) ($data['entity_id'] ?? 0);
        $entityType = $this->normalizeEntityType((string) ($data['entity_type'] ?? ''));

        $logs = $this->auditLogModel->getByEntity($entityType, $entityId);

        return array_map(fn ($log) => $this->mapToResponse($log), $logs);
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
