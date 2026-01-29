<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\ApiResponse;
use App\Libraries\Query\QueryBuilder;
use App\Models\AuditLogModel;

/**
 * Audit Service
 *
 * Logs and retrieves audit trail records
 */
class AuditService
{
    protected AuditLogModel $auditLogModel;

    public function __construct(AuditLogModel $auditLogModel)
    {
        $this->auditLogModel = $auditLogModel;
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
     * @return void
     */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId,
        array $oldValues,
        array $newValues,
        ?int $userId = null
    ): void {
        $request = \Config\Services::request();

        $this->auditLogModel->insert([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => !empty($oldValues) ? json_encode($oldValues) : null,
            'new_values' => !empty($newValues) ? json_encode($newValues) : null,
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
     * @return void
     */
    public function logCreate(
        string $entityType,
        int $entityId,
        array $data,
        ?int $userId = null
    ): void {
        $this->log('create', $entityType, $entityId, [], $data, $userId);
    }

    /**
     * Log an update action
     *
     * @param string $entityType
     * @param int $entityId
     * @param array $oldValues
     * @param array $newValues
     * @param int|null $userId
     * @return void
     */
    public function logUpdate(
        string $entityType,
        int $entityId,
        array $oldValues,
        array $newValues,
        ?int $userId = null
    ): void {
        // Only log if there are actual changes
        $diff = array_diff_assoc($newValues, $oldValues);
        if (!empty($diff)) {
            $this->log('update', $entityType, $entityId, $oldValues, $newValues, $userId);
        }
    }

    /**
     * Log a delete action
     *
     * @param string $entityType
     * @param int $entityId
     * @param array $data
     * @param int|null $userId
     * @return void
     */
    public function logDelete(
        string $entityType,
        int $entityId,
        array $data,
        ?int $userId = null
    ): void {
        $this->log('delete', $entityType, $entityId, $data, [], $userId);
    }

    /**
     * Get audit logs with filtering and pagination
     *
     * @param array $data
     * @return array
     */
    public function index(array $data): array
    {
        $builder = new QueryBuilder($this->auditLogModel);

        // Apply filters
        if (!empty($data['filter'])) {
            $builder->filter($data['filter']);
        }

        // Apply search
        if (!empty($data['search'])) {
            $builder->search($data['search']);
        }

        // Apply sorting
        if (!empty($data['sort'])) {
            $builder->sort($data['sort']);
        }

        // Paginate
        $page = $data['page'] ?? 1;
        $limit = min($data['limit'] ?? 50, 100);

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
        if (empty($data['id'])) {
            return ApiResponse::error(
                ['id' => 'Audit log ID is required'],
                'Invalid request'
            );
        }

        $log = $this->auditLogModel->find($data['id']);

        if (!$log) {
            return ApiResponse::error(
                ['audit_log' => 'Audit log not found'],
                'Not found',
                404
            );
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
        if (empty($data['entity_type']) || empty($data['entity_id'])) {
            return ApiResponse::error(
                ['entity' => 'Entity type and ID are required'],
                'Invalid request'
            );
        }

        $logs = $this->auditLogModel->getByEntity(
            $data['entity_type'],
            (int) $data['entity_id']
        );

        $logsArray = array_map(function ($log) {
            return [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'action' => $log->action,
                'old_values' => $log->old_values ? json_decode($log->old_values, true) : null,
                'new_values' => $log->new_values ? json_decode($log->new_values, true) : null,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at,
            ];
        }, $logs);

        return ApiResponse::success($logsArray);
    }
}
