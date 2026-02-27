<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\DTO\Response\Common\PaginatedResponseDTO;
use App\DTO\SecurityContext;
use App\Exceptions\NotFoundException;
use App\Interfaces\DataTransferObjectInterface;
use App\Libraries\Query\QueryBuilder;
use CodeIgniter\Model;

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
     * @var Model The primary model for this service
     */
    protected \CodeIgniter\Model $model;

    /**
     * @var string The class name of the Response DTO for this service
     */
    protected string $responseDtoClass;

    /**
     * Get a paginated list of resources
     */
    public function index(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        $builder = new QueryBuilder($this->model);

        // Apply global security/business criteria defined by the service
        $this->applyBaseCriteria($this->model);

        // Apply optional query filtering/options
        $this->applyQueryOptions($builder, $request->toArray());

        $requestData = $request->toArray();
        $page = $requestData['page'] ?? 1;
        $perPage = $requestData['perPage'] ?? 20;

        $result = $builder->paginate((int) $page, (int) $perPage);

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
            'perPage' => $result['perPage'],
        ]);
    }

    /**
     * Get a single resource by ID
     */
    public function show(int $id, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        /** @var object|null $entity */
        $entity = $this->model->find($id);

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
        if (!$this->model->find($id)) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }

        return $this->wrapInTransaction(function () use ($id) {
            if (!$this->model->delete($id)) {
                throw new \RuntimeException(lang('Api.deleteError'));
            }
            return true;
        });
    }

    /**
     * Optional hook for child services to apply global criteria (e.g. security filters)
     */
    protected function applyBaseCriteria(Model $model): void
    {
        // Default: no criteria
    }

    /**
     * Optional hook for child services to apply additional query options (filtering, sorting, etc.)
     */
    protected function applyQueryOptions(QueryBuilder $builder, array $data): void
    {
        // Default: no additional options
    }

    /**
     * Map a model entity to its corresponding Response DTO
     */
    protected function mapToResponse(object $entity): DataTransferObjectInterface
    {
        if (!isset($this->responseDtoClass) || !class_exists($this->responseDtoClass)) {
            throw new \RuntimeException(lang('Api.responseDtoNotDefined', [static::class]));
        }

        return ($this->responseDtoClass)::fromArray($entity->toArray());
    }
}
