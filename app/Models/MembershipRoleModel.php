<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class MembershipRoleModel extends Model
{
    /** @var string */
    protected $table            = 'membership_roles';
    protected $primaryKey       = 'membership_id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $protectFields    = true;

    /** @var list<string> */
    protected $allowedFields = ['membership_id', 'role_id'];
}
