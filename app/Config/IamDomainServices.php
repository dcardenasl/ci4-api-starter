<?php

declare(strict_types=1);

namespace Config;

trait IamDomainServices
{
    public static function roleResponseMapper(bool $getShared = true): \App\Interfaces\Mappers\ResponseMapperInterface
    {
        if ($getShared) {
            return static::getSharedInstance('roleResponseMapper');
        }

        return new \App\Services\Core\Mappers\DtoResponseMapper(
            \App\DTO\Response\Iam\RoleResponseDTO::class
        );
    }

    public static function roleService(bool $getShared = true): \App\Interfaces\Iam\RoleServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('roleService');
        }

        return new \App\Services\Iam\RoleService(
            new \App\Repositories\GenericRepository(model(\App\Models\RoleModel::class)),
            static::roleResponseMapper()
        );
    }

    public static function permissionResponseMapper(bool $getShared = true): \App\Interfaces\Mappers\ResponseMapperInterface
    {
        if ($getShared) {
            return static::getSharedInstance('permissionResponseMapper');
        }

        return new \App\Services\Core\Mappers\DtoResponseMapper(
            \App\DTO\Response\Iam\PermissionResponseDTO::class
        );
    }

    public static function permissionService(bool $getShared = true): \App\Interfaces\Iam\PermissionServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('permissionService');
        }

        return new \App\Services\Iam\PermissionService(
            new \App\Repositories\GenericRepository(model(\App\Models\PermissionModel::class)),
            static::permissionResponseMapper()
        );
    }
}
