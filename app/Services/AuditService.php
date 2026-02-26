<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Interfaces\AuditServiceInterface;
use App\Libraries\Query\QueryBuilder;
use App\Models\AuditLogModel;
use App\Traits\AppliesQueryOptions;
use App\Traits\ValidatesRequiredFields;
use CodeIgniter\HTTP\RequestInterface;

/**
 * Audit Service
 *
 * Logs and retrieves audit trail records
 */
class AuditService implements AuditServiceInterface
{
    use AppliesQueryOptions;
    use ValidatesRequiredFields;

    /**
     * Sensitive keys that must never be persisted in audit payloads.
     *
     * @var list<string>
     */
    private const SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
        'auth_token',
        'bearer_token',
        'email_verification_token',
        'verification_token',
        'reset_token',
        'reset_password_token',
        'secret',
        'secret_key',
        'api_key',
        'key_hash',
    ];

    /**
     * Entity aliases accepted by API filters/endpoints.
     *
     * @var array<string, string>
     */
    private const ENTITY_TYPE_ALIASES = [
        'user' => 'users',
        'users' => 'users',
        'file' => 'files',
        'files' => 'files',
        'api-key' => 'api_keys',
        'api_key' => 'api_keys',
        'apikey' => 'api_keys',
        'api-keys' => 'api_keys',
        'api_keys' => 'api_keys',
    ];

    public function __construct(
        protected AuditLogModel $auditLogModel
    ) {
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
        // Use injected request or get from Services as fallback
        $request = $request ?? \Config\Services::request();
        $sanitizedOldValues = $this->sanitizeAuditValues($oldValues);
        $sanitizedNewValues = $this->sanitizeAuditValues($newValues);

        $this->auditLogModel->insert([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => !empty($sanitizedOldValues) ? json_encode($sanitizedOldValues) : null,
            'new_values' => !empty($sanitizedNewValues) ? json_encode($sanitizedNewValues) : null,
            'ip_address' => $request->getIPAddress(),
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log a create action
     */
    public function logCreate(
        string $entityType,
        int $entityId,
        array $data,
        ?int $userId = null,
        ?RequestInterface $request = null
    ): void {
        $this->log('create', $entityType, $entityId, [], $data, $userId, $request);
    }

    /**
     * Log an update action
     */
    public function logUpdate(
        string $entityType,
        int $entityId,
        array $oldValues,
        array $newValues,
        ?int $userId = null,
        ?RequestInterface $request = null
    ): void {
        $sanitizedOldValues = $this->sanitizeAuditValues($oldValues);
        $sanitizedNewValues = $this->sanitizeAuditValues($newValues);

        // Only log if there are actual (non-sensitive) changes.
        if ($sanitizedNewValues !== $sanitizedOldValues) {
            $this->log('update', $entityType, $entityId, $sanitizedOldValues, $sanitizedNewValues, $userId, $request);
        }
    }

    /**
     * Log a delete action
     */
    public function logDelete(
        string $entityType,
        int $entityId,
        array $data,
        ?int $userId = null,
        ?RequestInterface $request = null
    ): void {
        $this->log('delete', $entityType, $entityId, $data, [], $userId, $request);
    }

    /**
     * Get audit logs with filtering and pagination
     */
    public function index(\App\DTO\Request\Audit\AuditIndexRequestDTO $request): array
    {
        $builder = new QueryBuilder($this->auditLogModel);

        $this->applyQueryOptions($builder, $request->toArray());

        $result = $builder->paginate($request->page, $request->perPage);

        // Convert to Response DTOs
        $result['data'] = array_map(function ($log) {
            return \App\DTO\Response\Audit\AuditResponseDTO::fromArray($log->toArray());
        }, $result['data']);

        return [
            'data' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage']
        ];
    }

    /**
     * Get audit log by ID
     */
    public function show(array $data): \App\DTO\Response\Audit\AuditResponseDTO
    {
        $id = $this->validateRequiredId($data);

        $log = $this->auditLogModel->find($id);

        if (!$log) {
            throw new NotFoundException(lang('Audit.notFound'));
        }

        return \App\DTO\Response\Audit\AuditResponseDTO::fromArray($log->toArray());
    }

    /**
     * Get audit logs for a specific entity
     */
    public function byEntity(array $data): array
    {
        $this->validateRequiredId($data, 'entity_id');
        $entityType = $this->normalizeEntityType((string) ($data['entity_type'] ?? ''));

        $logs = $this->auditLogModel->getByEntity(
            $entityType,
            (int) $data['entity_id']
        );

        return array_map(function ($log) {
            return \App\DTO\Response\Audit\AuditResponseDTO::fromArray($log->toArray());
        }, $logs);
    }

    private function normalizeEntityType(string $entityType): string
    {
        $normalized = strtolower(trim($entityType));

        return self::ENTITY_TYPE_ALIASES[$normalized] ?? $normalized;
    }

    /**
     * Recursively remove sensitive fields from audit values.
     */
    private function sanitizeAuditValues(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_FIELDS, true)) {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeAuditValues($value);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
