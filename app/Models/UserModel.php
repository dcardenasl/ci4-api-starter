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
        'email',
        'first_name',
        'last_name',
        'password',
        'role',
        'status',
        'approved_at',
        'approved_by',
        'invited_at',
        'invited_by',
        'oauth_provider',
        'oauth_provider_id',
        'avatar_url',
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
            'rules'  => 'required|valid_email_idn|max_length[255]|is_unique[users.email,id,{id}]',
            'errors' => [
                'required'    => '{field} is required',
                'valid_email_idn' => 'Please provide a valid email',
                'is_unique'   => 'This email is already registered',
            ],
        ],
        'first_name' => [
            'rules'  => 'permit_empty|string|max_length[100]',
            'errors' => [
                'max_length' => 'First name cannot exceed {param} characters',
            ],
        ],
        'last_name' => [
            'rules'  => 'permit_empty|string|max_length[100]',
            'errors' => [
                'max_length' => 'Last name cannot exceed {param} characters',
            ],
        ],
        'oauth_provider' => [
            'rules'  => 'permit_empty|in_list[google,github]',
            'errors' => [
                'in_list' => 'OAuth provider is not supported',
            ],
        ],
        'oauth_provider_id' => [
            'rules'  => 'permit_empty|string|max_length[255]',
            'errors' => [
                'max_length' => 'OAuth provider id cannot exceed {param} characters',
            ],
        ],
        'avatar_url' => [
            'rules'  => 'permit_empty|valid_url|max_length[255]',
            'errors' => [
                'valid_url'        => 'Avatar URL must be a valid URL',
                'max_length'       => 'Avatar URL cannot exceed {param} characters',
            ],
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Search and filter configuration
    protected array $searchableFields = ['email', 'first_name', 'last_name'];
    protected array $filterableFields = ['role', 'status', 'email', 'created_at', 'id', 'first_name', 'last_name'];
    protected array $sortableFields = ['id', 'email', 'created_at', 'role', 'status', 'first_name', 'last_name'];

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
