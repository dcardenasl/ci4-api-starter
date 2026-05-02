<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\AppUserMembershipEntity;
use App\Traits\Filterable;

class AppUserMembershipModel extends BaseAuditableModel
{
    use Filterable;

    /** @var string */
    protected $table            = 'app_user_memberships';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = AppUserMembershipEntity::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    /** @var list<string> */
    protected $allowedFields = [
        'user_id',
        'application_id',
        'status',
        'invited_at',
        'accepted_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** @var array<int, string> */
    protected array $filterableFields = ['user_id', 'application_id', 'status', 'created_at'];
    /** @var array<int, string> */
    protected array $sortableFields = ['id', 'user_id', 'application_id', 'created_at'];
}
