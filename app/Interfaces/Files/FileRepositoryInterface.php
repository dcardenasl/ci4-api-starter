<?php

declare(strict_types=1);

namespace App\Interfaces\Files;

use App\Interfaces\Core\RepositoryInterface;

/**
 * File Repository Interface
 */
interface FileRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a file by its stored name
     */
    public function findByStoredName(string $storedName): ?object;

    /**
     * Count files by user
     */
    public function countByUser(int $userId): int;
}
