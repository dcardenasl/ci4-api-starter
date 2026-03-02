<?php

declare(strict_types=1);

namespace App\Services\Core\Mappers;

use App\Interfaces\DataTransferObjectInterface;
use App\Interfaces\Mappers\ResponseMapperInterface;

class DtoResponseMapper implements ResponseMapperInterface
{
    public function __construct(
        private readonly string $responseDtoClass
    ) {
    }

    public function map(object $entity): DataTransferObjectInterface
    {
        if (!class_exists($this->responseDtoClass)) {
            throw new \RuntimeException(lang('Api.responseDtoNotDefined', [$this->responseDtoClass]));
        }

        $data = $this->extractData($entity);

        return ($this->responseDtoClass)::fromArray($data);
    }

    /**
     * Attempt to convert the entity into an array.
     */
    private function extractData(object $entity): array
    {
        if (method_exists($entity, 'toArray')) {
            return $entity->toArray();
        }

        return (array) $entity;
    }
}
