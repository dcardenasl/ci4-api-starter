<?php

namespace App\Controllers;

use App\Services\UserService;
use CodeIgniter\API\ResponseTrait;

class UserController extends BaseController
{
    use ResponseTrait;

    protected $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    public function index()
    {
        $users = $this->userService->getAllUsers();
        
        return $this->respond([
            'success' => true,
            'data' => $users,
            'error' => null
        ]);
    }

    public function show($id = null)
    {
        $user = $this->userService->getUserById($id);
        
        if (!$user) {
            return $this->respond([
                'success' => false,
                'data' => null,
                'error' => 'User not found'
            ], 404);
        }

        return $this->respond([
            'success' => true,
            'data' => $user,
            'error' => null
        ]);
    }

    public function create()
    {
        $data = $this->request->getJSON();
        
        if (!$data) {
            return $this->respond([
                'success' => false,
                'data' => null,
                'error' => 'Invalid JSON data'
            ], 400);
        }

        $user = $this->userService->createUser($data);
        
        if (!$user) {
            return $this->respond([
                'success' => false,
                'data' => null,
                'error' => 'Failed to create user'
            ], 400);
        }

        return $this->respond([
            'success' => true,
            'data' => $user,
            'error' => null
        ], 201);
    }

    public function update($id = null)
    {
        $data = $this->request->getJSON();
        
        if (!$data) {
            return $this->respond([
                'success' => false,
                'data' => null,
                'error' => 'Invalid JSON data'
            ], 400);
        }

        $user = $this->userService->updateUser($id, $data);
        
        if (!$user) {
            return $this->respond([
                'success' => false,
                'data' => null,
                'error' => 'User not found or update failed'
            ], 404);
        }

        return $this->respond([
            'success' => true,
            'data' => $user,
            'error' => null
        ]);
    }

    public function delete($id = null)
    {
        $deleted = $this->userService->deleteUser($id);
        
        if (!$deleted) {
            return $this->respond([
                'success' => false,
                'data' => null,
                'error' => 'User not found or delete failed'
            ], 404);
        }

        return $this->respond([
            'success' => true,
            'data' => null,
            'error' => null
        ]);
    }
}