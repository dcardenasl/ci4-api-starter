<?php

declare(strict_types=1);

namespace App\Services\Iam;

use App\Interfaces\Core\RepositoryInterface;
use App\Interfaces\Iam\ApplicationServiceInterface;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Services\Core\BaseCrudService;

class ApplicationService extends BaseCrudService implements ApplicationServiceInterface
{
    public function __construct(
        RepositoryInterface $applicationRepository,
        ResponseMapperInterface $responseMapper
    ) {
        parent::__construct($applicationRepository, $responseMapper);
    }
}
