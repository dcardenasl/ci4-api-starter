<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\Entities\PermissionEntity;
use App\Interfaces\Iam\PermissionServiceInterface;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;
use dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface;
use dcardenasl\Ci4ApiCore\Services\BaseCrudService;
use dcardenasl\Ci4ApiCore\Support\RelationLabelLoader;

/**
 * @extends BaseCrudService<PermissionEntity>
 */
class PermissionService extends BaseCrudService implements PermissionServiceInterface
{
    /**
     * @param RepositoryInterface<PermissionEntity> $permissionRepository
     */
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

        $dto = new \App\DTO\Request\Iam\PermissionCreateRequestDTO($data);

        // Auto-assign application_id from context if not provided
        if (empty($dto->application_id) && $context !== null && $context->app_id !== null) {
            $data['application_id'] = $context->app_id;
        }

        return parent::beforeStore($data, $context);
    }

    protected function beforeUpdate(int $id, array $data, ?SecurityContext $context): array
    {
        $this->authz->assertSuperAdmin($context);

        $dto = new \App\DTO\Request\Iam\PermissionUpdateRequestDTO($data);

        return parent::beforeUpdate($id, $data, $context);
    }

    protected function beforeDelete(int $id, ?SecurityContext $context): void
    {
        $this->authz->assertSuperAdmin($context);

        parent::beforeDelete($id, $context);
    }
}
