<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\AuditServiceInterface;
use App\Interfaces\DataTransferObjectInterface;
use App\Models\AuditLogModel;
use CodeIgniter\HTTP\RequestInterface;

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
     * Log an audit event
     */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId,
        array $oldValues,
        array $newValues,
        ?int $userId = null,
        ?RequestInterface $request = null
    ): void {
        $request = $request ?? \Config\Services::request();

        $this->model->insert([
            'user_id'     => $userId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_values'  => ($old = $this->sanitizeAuditValues($oldValues)) ? json_encode($old) : null,
            'new_values'  => ($new = $this->sanitizeAuditValues($newValues)) ? json_encode($new) : null,
            'ip_address'  => $request->getIPAddress(),
            'user_agent'  => $request->getHeaderLine('User-Agent'),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log a create action
     */
    public function logCreate(string $entityType, int $entityId, array $data, ?int $userId = null, ?\CodeIgniter\HTTP\RequestInterface $request = null): void
    {
        $this->log('create', $entityType, $entityId, [], $data, $userId, $request);
    }

    /**
     * Log an update action
     */
    public function logUpdate(string $entityType, int $entityId, array $oldValues, array $newValues, ?int $userId = null, ?\CodeIgniter\HTTP\RequestInterface $request = null): void
    {
        $sanitizedOld = $this->sanitizeAuditValues($oldValues);
        $sanitizedNew = $this->sanitizeAuditValues($newValues);

        // Only log if there are actual non-sensitive changes
        if (json_encode($sanitizedOld) !== json_encode($sanitizedNew)) {
            $this->log('update', $entityType, $entityId, $sanitizedOld, $sanitizedNew, $userId, $request);
        }
    }

    /**
     * Log a delete action
     */
    public function logDelete(string $entityType, int $entityId, array $data, ?int $userId = null, ?\CodeIgniter\HTTP\RequestInterface $request = null): void
    {
        $this->log('delete', $entityType, $entityId, $data, [], $userId, $request);
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
    public function byEntity(array $data): array
    {
        $entityId = (int) ($data['entity_id'] ?? 0);
        $entityType = $this->normalizeEntityType((string) ($data['entity_type'] ?? ''));

        $logs = $this->auditLogModel->getByEntity($entityType, $entityId);

        return array_map(fn ($log) => $this->mapToResponse($log), $logs);
    }

    /**
     * Audit logs are immutable via API
     */
    public function store(DataTransferObjectInterface $request): DataTransferObjectInterface
    {
        throw new \BadMethodCallException(lang('Audit.cannotCreateManual'));
    }

    /**
     * Audit logs are immutable via API
     */
    public function update(int $id, DataTransferObjectInterface $request): DataTransferObjectInterface
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
