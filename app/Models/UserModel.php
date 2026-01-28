<?php

namespace App\Models;

use App\Services\DatabaseDemoService;
use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'name',
        'email', 
        'password',
        'role',
        'status',
        'created_at',
        'updated_at'
    ];
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $validationRules = [
        'name' => 'required|min_length[3]|max_length[100]',
        'email' => 'required|valid_email|is_unique[users.email]',
        'password' => 'required|min_length[8]',
        'role' => 'required|in_list[user,admin,moderator]',
        'status' => 'required|in_list[active,inactive,suspended]'
    ];
    protected $validationMessages = [
        'name' => [
            'required' => 'El nombre es obligatorio',
            'min_length' => 'El nombre debe tener al menos 3 caracteres',
            'max_length' => 'El nombre no puede exceder 100 caracteres'
        ],
        'email' => [
            'required' => 'El email es obligatorio',
            'valid_email' => 'El email no es válido',
            'is_unique' => 'El email ya está registrado'
        ],
        'password' => [
            'required' => 'La contraseña es obligatoria',
            'min_length' => 'La contraseña debe tener al menos 8 caracteres'
        ],
        'role' => [
            'required' => 'El rol es obligatorio',
            'in_list' => 'El rol debe ser: user, admin, o moderator'
        ],
        'status' => [
            'required' => 'El estado es obligatorio',
            'in_list' => 'El estado debe ser: active, inactive, o suspended'
        ]
    ];
    protected $skipValidation = false;
    
    // Hash password before saving
    protected $beforeInsert = ['hashPassword'];
    protected $beforeUpdate = ['hashPassword'];
    
    protected function hashPassword(array $data)
    {
        if (isset($data['data']['password'])) {
            $data['data']['password'] = password_hash($data['data']['password'], PASSWORD_DEFAULT);
        }
        
        return $data;
    }
    
    // Check if we're in demo mode (no database available)
    private function isDemoMode()
    {
        return (ENVIRONMENT === 'development' && 
               !getenv('database.default.database'));
    }
    
    // Override methods for demo mode
    public function find($id = null)
    {
        if ($this->isDemoMode()) {
            return DatabaseDemoService::find($id);
        }
        
        return parent::find($id);
    }
    
    public function findAllDemo(int $limit = 0, int $offset = 0)
    {
        if ($this->isDemoMode()) {
            return DatabaseDemoService::findAll();
        }
        
        return parent::findAll($limit, $offset);
    }
    
    public function insert($data = null, bool $returnID = true)
    {
        if ($this->isDemoMode()) {
            $insertId = DatabaseDemoService::insert($data);
            return $returnID ? $insertId : true;
        }
        
        return parent::insert($data, $returnID);
    }
    
    public function update($id = null, $data = null): bool
    {
        if ($this->isDemoMode()) {
            return DatabaseDemoService::update($id, $data);
        }
        
        return parent::update($id, $data);
    }
    
    public function getInsertID()
    {
        if ($this->isDemoMode()) {
            return DatabaseDemoService::getLastInsertId();
        }
        
        return parent::getInsertID();
    }
    
    // Get user by email (for authentication)
    public function findByEmail($email)
    {
        if ($this->isDemoMode()) {
            return DatabaseDemoService::findByEmail($email);
        }
        
        return $this->where('email', $email)->first();
    }
    
    // Get active users only
    public function findActive($id = null)
    {
        if ($this->isDemoMode()) {
            if ($id === null) {
                return DatabaseDemoService::findActive();
            }
            $user = DatabaseDemoService::find($id);
            return ($user && $user['status'] === 'active') ? $user : null;
        }
        
        if ($id === null) {
            return $this->where('status', 'active')->findAll();
        }
        
        return $this->where(['id' => $id, 'status' => 'active'])->first();
    }
    
    // Get users by role
    public function findByRole($role)
    {
        if ($this->isDemoMode()) {
            return DatabaseDemoService::findByRole($role);
        }
        
        return $this->where('role', $role)->findAll();
    }
    
    // Verify password
    public function verifyPassword($plainPassword, $hashedPassword)
    {
        return password_verify($plainPassword, $hashedPassword);
    }
    
    // Get user without password
    public function getUserWithoutPassword($id)
    {
        $user = $this->find($id);
        
        if ($user) {
            unset($user['password']);
        }
        
        return $user;
    }
    
    // Soft delete (change status to inactive)
    public function softDelete($id)
    {
        if ($this->isDemoMode()) {
            return DatabaseDemoService::update($id, ['status' => 'inactive']);
        }
        
        return $this->update($id, ['status' => 'inactive']);
    }
}