<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Audit Service Interface
 *
 * Contract for audit logging and retrieval operations
 */
use App\DTO\Request\Audit\AuditIndexRequestDTO;
use App\DTO\Response\Audit\AuditResponseDTO;

/**
 * Audit Service Interface
 *
 * Contract for audit logging and retrieval operations
 */
interface AuditServiceInterface
{
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
        ?\CodeIgniter\HTTP\RequestInterface $request = null
    ): void;

    /**
     * Log a create action
     */
    public function logCreate(
        string $entityType,
        int $entityId,
        array $data,
        ?int $userId = null,
        ?\CodeIgniter\HTTP\RequestInterface $request = null
    ): void;

    /**
     * Log an update action
     */
    public function logUpdate(
        string $entityType,
        int $entityId,
        array $oldValues,
        array $newValues,
        ?int $userId = null,
        ?\CodeIgniter\HTTP\RequestInterface $request = null
    ): void;

    /**
     * Log a delete action
     */
    public function logDelete(
        string $entityType,
        int $entityId,
        array $data,
        ?int $userId = null,
        ?\CodeIgniter\HTTP\RequestInterface $request = null
    ): void;

    /**
     * Get audit logs with filtering and pagination
     */
    public function index(AuditIndexRequestDTO $request): array;

    /**
     * Get audit log by ID
     */
    public function show(array $data): AuditResponseDTO;

    /**
     * Get audit logs for a specific entity
     */
    public function byEntity(array $data): array;
}
