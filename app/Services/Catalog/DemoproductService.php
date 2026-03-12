<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Interfaces\Catalog\DemoproductServiceInterface;
use App\Interfaces\Core\RepositoryInterface;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Services\Core\BaseCrudService;

class DemoproductService extends BaseCrudService implements DemoproductServiceInterface
{
    public function __construct(
        RepositoryInterface $demoproductRepository,
        ResponseMapperInterface $responseMapper
    ) {
        parent::__construct($demoproductRepository, $responseMapper);
    }

    /**
     * Store and update are now handled by BaseCrudService.
     * Use beforeStore/afterStore hooks for domain-specific logic.
     */
}
