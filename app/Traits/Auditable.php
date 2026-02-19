<?php

namespace App\Traits;

use App\HTTP\ApiRequest;
use App\Services\AuditService;

/**
 * Auditable Trait
 *
 * Automatically logs create, update, and delete actions for models
 *
 * SECURITY NOTE: This trait uses Entity::toArray() to ensure sensitive
 * fields (passwords, tokens) are filtered before logging.
 *
 * Make sure your Entity classes override toArray() to exclude sensitive data:
 *
 * class UserEntity extends Entity {
 *     public function toArray(...): array {
 *         $data = parent::toArray(...);
 *         unset($data['password']);
 *         return $data;
 *     }
 * }
 */
trait Auditable
{
    /**
     * Temporary storage for old values before update/delete
     */
    protected array $auditOldValues = [];

    /**
     * Initialize auditable callbacks
     *
     * Call this in model constructor:
     * public function __construct() {
     *     parent::__construct();
     *     $this->initAuditable();
     * }
     */
    protected function initAuditable(): void
    {
        $this->beforeUpdate[] = 'auditBeforeUpdate';
        $this->beforeDelete[] = 'auditBeforeDelete';
        $this->afterInsert[] = 'auditInsert';
        $this->afterUpdate[] = 'auditUpdate';
        $this->afterDelete[] = 'auditDelete';
    }

    /**
     * Capture old values before update
     */
    protected function auditBeforeUpdate(array $data): array
    {
        if (isset($data['id'])) {
            $id = is_array($data['id']) ? (int) $data['id'][0] : (int) $data['id'];
            $old = $this->find($id);

            if ($old) {
                $this->auditOldValues[$id] = is_object($old)
                    ? (method_exists($old, 'toArray') ? $old->toArray() : (array) $old)
                    : $old;
            }
        }

        return $data;
    }

    /**
     * Capture entity data before delete
     */
    protected function auditBeforeDelete(array $data): array
    {
        if (isset($data['id'])) {
            $id = is_array($data['id']) ? (int) $data['id'][0] : (int) $data['id'];
            $entity = $this->find($id);

            if ($entity) {
                $this->auditOldValues[$id] = is_object($entity)
                    ? (method_exists($entity, 'toArray') ? $entity->toArray() : (array) $entity)
                    : $entity;
            }
        }

        return $data;
    }

    /**
     * Audit insert operations
     *
     * @param array $data
     * @return void
     */
    protected function auditInsert(array $data): void
    {
        if (!isset($data['id'])) {
            return;
        }

        $auditService = $this->getAuditService();
        $userId = $this->getCurrentUserId();

        $auditService->logCreate(
            $this->getEntityType(),
            is_array($data['id']) ? (int) $data['id'][0] : (int) $data['id'],
            $data['data'] ?? [],
            $userId
        );
    }

    /**
     * Audit update operations
     *
     * @param array $data
     * @return void
     */
    protected function auditUpdate(array $data): void
    {
        if (!isset($data['id'])) {
            return;
        }

        $id = is_array($data['id']) ? (int) $data['id'][0] : (int) $data['id'];

        // Get old values from before update (captured in auditBeforeUpdate)
        if (!isset($this->auditOldValues[$id])) {
            return;
        }

        $oldValues = $this->auditOldValues[$id];
        $newValues = array_merge($oldValues, $data['data'] ?? []);

        // Clear stored old values
        unset($this->auditOldValues[$id]);

        $auditService = $this->getAuditService();
        $userId = $this->getCurrentUserId();

        $auditService->logUpdate(
            $this->getEntityType(),
            $id,
            $oldValues,
            $newValues,
            $userId
        );
    }

    /**
     * Audit delete operations
     *
     * @param array $data
     * @return void
     */
    protected function auditDelete(array $data): void
    {
        if (!isset($data['id'])) {
            return;
        }

        $id = is_array($data['id']) ? (int) $data['id'][0] : (int) $data['id'];

        // Get entity data from before delete (captured in auditBeforeDelete)
        if (!isset($this->auditOldValues[$id])) {
            return;
        }

        $deletedData = $this->auditOldValues[$id];

        // Clear stored old values
        unset($this->auditOldValues[$id]);

        $auditService = $this->getAuditService();
        $userId = $this->getCurrentUserId();

        $auditService->logDelete(
            $this->getEntityType(),
            $id,
            $deletedData,
            $userId
        );
    }

    /**
     * Get audit service instance
     *
     * @return AuditService
     */
    protected function getAuditService(): AuditService
    {
        static $auditService = null;

        if ($auditService === null) {
            $auditService = new AuditService(new \App\Models\AuditLogModel());
        }

        return $auditService;
    }

    /**
     * Get current user ID from request
     *
     * @return int|null
     */
    protected function getCurrentUserId(): ?int
    {
        $request = \Config\Services::request();

        if ($request instanceof ApiRequest) {
            return $request->getAuthUserId();
        }

        return null;
    }

    /**
     * Get entity type for audit logging
     *
     * Override this in your model if needed
     *
     * @return string
     */
    protected function getEntityType(): string
    {
        // Default: use table name without prefix
        return str_replace($this->DBPrefix ?? '', '', $this->table);
    }
}
