<?php

namespace App\Traits;

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
        $this->afterInsert[] = 'auditInsert';
        $this->afterUpdate[] = 'auditUpdate';
        $this->afterDelete[] = 'auditDelete';
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

        // Get old values
        $old = $this->find($id);
        if (!$old) {
            return;
        }

        // Use toArray() to respect Entity filtering (prevents password exposure)
        $oldValues = is_object($old)
            ? (method_exists($old, 'toArray') ? $old->toArray() : (array) $old)
            : $old;
        $newValues = array_merge($oldValues, $data['data'] ?? []);

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
        if (!isset($data['id']) || !isset($data['data'])) {
            return;
        }

        $id = is_array($data['id']) ? (int) $data['id'][0] : (int) $data['id'];
        // Use toArray() to respect Entity filtering (prevents password exposure)
        $deletedData = is_object($data['data'])
            ? (method_exists($data['data'], 'toArray') ? $data['data']->toArray() : (array) $data['data'])
            : $data['data'];

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

        // Check if userId is set by JwtAuthFilter
        if (property_exists($request, 'userId')) {
            return (int) $request->userId;
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
