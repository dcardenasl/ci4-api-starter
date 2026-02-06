<?php

declare(strict_types=1);

namespace App\Interfaces;

use CodeIgniter\HTTP\RequestInterface;

/**
 * Audit Service Interface
 *
 * Contract for audit logging and retrieval operations
 */
interface AuditServiceInterface
{
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
    ): void;

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
    ): void;

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
    ): void;

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
    ): void;

    /**
     * Get audit logs with filtering and pagination
     *
     * @param array $data
     * @return array
     */
    public function index(array $data): array;

    /**
     * Get audit log by ID
     *
     * @param array $data
     * @return array
     */
    public function show(array $data): array;

    /**
     * Get audit logs for a specific entity
     *
     * @param array $data
     * @return array
     */
    public function byEntity(array $data): array;
}
