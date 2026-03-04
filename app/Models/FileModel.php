<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\FileEntity;
use App\Traits\Filterable;
use App\Traits\Searchable;

/**
 * File Model
 *
 * Manages file metadata in the database
 */
class FileModel extends BaseAuditableModel
{
    use Filterable;
    use Searchable;
    protected $table = 'files';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = FileEntity::class;
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'user_id',
        'original_name',
        'stored_name',
        'mime_type',
        'size',
        'storage_driver',
        'path',
        'url',
        'metadata',
        'uploaded_at',
    ];

    // Timestamps
    protected $useTimestamps = false; // Using uploaded_at instead
    protected $dateFormat = 'datetime';

    /**
     * Validation rules
     *
     * @var array<string, string>
     */
    protected $validationRules = [
        'user_id' => 'required|integer',
        'original_name' => 'required|max_length[255]',
        'stored_name' => 'required|max_length[255]',
        'mime_type' => 'required|max_length[100]',
        'size' => 'required|integer',
        'storage_driver' => 'required|max_length[50]',
        'path' => 'required|max_length[500]',
    ];

    /**
     * Validation messages
     *
     * @var array<string, array<string, string>>
     */
    protected $validationMessages = [
        'user_id' => [
            'required' => 'InputValidation.common.userIdRequired',
            'integer' => 'InputValidation.common.userIdMustBeInteger',
        ],
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Query capabilities
    /** @var array<int, string> */
    protected array $searchableFields = ['original_name', 'mime_type'];
    /** @var array<int, string> */
    protected array $filterableFields = ['user_id', 'mime_type', 'size', 'uploaded_at', 'storage_driver'];
    /** @var array<int, string> */
    protected array $sortableFields = ['id', 'user_id', 'original_name', 'size', 'uploaded_at', 'mime_type'];

}
