<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\Interfaces\Iam\ApplicationServiceInterface;
use dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface;
use dcardenasl\Ci4ApiCore\Repositories\RepositoryInterface;
use dcardenasl\Ci4ApiCore\Services\BaseCrudService;

class ApplicationService extends BaseCrudService implements ApplicationServiceInterface
{
    public function __construct(
        RepositoryInterface $applicationRepository,
        ResponseMapperInterface $responseMapper
    ) {
        parent::__construct($applicationRepository, $responseMapper);
    }
}
