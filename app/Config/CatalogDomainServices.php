<?php

declare(strict_types=1);

namespace Config;

trait CatalogDomainServices
{
    public static function demoproductResponseMapper(bool $getShared = true): \App\Interfaces\Mappers\ResponseMapperInterface
    {
        if ($getShared) {
            return static::getSharedInstance('demoproductResponseMapper');
        }

        return new \App\Services\Core\Mappers\DtoResponseMapper(
            \App\DTO\Response\Catalog\DemoproductResponseDTO::class
        );
    }

    public static function demoproductService(bool $getShared = true): \App\Interfaces\Catalog\DemoproductServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('demoproductService');
        }

        return new \App\Services\Catalog\DemoproductService(
            static::demoproductRepository(),
            static::demoproductResponseMapper()
        );
    }
}
