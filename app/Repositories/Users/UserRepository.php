<?php

declare(strict_types=1);

namespace App\Repositories\Users;

use App\Interfaces\Users\UserRepositoryInterface;
use App\Repositories\BaseRepository;

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
        return $this->model->where('email_verification_token', $token)->first();
    }
}
