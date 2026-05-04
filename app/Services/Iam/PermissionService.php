<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\DTO\SecurityContext;
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
        private readonly IamAuthorizationService $authz,
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

    protected function beforeStore(array $data, ?SecurityContext $context): array
    {
        $this->authz->assertSuperAdmin($context);

        return parent::beforeStore($data, $context);
    }

    protected function beforeUpdate(int $id, array $data, ?SecurityContext $context): array
    {
        $this->authz->assertSuperAdmin($context);

        return parent::beforeUpdate($id, $data, $context);
    }

    protected function beforeDelete(int $id, ?SecurityContext $context): void
    {
        $this->authz->assertSuperAdmin($context);

        parent::beforeDelete($id, $context);
    }
}
