<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Audit Service Interface
 */
interface AuditServiceInterface
{
    /**
     * Log an audit event (Internal)
     */
    public function log(string $action, string $entityType, ?int $entityId, array $oldValues, array $newValues, ?int $userId = null, ?\CodeIgniter\HTTP\RequestInterface $request = null): void;

    /**
     * Log structured events (Internal)
     */
    public function logCreate(string $entityType, int $entityId, array $data, ?int $userId = null, ?\CodeIgniter\HTTP\RequestInterface $request = null): void;
    public function logUpdate(string $entityType, int $entityId, array $oldValues, array $newValues, ?int $userId = null, ?\CodeIgniter\HTTP\RequestInterface $request = null): void;
    public function logDelete(string $entityType, int $entityId, array $data, ?int $userId = null, ?\CodeIgniter\HTTP\RequestInterface $request = null): void;

    /**
     * List audit logs (API)
     */
    public function index(\App\Interfaces\DataTransferObjectInterface $request): array;

    /**
     * Get single log (API)
     */
    public function show(int $id): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Get logs by entity (Internal/API)
     */
    public function byEntity(array $data): array;
}
