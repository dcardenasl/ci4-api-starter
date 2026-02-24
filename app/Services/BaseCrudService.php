<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Interfaces\CrudServiceContract;

abstract class BaseCrudService implements CrudServiceContract
{
    /**
     * Enforce common id requirement across CRUD services.
     *
     * @param array<string, mixed> $data
     */
    protected function requireId(array $data, string $field = 'id', string $label = 'Id'): int
    {
        if (!isset($data[$field]) || !is_numeric($data[$field])) {
            throw new BadRequestException(
                lang('Api.invalidRequest'),
                [$field => lang('InputValidation.common.idRequired', [$label])]
            );
        }

        return (int) $data[$field];
    }
}
