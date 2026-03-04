<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\DemoproductEntity;
use App\Traits\Filterable;
use App\Traits\Searchable;

class DemoproductModel extends BaseAuditableModel
{
    use Filterable;
    use Searchable;

    protected $table = 'demoproducts';
    protected $primaryKey = 'id';
    protected $returnType = DemoproductEntity::class;
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'name',
        'deleted_at',
    ];

    /** @var array<int, string> */
    protected array $searchableFields = ['name'];
    /** @var array<int, string> */
    protected array $filterableFields = ['id', 'name', 'created_at'];
    /** @var array<int, string> */
    protected array $sortableFields = ['id', 'name', 'created_at'];

    protected $validationRules = [
        'name' => [
            'rules' => 'required|max_length[255]',
            'errors' => [
                'required' => 'InputValidation.common.nameRequired',
                'max_length' => 'InputValidation.common.nameMaxLength',
            ],
        ],
    ];

}
