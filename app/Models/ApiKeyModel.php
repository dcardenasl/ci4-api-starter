<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\ApiKeyEntity;
use App\Traits\Auditable;
use App\Traits\Filterable;
use App\Traits\Searchable;
use CodeIgniter\Model;

class ApiKeyModel extends Model
{
    use Auditable;
    use Filterable;
    use Searchable;

    protected $table            = 'api_keys';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = ApiKeyEntity::class;

    // No soft deletes — hard-delete API keys when revoked
    protected $useSoftDeletes   = false;

    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'key_prefix',
        'key_hash',
        'is_active',
        'rate_limit_requests',
        'rate_limit_window',
        'user_rate_limit',
        'ip_rate_limit',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'name' => [
            'rules'  => 'required|string|max_length[100]',
            'errors' => [
                'required'   => 'API key name is required',
                'max_length' => 'Name cannot exceed {param} characters',
            ],
        ],
        'key_prefix' => [
            'rules'  => 'required|string|max_length[12]',
            'errors' => [
                'required'   => 'Key prefix is required',
                'max_length' => 'Key prefix cannot exceed {param} characters',
            ],
        ],
        'key_hash' => [
            'rules'  => 'required|string|max_length[64]',
            'errors' => [
                'required'   => 'Key hash is required',
                'max_length' => 'Key hash cannot exceed {param} characters',
            ],
        ],
        'rate_limit_requests' => [
            'rules'  => 'permit_empty|integer|greater_than[0]',
            'errors' => [
                'integer'      => 'rate_limit_requests must be an integer',
                'greater_than' => 'rate_limit_requests must be greater than 0',
            ],
        ],
        'rate_limit_window' => [
            'rules'  => 'permit_empty|integer|greater_than[0]',
            'errors' => [
                'integer'      => 'rate_limit_window must be an integer',
                'greater_than' => 'rate_limit_window must be greater than 0',
            ],
        ],
        'user_rate_limit' => [
            'rules'  => 'permit_empty|integer|greater_than[0]',
            'errors' => [
                'integer'      => 'user_rate_limit must be an integer',
                'greater_than' => 'user_rate_limit must be greater than 0',
            ],
        ],
        'ip_rate_limit' => [
            'rules'  => 'permit_empty|integer|greater_than[0]',
            'errors' => [
                'integer'      => 'ip_rate_limit must be an integer',
                'greater_than' => 'ip_rate_limit must be greater than 0',
            ],
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Search and filter configuration
    protected array $searchableFields  = ['name', 'key_prefix'];
    protected array $filterableFields  = ['name', 'is_active', 'created_at'];
    protected array $sortableFields    = ['id', 'name', 'is_active', 'created_at'];

    /**
     * Find an API key entity by its raw SHA-256 hash.
     *
     * @param string $hash SHA-256 hash of the raw key
     * @return ApiKeyEntity|null
     */
    public function findByHash(string $hash): ?ApiKeyEntity
    {
        /** @var ApiKeyEntity|null */
        return $this->where('key_hash', $hash)->first();
    }

    public function __construct()
    {
        parent::__construct();
        $this->initAuditable();
    }
}
