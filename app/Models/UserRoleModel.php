<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class UserRoleModel extends Model
{
    protected $table         = 'user_roles';
    protected $primaryKey    = 'user_id';
    protected $returnType    = 'array';
    protected $useAutoIncrement = false;
    protected $useTimestamps = false;
    protected $allowedFields = ['user_id', 'role_id', 'assigned_at', 'assigned_by_user_id'];
}
