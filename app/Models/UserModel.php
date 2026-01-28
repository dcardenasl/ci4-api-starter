<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Entities\UserEntity;

class UserModel extends Model
{
    // Configuración de tabla
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = UserEntity::class;

    // Soft deletes (borrado lógico)
    protected $useSoftDeletes   = true;

    // Protección contra mass assignment
    protected $protectFields    = true;
    protected $allowedFields    = ['username', 'email'];

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
                'required'    => 'El email es obligatorio',
                'valid_email' => 'Debe proporcionar un email válido',
                'is_unique'   => 'Este email ya está registrado',
            ],
        ],
        'username' => [
            'rules'  => 'required|alpha_numeric|min_length[3]|max_length[100]|is_unique[users.username,id,{id}]',
            'errors' => [
                'required'      => 'El nombre de usuario es obligatorio',
                'alpha_numeric' => 'El nombre de usuario solo puede contener letras y números',
                'min_length'    => 'El nombre de usuario debe tener al menos 3 caracteres',
                'is_unique'     => 'Este nombre de usuario ya está en uso',
            ],
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

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
