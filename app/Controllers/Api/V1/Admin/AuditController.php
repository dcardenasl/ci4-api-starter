<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\ApiController;
use App\DTO\Request\Audit\AuditIndexRequestDTO;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Modernized Audit Controller
 *
 * Provides administrative access to the system audit trail.
 */
class AuditController extends ApiController
{
    protected string $serviceName = 'auditService';

    /**
     * List all audit logs
     */
    public function index(): ResponseInterface
    {
        return $this->handleRequest('index', AuditIndexRequestDTO::class);
    }

    /**
     * Get a single audit log detail
     */
    public function show(int $id): ResponseInterface
    {
        return $this->handleRequest(fn () => $this->getService()->show($id));
    }


    /**
     * Get audit logs for a specific entity
     */
    public function byEntity(string $type, int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn () => $this->getService()->byEntity(['entity_type' => $type, 'entity_id' => $id])
        );
    }
}
