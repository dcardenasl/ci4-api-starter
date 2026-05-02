<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\Interfaces\Core\RepositoryInterface;
use App\Interfaces\Iam\PermissionServiceInterface;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Services\Core\BaseCrudService;

class PermissionService extends BaseCrudService implements PermissionServiceInterface
{
    public function __construct(
        RepositoryInterface $permissionRepository,
        ResponseMapperInterface $responseMapper
    ) {
        parent::__construct($permissionRepository, $responseMapper);
    }

    /**
     * Domain Hooks
     *
     * Implement beforeStore, afterStore, beforeUpdate, etc.,
     * to add specific business logic while keeping the service layer clean.
     */
}
