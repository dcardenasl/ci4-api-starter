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

    public static function appUserMembershipResponseMapper(bool $getShared = true): \App\Interfaces\Mappers\ResponseMapperInterface
    {
        if ($getShared) {
            return static::getSharedInstance('appUserMembershipResponseMapper');
        }

        return new \App\Services\Core\Mappers\DtoResponseMapper(
            \App\DTO\Response\Iam\AppUserMembershipResponseDTO::class
        );
    }

    public static function appUserMembershipService(bool $getShared = true): \App\Interfaces\Iam\AppUserMembershipServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('appUserMembershipService');
        }

        return new \App\Services\Iam\AppUserMembershipService(
            new \App\Repositories\GenericRepository(model(\App\Models\AppUserMembershipModel::class)),
            static::appUserMembershipResponseMapper(),
            static::effectivePermissionsResolver()
        );
    }

    public static function applicationResponseMapper(bool $getShared = true): \App\Interfaces\Mappers\ResponseMapperInterface
    {
        if ($getShared) {
            return static::getSharedInstance('applicationResponseMapper');
        }

        return new \App\Services\Core\Mappers\DtoResponseMapper(
            \App\DTO\Response\Iam\ApplicationResponseDTO::class
        );
    }

    public static function applicationService(bool $getShared = true): \App\Interfaces\Iam\ApplicationServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('applicationService');
        }

        return new \App\Services\Iam\ApplicationService(
            new \App\Repositories\GenericRepository(model(\App\Models\ApplicationModel::class)),
            static::applicationResponseMapper()
        );
    }

    public static function effectivePermissionsResolver(bool $getShared = true): \App\Services\Iam\EffectivePermissionsResolver
    {
        if ($getShared) {
            return static::getSharedInstance('effectivePermissionsResolver');
        }

        return new \App\Services\Iam\EffectivePermissionsResolver(
            \Config\Database::connect(),
            static::cache()
        );
    }

    public static function membershipProvisioner(bool $getShared = true): \App\Services\Iam\MembershipProvisioner
    {
        if ($getShared) {
            return static::getSharedInstance('membershipProvisioner');
        }

        return new \App\Services\Iam\MembershipProvisioner();
    }
}
