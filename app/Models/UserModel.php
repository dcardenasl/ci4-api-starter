<?php

namespace App\Models;

use App\Entities\UserEntity;
use App\Traits\Filterable;
use App\Traits\Searchable;
use CodeIgniter\Model;

class UserModel extends Model
{
    use Filterable;
    use Searchable;
    // Configuración de tabla
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = UserEntity::class;

    // Soft deletes (borrado lógico)
    protected $useSoftDeletes   = true;

    // Protección contra mass assignment
    protected $protectFields    = true;
    protected $allowedFields    = [
        'username',
        'email',
        'password',
        'role',
        'email_verification_token',
        'verification_token_expires',
        'email_verified_at',
    ];

    // Gestión automática de timestamps
    protected $useTimestamps      = true;
    protected $dateFormat         = 'datetime';
    protected $createdField       = 'created_at';
    protected $updatedField       = 'updated_at';
    protected $deletedField       = 'deleted_at';

    // Reglas de validación (integridad de datos)
    protected $validationRules = [
        'email' => [
            'rules'  => 'required|valid_email|max_length[255]|is_unique[users.email,id,{id}]',
            'errors' => [
                'required'    => '{field} is required',
                'valid_email' => 'Please provide a valid email',
                'is_unique'   => 'This email is already registered',
            ],
        ],
        'username' => [
            'rules'  => 'required|alpha_numeric|min_length[3]|max_length[100]|is_unique[users.username,id,{id}]',
            'errors' => [
                'required'      => '{field} is required',
                'alpha_numeric' => 'Username can only contain letters and numbers',
                'min_length'    => 'Username must be at least {param} characters',
                'is_unique'     => 'This username is already taken',
            ],
        ],
        'password' => [
            'rules'  => 'required|min_length[8]|max_length[128]|regex_match[/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/]',
            'errors' => [
                'required'    => '{field} is required',
                'min_length'  => 'Password must be at least {param} characters',
                'max_length'  => 'Password must not exceed {param} characters',
                'regex_match' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character',
            ],
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Search and filter configuration
    protected array $searchableFields = ['username', 'email'];
    protected array $filterableFields = ['role', 'email', 'created_at', 'id', 'username'];
    protected array $sortableFields = ['id', 'username', 'email', 'created_at', 'role'];

    // Callbacks para procesamiento adicional
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];
}
