<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\RoleEntity;
use App\Traits\Filterable;
use App\Traits\Searchable;

class RoleModel extends BaseAuditableModel
{
    use Filterable;
    use Searchable;

    /** @var string */
    protected $table            = 'roles';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = RoleEntity::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    /** @var list<string> */
    protected $allowedFields = [
        'application_id',
        'code',
        'name',
        'description',
        'is_system',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** @var array<int, string> */
    protected array $searchableFields = ['code', 'name', 'description'];
    /** @var array<int, string> */
    protected array $filterableFields = ['application_id', 'is_system', 'code', 'created_at'];
    /** @var array<int, string> */
    protected array $sortableFields = ['id', 'code', 'name', 'created_at'];
}
