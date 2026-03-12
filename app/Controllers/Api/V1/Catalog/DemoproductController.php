<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Catalog;

use App\Controllers\ApiController;
use App\DTO\Request\Catalog\DemoproductCreateRequestDTO;
use App\DTO\Request\Catalog\DemoproductIndexRequestDTO;
use App\DTO\Request\Catalog\DemoproductUpdateRequestDTO;
use App\Interfaces\Catalog\DemoproductServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class DemoproductController extends ApiController
{
    protected DemoproductServiceInterface $demoproductService;

    protected function resolveDefaultService(): object
    {
        $this->demoproductService = Services::demoproductService();

        return $this->demoproductService;
    }

    protected array $statusCodes = [
        'store' => 201,
    ];

    public function index(): ResponseInterface
    {
        return $this->handleRequest('index', DemoproductIndexRequestDTO::class);
    }

    public function create(): ResponseInterface
    {
        return $this->handleRequest('store', DemoproductCreateRequestDTO::class);
    }

    public function update(int $id): ResponseInterface
    {
        return $this->handleRequest(
            fn ($dto, $context) => $this->demoproductService->update($id, $dto, $context),
            DemoproductUpdateRequestDTO::class
        );
    }

    public function show(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->demoproductService->show($id, $context));
    }

    public function delete(int $id): ResponseInterface
    {
        return $this->handleRequest(fn ($dto, $context) => $this->demoproductService->destroy($id, $context));
    }
}
