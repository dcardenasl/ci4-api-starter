<?php

namespace App\Repositories;

use App\Models\UserModel;

class UserRepository
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function findUser($id)
    {
        // Use demo method to avoid database connection
        $allUsers = $this->userModel->findAllDemo();
        
        foreach ($allUsers as $user) {
            if ($user['id'] == $id) {
                unset($user['password']);
                return $user;
            }
        }
        
        return null;
    }

    public function getAllUsers()
    {
        $users = $this->userModel->findAllDemo();
        
        // Remove passwords from all users
        return array_map(function($user) {
            unset($user['password']);
            return $user;
        }, $users);
    }

    public function insertUser($data)
    {
        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'] ?? 'user',
            'status' => $data['status'] ?? 'active'
        ];
        
        $insertId = $this->userModel->insert($userData);
        
        return $insertId ? $this->userModel->getInsertID() : false;
    }

    public function updateUser($id, $data)
    {
        // Remove sensitive fields that shouldn't be updated here
        $updateData = array_intersect_key($data, array_flip([
            'name', 'email', 'role', 'status'
        ]));
        
        if (!empty($updateData)) {
            return $this->userModel->update($id, $updateData);
        }
        
        return false;
    }

    public function deleteUser($id)
    {
        // Use soft delete instead of hard delete
        return $this->userModel->softDelete($id);
    }

    // Additional repository methods
    public function findByEmail($email)
    {
        return $this->userModel->findByEmail($email);
    }

    public function findActiveUsers()
    {
        $users = $this->userModel->findActive();
        
        return array_map(function($user) {
            unset($user['password']);
            return $user;
        }, $users);
    }

    public function findByRole($role)
    {
        $users = $this->userModel->findByRole($role);
        
        return array_map(function($user) {
            unset($user['password']);
            return $user;
        }, $users);
    }

    public function verifyPassword($email, $password)
    {
        $user = $this->userModel->findByEmail($email);
        
        if (!$user) {
            return false;
        }
        
        return $this->userModel->verifyPassword($password, $user['password']);
    }
}