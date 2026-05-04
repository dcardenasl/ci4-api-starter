<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\Interfaces\Core\RepositoryInterface;
use App\Interfaces\Iam\PermissionServiceInterface;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Services\Core\BaseCrudService;
use App\Services\Core\Support\RelationLabelLoader;

class PermissionService extends BaseCrudService implements PermissionServiceInterface
{
    public function __construct(
        RepositoryInterface $permissionRepository,
        ResponseMapperInterface $responseMapper,
        private readonly RelationLabelLoader $labels = new RelationLabelLoader()
    ) {
        parent::__construct($permissionRepository, $responseMapper);
    }

    protected function enrichEntities(array $entities): array
    {
        return $this->labels->attachLabel(
            $entities,
            sourceField: 'application_id',
            targetField: 'application_name',
            relatedTable: 'applications',
            relatedLabel: 'name'
        );
    }
}
