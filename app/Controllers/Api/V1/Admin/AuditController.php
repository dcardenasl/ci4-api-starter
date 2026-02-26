<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Audit Controller - Admin-only audit log access
 */
class AuditController extends ApiController
{
    protected string $serviceName = 'auditService';

    /**
     * List audit logs with filters and pagination
     */
    public function index(): ResponseInterface
    {
        $dto = $this->getDTO(\App\DTO\Request\Audit\AuditIndexRequestDTO::class);

        return $this->handleRequest(
            fn () => $this->getService()->index($dto)
        );
    }

    /**
     * Get audit logs for a specific entity
     */
    public function byEntity(string $type, int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn () => $this->getService()->byEntity([
                'entity_type' => $type,
                'entity_id'   => $id,
            ])
        );
    }
}
