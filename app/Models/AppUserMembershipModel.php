<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\AppUserMembershipEntity;
use App\Traits\Filterable;
use App\Traits\Searchable;

class AppUserMembershipModel extends BaseAuditableModel
{
    use Filterable;
    use Searchable;

    protected $table = 'app_user_memberships';
    protected $primaryKey = 'id';
    protected $returnType = AppUserMembershipEntity::class;
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;

    protected $allowedFields = ['user_id', 'application_id', 'status', 'invited_at', 'accepted_at'];

    /** @var array<int, string> */
    protected array $searchableFields = [];

    /** @var array<int, string> */
    protected array $filterableFields = ['id', 'user_id', 'application_id', 'status'];

    /** @var array<int, string> */
    protected array $sortableFields = ['id', 'created_at', 'user_id', 'application_id', 'status'];

    protected $validationRules = [
        'user_id' => 'required|integer',
        'application_id' => 'required|integer',
        'status' => 'permit_empty|string|max_length[20]',
    ];
}
