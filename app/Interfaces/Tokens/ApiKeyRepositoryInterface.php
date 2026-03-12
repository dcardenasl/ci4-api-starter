<?php

declare(strict_types=1);

namespace App\Interfaces\Tokens;

use App\Entities\ApiKeyEntity;
use App\Interfaces\Core\RepositoryInterface;

interface ApiKeyRepositoryInterface extends RepositoryInterface
{
    public function findByHash(string $hash): ?ApiKeyEntity;
}
