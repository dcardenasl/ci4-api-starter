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

    /**
     * Get all users
     *
     * @param array $data Request data (unused for index)
     * @return array Response with status and data
     */
    public function index(array $data): array
    {
        $users = $this->userRepository->findAll();

        // Convert entities to arrays for JSON serialization
        $usersArray = array_map(function ($user) {
            return $user instanceof UserEntity ? $user->toArray() : $user;
        }, $users);

        return [
            'status' => 'success',
            'data' => $usersArray,
        ];
    }

    /**
     * Get a single user by ID
     *
     * @param array $data Request data containing 'id'
     * @return array Response with status and data
     * @throws \InvalidArgumentException If user not found
     */
    public function show(array $data): array
    {
        if (!isset($data['id'])) {
            return ['errors' => ['id' => 'User ID is required']];
        }

        $id = (int) $data['id'];
        $user = $this->userRepository->findById($id);

        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }

        return [
            'status' => 'success',
            'data' => $user->toArray(),
        ];
    }

    /**
     * Create a new user
     *
     * @param array $data Request data containing user fields
     * @return array Response with status and data or validation errors
     */
    public function store(array $data): array
    {
        // Validate required fields
        $errors = [];

        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        }

        if (empty($data['username'])) {
            $errors['username'] = 'Username is required';
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        // Prepare user data
        $userData = [
            'email' => $data['email'],
            'username' => $data['username'],
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $user = $this->userRepository->create($userData);

        if (!$user) {
            throw new \RuntimeException('Failed to create user');
        }

        return [
            'status' => 'success',
            'data' => $user->toArray(),
        ];
    }

    /**
     * Update an existing user
     *
     * @param array $data Request data containing 'id' and user fields
     * @return array Response with status and data or validation errors
     * @throws \InvalidArgumentException If user not found
     */
    public function update(array $data): array
    {
        // Validate ID
        if (!isset($data['id'])) {
            return ['errors' => ['id' => 'User ID is required']];
        }

        $id = (int) $data['id'];

        // Check if user exists
        $existingUser = $this->userRepository->findById($id);
        if (!$existingUser) {
            throw new \InvalidArgumentException('User not found');
        }

        // Validate at least one field to update
        if (empty($data['email']) && empty($data['username'])) {
            return ['errors' => ['fields' => 'At least one field (email or username) is required']];
        }

        // Prepare update data
        $userData = [
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($data['email'])) {
            $userData['email'] = $data['email'];
        }

        if (!empty($data['username'])) {
            $userData['username'] = $data['username'];
        }

        $user = $this->userRepository->update($id, $userData);

        if (!$user) {
            throw new \RuntimeException('Failed to update user');
        }

        return [
            'status' => 'success',
            'data' => $user->toArray(),
        ];
    }

    /**
     * Delete a user
     *
     * @param array $data Request data containing 'id'
     * @return array Response with status and message
     * @throws \InvalidArgumentException If user not found
     */
    public function destroy(array $data): array
    {
        if (!isset($data['id'])) {
            return ['errors' => ['id' => 'User ID is required']];
        }

        $id = (int) $data['id'];

        // Check if user exists
        $existingUser = $this->userRepository->findById($id);
        if (!$existingUser) {
            throw new \InvalidArgumentException('User not found');
        }

        $deleted = $this->userRepository->delete($id);

        if (!$deleted) {
            throw new \RuntimeException('Failed to delete user');
        }

        return [
            'status' => 'success',
            'message' => 'User deleted successfully',
        ];
    }
}
