<?php

declare(strict_types=1);

namespace App\Models\Files;

use CodeIgniter\Model;

class FileReferenceModel extends Model
{
    protected $table      = 'file_references';
    protected $primaryKey = 'id';

    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'file_id',
        'resource_type',
        'resource_id',
        'role',
        'label',
        'created_at',
    ];

    protected $useTimestamps = false;
}
