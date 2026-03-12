<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\ApiController;
use App\DTO\Request\Catalog\AuditFacetsRequestDTO;
use App\Interfaces\System\CatalogServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class CatalogController extends ApiController
{
    private CatalogServiceInterface $catalogService;

    protected function resolveDefaultService(): object
    {
        $this->catalogService = Services::catalogService();

        return $this->catalogService;
    }

    public function index(): ResponseInterface
    {
        return $this->handleRequest(
            fn (mixed $payload, \App\DTO\SecurityContext $context) => $this->catalogService->index(),
            null
        );
    }

    public function auditFacets(): ResponseInterface
    {
        return $this->handleRequest('auditFacets', AuditFacetsRequestDTO::class);
    }
}
