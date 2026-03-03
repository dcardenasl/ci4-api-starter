<?php

declare(strict_types=1);

namespace App\Repositories\Files;

use App\Interfaces\Files\FileRepositoryInterface;
use App\Repositories\BaseRepository;

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
}
