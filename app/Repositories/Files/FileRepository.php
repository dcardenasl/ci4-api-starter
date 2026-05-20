<?php

declare(strict_types=1);

namespace App\Repositories\Files;

use App\Interfaces\Files\FileRepositoryInterface;
use dcardenasl\Ci4ApiCore\Repositories\BaseRepository;

/**
 * File Repository (Implementation)
 */
class FileRepository extends BaseRepository implements FileRepositoryInterface
{
    public function findByStoredName(string $storedName): ?object
    {
        return $this->model->where('stored_name', $storedName)->first();
    }

    public function countByUser(int $userId): int
    {
        return $this->model->where('user_id', $userId)->countAllResults();
    }

    public function findIncludingTrashed(int $id): ?object
    {
        /** @var object|null $result */
        $result = $this->model->withDeleted()->find($id);

        return $result;
    }

    public function purge(int $id): bool
    {
        return (bool) $this->model->delete($id, true);
    }

    public function restore(int|string $id, array $data = []): bool
    {
        $data['deleted_by_user_id'] = null;

        return parent::restore($id, $data);
    }

    public function findByUrl(string $url): ?object
    {
        /** @var object|null $result */
        $result = $this->model->withDeleted()->where('url', $url)->first();

        return $result;
    }
}
