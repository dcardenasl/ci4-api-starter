<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Interfaces\AuditServiceInterface;
use App\Libraries\ApiResponse;
use App\Libraries\Query\QueryBuilder;
use App\Models\AuditLogModel;
use App\Traits\AppliesQueryOptions;
use CodeIgniter\HTTP\RequestInterface;

/**
 * Audit Service
 *
 * Logs and retrieves audit trail records
 */
class AuditService implements AuditServiceInterface
{
    use AppliesQueryOptions;

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
     *
     * @param string $action Action performed (create, update, delete)
     * @param string $entityType Entity type (user, file, etc.)
     * @param int|null $entityId Entity ID
     * @param array $oldValues Old values before change
     * @param array $newValues New values after change
     * @param int|null $userId User who performed action
     * @param RequestInterface|null $request Request object for IP/User-Agent (optional)
     * @return void
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
     *
     * @param string $entityType
     * @param int $entityId
     * @param array $data
     * @param int|null $userId
     * @param RequestInterface|null $request
     * @return void
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
     *
     * @param string $entityType
     * @param int $entityId
     * @param array $oldValues
     * @param array $newValues
     * @param int|null $userId
     * @param RequestInterface|null $request
     * @return void
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
     *
     * @param string $entityType
     * @param int $entityId
     * @param array $data
     * @param int|null $userId
     * @param RequestInterface|null $request
     * @return void
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
     *
     * @param array $data
     * @return array
     */
    public function index(array $data): array
    {
        $this->validateInputOrBadRequest($data, 'index');

        $builder = new QueryBuilder($this->auditLogModel);

        if (
            isset($data['filter'])
            && is_array($data['filter'])
            && isset($data['filter']['entity_type'])
            && is_string($data['filter']['entity_type'])
        ) {
            $data['filter']['entity_type'] = $this->normalizeEntityType($data['filter']['entity_type']);
        }

        $this->applyQueryOptions($builder, $data);

        [$page, $limit] = $this->resolvePagination($data, 50, 100);

        $result = $builder->paginate($page, $limit);

        // Decode JSON fields
        $result['data'] = array_map(function ($log) {
            return [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'old_values' => $log->old_values ? json_decode($log->old_values, true) : null,
                'new_values' => $log->new_values ? json_decode($log->new_values, true) : null,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at,
            ];
        }, $result['data']);

        return ApiResponse::paginated(
            $result['data'],
            $result['total'],
            $result['page'],
            $result['perPage']
        );
    }

    /**
     * Get audit log by ID
     *
     * @param array $data
     * @return array
     */
    public function show(array $data): array
    {
        $this->validateInputOrBadRequest($data, 'show');

        $log = $this->auditLogModel->find($data['id']);

        if (!$log) {
            throw new NotFoundException(lang('Audit.notFound'));
        }

        return ApiResponse::success([
            'id' => $log->id,
            'user_id' => $log->user_id,
            'action' => $log->action,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'old_values' => $log->old_values ? json_decode($log->old_values, true) : null,
            'new_values' => $log->new_values ? json_decode($log->new_values, true) : null,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'created_at' => $log->created_at,
        ]);
    }

    /**
     * Get audit logs for a specific entity
     *
     * @param array $data
     * @return array
     */
    public function byEntity(array $data): array
    {
        $this->validateInputOrBadRequest($data, 'by_entity');

        $entityType = $this->normalizeEntityType((string) $data['entity_type']);

        $logs = $this->auditLogModel->getByEntity(
            $entityType,
            (int) $data['entity_id']
        );

        $logsArray = array_map(function ($log) {
            return [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'old_values' => $log->old_values ? json_decode($log->old_values, true) : null,
                'new_values' => $log->new_values ? json_decode($log->new_values, true) : null,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at,
            ];
        }, $logs);

        return ApiResponse::success($logsArray);
    }

    private function normalizeEntityType(string $entityType): string
    {
        $normalized = strtolower(trim($entityType));

        return self::ENTITY_TYPE_ALIASES[$normalized] ?? $normalized;
    }

    private function validateInputOrBadRequest(array $data, string $action): void
    {
        $validation = getValidationRules('audit', $action);
        $errors = validateInputs($data, $validation['rules'], $validation['messages']);

        if ($errors !== []) {
            throw new BadRequestException(lang('Api.invalidRequest'), $errors);
        }
    }

    /**
     * Recursively remove sensitive fields from audit values.
     *
     * @param array $values
     * @return array
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
