<?php

declare(strict_types=1);

namespace App\Repositories\Users;

use App\Interfaces\Users\UserRepositoryInterface;
use dcardenasl\Ci4ApiCore\Repositories\BaseRepository;
use dcardenasl\Ci4ApiCore\Security\Hasher;

/**
 * User Repository (Implementation)
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function findByEmail(string $email): ?object
    {
        return $this->model->where('email', $email)->first();
    }

    public function findByEmailWithDeleted(string $email): ?object
    {
        return $this->model->withDeleted()->where('email', $email)->first();
    }

    public function findByVerificationToken(string $token): ?object
    {
        $tokenHash = Hasher::token($token);
        return $this->model->where('email_verification_token', $tokenHash)->first();
    }
}
