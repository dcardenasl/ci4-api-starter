<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Entities\UserEntity;

class UserService
{
    protected $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function getAllUsers(): array
    {
        return $this->userRepository->findAll();
    }

    public function getUserById(int $id): ?UserEntity
    {
        return $this->userRepository->findById($id);
    }

    public function createUser(array $data): ?UserEntity
    {
        // Business logic validation could go here
        $userData = [
            'email' => $data['email'] ?? null,
            'username' => $data['username'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return $this->userRepository->create($userData);
    }

    public function updateUser(int $id, array $data): ?UserEntity
    {
        // Business logic validation could go here
        $userData = [
            'email' => $data['email'] ?? null,
            'username' => $data['username'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return $this->userRepository->update($id, $userData);
    }

    public function deleteUser(int $id): bool
    {
        return $this->userRepository->delete($id);
    }
}
