<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\DTO\Response\Common\PaginatedResponseDTO;
use App\DTO\SecurityContext;
use App\Exceptions\NotFoundException;
use App\Interfaces\Core\RepositoryInterface;
use App\Interfaces\DataTransferObjectInterface;
use App\Interfaces\Mappers\ResponseMapperInterface;

/**
 * Enhanced Base CRUD Service
 *
 * Provides automated, generic CRUD operations for all services.
 * Implements transaction safety and automatic Response DTO mapping.
 */
abstract class BaseCrudService implements \App\Interfaces\Core\CrudServiceContract
{
    use \App\Traits\HandlesTransactions;

    /**
     * @var RepositoryInterface The primary repository for this service
     */
    protected RepositoryInterface $repository;

    /**
     * @var ResponseMapperInterface Mapper responsible for turning entities into DTOs
     */
    protected ResponseMapperInterface $responseMapper;

    public function __construct(ResponseMapperInterface $responseMapper)
    {
        $this->responseMapper = $responseMapper;
    }

    /**
     * Get a paginated list of resources
     */
    public function index(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        $requestData = $request->toArray();
        $page = $requestData['page'] ?? 1;
        $per_page = $requestData['per_page'] ?? 20;

        // The base criteria callable allows services to inject base constraints
        $baseCriteria = function ($model) {
            $this->applyBaseCriteria($model);
        };

        // Pass the request data directly to the repository as criteria
        $criteria = $this->applyQueryOptions($requestData);
        $result = $this->repository->paginateCriteria($criteria, (int) $page, (int) $per_page, $baseCriteria);

        // Auto-map entities to response DTOs
        $result['data'] = array_map(
            function ($entity) {
                /** @var object $entity */
                return $this->mapToResponse($entity);
            },
            (array) $result['data']
        );

        return PaginatedResponseDTO::fromArray([
            'data'    => $result['data'],
            'total'   => $result['total'],
            'page'    => $result['page'],
            'per_page' => $result['per_page'],
        ]);
    }

    /**
     * Get a single resource by ID
     */
    public function show(int $id, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        /** @var object|null $entity */
        $entity = $this->repository->find($id);

        if (!$entity) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }

        return $this->mapToResponse($entity);
    }

    /**
     * Delete a resource (soft delete by default)
     */
    public function destroy(int $id, ?SecurityContext $context = null): bool
    {
        if (!$this->repository->find($id)) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }

        return $this->wrapInTransaction(function () use ($id) {
            if (!$this->repository->delete($id)) {
                throw new \RuntimeException(lang('Api.deleteError'));
            }
            return true;
        });
    }

    /**
     * Optional hook for child services to apply global criteria (e.g. security filters)
     * Note: Currently receives the underlying object (e.g. CI Model) for backwards compatibility.
     */
    protected function applyBaseCriteria(object $model): void
    {
        // Default: no criteria
    }

    /**
     * Optional hook for child services to apply/modify query options (filtering, sorting, etc.)
     */
    protected function applyQueryOptions(array $criteria): array
    {
        return $criteria; // Default: pass through unmodified
    }

    /**
     * Map a model entity to its corresponding Response DTO
     */
    protected function mapToResponse(object $entity): DataTransferObjectInterface
    {
        return $this->responseMapper->map($entity);
    }
}
