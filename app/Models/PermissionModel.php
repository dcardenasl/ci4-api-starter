<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\PermissionEntity;
use App\Traits\Filterable;
use App\Traits\Searchable;

class PermissionModel extends BaseAuditableModel
{
    use Filterable;
    use Searchable;

    /** @var string */
    protected $table            = 'permissions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = PermissionEntity::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    /** @var list<string> */
    protected $allowedFields = [
        'application_id',
        'code',
        'resource',
        'action',
        'description',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** @var array<int, string> */
    protected array $searchableFields = ['code', 'resource', 'action', 'description'];
    /** @var array<int, string> */
    protected array $filterableFields = ['application_id', 'resource', 'action', 'code', 'created_at'];
    /** @var array<int, string> */
    protected array $sortableFields = ['id', 'code', 'resource', 'action', 'created_at'];
}
