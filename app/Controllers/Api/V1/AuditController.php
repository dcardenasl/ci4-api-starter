<?php

namespace App\Controllers\Api\V1;

use App\Controllers\ApiController;
use App\Models\AuditLogModel;
use App\Services\AuditService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Audit Controller
 *
 * Admin-only endpoints for viewing audit logs
 */
class AuditController extends ApiController
{
    protected AuditService $auditService;

    public function __construct()
    {
        $auditLogModel = new AuditLogModel();
        $this->auditService = new AuditService($auditLogModel);
    }

    /**
     * Get the service instance
     *
     * @return object
     */
    protected function getService(): object
    {
        return $this->auditService;
    }

    /**
     * Get success status code
     *
     * @param string $method
     * @return int
     */
    protected function getSuccessStatus(string $method): int
    {
        return 200;
    }

    /**
     * List audit logs with filtering and pagination
     *
     * GET /api/v1/audit
     *
     * @return ResponseInterface
     */
    public function index(): ResponseInterface
    {
        return $this->handleRequest('index');
    }

    /**
     * Get audit log by ID
     *
     * GET /api/v1/audit/:id
     *
     * @param int $id Audit log ID
     * @return ResponseInterface
     */
    public function show(int $id): ResponseInterface
    {
        return $this->handleRequest('show', ['id' => $id]);
    }

    /**
     * Get audit logs for a specific entity
     *
     * GET /api/v1/audit/entity/:type/:id
     *
     * @param string $type Entity type (user, file, etc.)
     * @param int $id Entity ID
     * @return ResponseInterface
     */
    public function byEntity(string $type, int $id): ResponseInterface
    {
        $data = [
            'entity_type' => $type,
            'entity_id' => $id,
        ];

        return $this->handleRequest('byEntity', $data);
    }
}
