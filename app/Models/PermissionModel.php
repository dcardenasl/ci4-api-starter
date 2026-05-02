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

    protected $table = 'permissions';
    protected $primaryKey = 'id';
    protected $returnType = PermissionEntity::class;
    protected $useSoftDeletes = false;
    protected $useTimestamps = true;

    protected $allowedFields = ['application_id', 'code', 'resource', 'action', 'description'];

    /** @var array<int, string> */
    protected array $searchableFields = ['code'];

    /** @var array<int, string> */
    protected array $filterableFields = ['id', 'application_id', 'resource', 'action'];

    /** @var array<int, string> */
    protected array $sortableFields = ['id', 'created_at', 'application_id', 'code', 'resource', 'action'];

    protected $validationRules = [
        'application_id' => 'required|integer',
        'code' => 'required|string|max_length[100]',
        'resource' => 'required|string|max_length[50]',
        'action' => 'required|string|max_length[50]',
        'description' => 'permit_empty|string',
    ];
}
