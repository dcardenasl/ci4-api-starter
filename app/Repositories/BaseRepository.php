<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\Core\RepositoryInterface;
use App\Libraries\Query\QueryBuilder;
use CodeIgniter\Model;

/**
 * Base Repository
 *
 * Implements the Repository pattern by wrapping a CodeIgniter Model.
 * Encapsulates the QueryBuilder logic, keeping Services completely
 * decoupled from database-specific mechanisms.
 */
abstract class BaseRepository implements RepositoryInterface
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function errors(): array
    {
        return method_exists($this->model, "errors") ? $this->model->errors() : [];
    }

    public function find(int $id): ?object
    {
        /** @var object|null $result */
        $result = $this->model->find($id);
        return $result;
    }

    public function findAll(int $limit = 0, int $offset = 0): array
    {
        return $this->model->findAll($limit, $offset);
    }

    public function insert(array|object $data, bool $returnID = true): int|string|bool
    {
        return $this->model->insert($data, $returnID);
    }

    public function update(int|string|array $id = null, array|object|null $data = null): bool
    {
        return $this->model->update($id, $data);
    }

    public function delete(int|string|array $id = null, bool $purge = false): bool
    {
        return $this->model->delete($id, $purge);
    }

    public function restore(int $id, array $data = []): bool
    {
        // Clear the soft-delete timestamp
        $data['deleted_at'] = null;

        // Use the query builder directly to avoid model-level soft-delete filters during the update.
        // This is the most reliable way to restore a record in CodeIgniter 4.
        return $this->model->builder()
            ->where('id', $id)
            ->update($data);
    }

    public function where(string|array $key, mixed $value = null, ?bool $escape = null): static
    {
        $this->model->where($key, $value, $escape);
        return $this;
    }

    public function orderBy(string $orderBy, string $direction = '', ?bool $escape = null): static
    {
        $this->model->orderBy($orderBy, $direction, $escape);
        return $this;
    }

    public function paginateCriteria(array $criteria, int $page = 1, int $perPage = 20, ?callable $baseCriteria = null): array
    {
        $builder = new QueryBuilder($this->model);

        if ($baseCriteria !== null) {
            $baseCriteria($this->model);
        }

        // Apply filters
        if (!empty($criteria['filter']) && is_array($criteria['filter'])) {
            $builder->filter($criteria['filter']);
        }

        // Apply sort
        if (!empty($criteria['sort']) && is_string($criteria['sort'])) {
            $builder->sort($criteria['sort']);
        }

        // Apply search
        if (!empty($criteria['search']) && is_string($criteria['search'])) {
            $builder->search($criteria['search']);
        }

        return $builder->paginate($page, $perPage);
    }

    /**
     * Get the underlying model instance (use with caution)
     */
    public function getModel(): Model
    {
        return $this->model;
    }
}
