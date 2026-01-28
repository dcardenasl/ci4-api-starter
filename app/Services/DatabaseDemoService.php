<?php

namespace App\Services;

class DatabaseDemoService
{
    private static $users = [];
    private static $nextId = 1;

    public static function initialize()
    {
        if (empty(self::$users)) {
            self::$users = [
                1 => [
                    'id' => 1,
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password123
                    'role' => 'user',
                    'status' => 'active',
                    'created_at' => '2024-01-01 00:00:00',
                    'updated_at' => '2024-01-01 00:00:00'
                ],
                2 => [
                    'id' => 2,
                    'name' => 'Jane Smith',
                    'email' => 'jane@example.com',
                    'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password123
                    'role' => 'admin',
                    'status' => 'active',
                    'created_at' => '2024-01-02 00:00:00',
                    'updated_at' => '2024-01-02 00:00:00'
                ],
                3 => [
                    'id' => 3,
                    'name' => 'Bob Wilson',
                    'email' => 'bob@example.com',
                    'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password123
                    'role' => 'moderator',
                    'status' => 'active',
                    'created_at' => '2024-01-03 00:00:00',
                    'updated_at' => '2024-01-03 00:00:00'
                ]
            ];
            
            self::$nextId = 4;
        }
    }

    public static function find($id)
    {
        self::initialize();
        return self::$users[$id] ?? null;
    }

    public static function findAll()
    {
        self::initialize();
        return array_values(self::$users);
    }

    public static function insert($data)
    {
        self::initialize();
        
        $user = [
            'id' => self::$nextId++,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'] ?? 'password123', PASSWORD_DEFAULT),
            'role' => $data['role'] ?? 'user',
            'status' => $data['status'] ?? 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        self::$users[$user['id']] = $user;
        
        return $user['id'];
    }

    public static function update($id, $data)
    {
        self::initialize();
        
        if (isset(self::$users[$id])) {
            self::$users[$id] = array_merge(self::$users[$id], $data, [
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            return true;
        }
        
        return false;
    }

    public static function delete($id)
    {
        self::initialize();
        
        if (isset(self::$users[$id])) {
            unset(self::$users[$id]);
            return true;
        }
        
        return false;
    }

    public static function getLastInsertId()
    {
        self::initialize();
        return self::$nextId - 1;
    }

    public static function findByEmail($email)
    {
        self::initialize();
        
        foreach (self::$users as $user) {
            if ($user['email'] === $email) {
                return $user;
            }
        }
        
        return null;
    }

    public static function findActive()
    {
        self::initialize();
        
        return array_values(array_filter(self::$users, function($user) {
            return $user['status'] === 'active';
        }));
    }

    public static function findByRole($role)
    {
        self::initialize();
        
        return array_values(array_filter(self::$users, function($user) use ($role) {
            return $user['role'] === $role;
        }));
    }
}