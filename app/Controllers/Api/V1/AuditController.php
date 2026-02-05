<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Audit Controller - Admin-only audit log access
 */
class AuditController extends ApiController
{
    protected string $serviceName = 'auditService';

    /**
     * Get audit logs for a specific entity
     */
    public function byEntity(string $type, int $id): ResponseInterface
    {
        return $this->handleRequest('byEntity', [
            'entity_type' => $type,
            'entity_id'   => $id,
        ]);
    }
}
