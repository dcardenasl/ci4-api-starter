<?php

namespace App\Services;

use App\Repositories\UserRepository;

class UserService
{
    protected $userRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
    }

    public function getAllUsers()
    {
        return $this->userRepository->getAllUsers();
    }

    public function getUserById($id)
    {
        return $this->userRepository->findUser($id);
    }

    public function createUser($data)
    {
        $userData = [
            'name' => $data->name ?? null,
            'email' => $data->email ?? null,
            'role' => $data->role ?? 'user',
            'status' => $data->status ?? 'active'
        ];

        $insertId = $this->userRepository->insertUser($userData);
        
        if ($insertId) {
            return $this->userRepository->findUser($insertId);
        }
        
        return null;
    }

    public function updateUser($id, $data)
    {
        $existingUser = $this->userRepository->findUser($id);
        
        if (!$existingUser) {
            return null;
        }

        $updateData = [];
        
        if (isset($data->name)) {
            $updateData['name'] = $data->name;
        }
        
        if (isset($data->email)) {
            $updateData['email'] = $data->email;
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        $updated = $this->userRepository->updateUser($id, $updateData);
        
        if ($updated) {
            return $this->userRepository->findUser($id);
        }
        
        return null;
    }

    public function deleteUser($id)
    {
        $existingUser = $this->userRepository->findUser($id);
        
        if (!$existingUser) {
            return false;
        }

        return $this->userRepository->deleteUser($id);
    }

    // Additional service methods
    public function getActiveUsers()
    {
        return $this->userRepository->findActiveUsers();
    }

    public function getUsersByRole($role)
    {
        return $this->userRepository->findByRole($role);
    }

    public function authenticateUser($email, $password)
    {
        return $this->userRepository->verifyPassword($email, $password);
    }

    public function getUserByEmail($email)
    {
        return $this->userRepository->findByEmail($email);
    }
}