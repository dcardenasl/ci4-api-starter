<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\Searchable;
use CodeIgniter\Model;

/**
 * Audit Log Model
 *
 * Stores audit trail of all data changes
 */
class AuditLogModel extends Model
{
    use Filterable;
    use Searchable;

    protected $table = 'audit_logs';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    // No timestamps (using custom created_at)
    protected $useTimestamps = false;
    protected $dateFormat = 'datetime';

    // Validation rules
    protected $validationRules = [
        'action' => 'required|max_length[50]',
        'entity_type' => 'required|max_length[50]',
        'ip_address' => 'required|max_length[45]',
    ];

    protected $validationMessages = [];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Filtering and searching
    protected array $filterableFields = ['user_id', 'action', 'entity_type', 'entity_id', 'created_at'];
    protected array $searchableFields = ['action', 'entity_type'];
    protected array $sortableFields = ['id', 'user_id', 'action', 'entity_type', 'entity_id', 'created_at'];

    /**
     * Get audit logs for an entity
     *
     * @param string $entityType Entity type (e.g., 'user', 'file')
     * @param int $entityId Entity ID
     * @return array
     */
    public function getByEntity(string $entityType, int $entityId): array
    {
        return $this->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get audit logs for a user
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getByUser(int $userId, int $limit = 50): array
    {
        return $this->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->findAll($limit);
    }

    /**
     * Get recent audit logs
     *
     * @param int $limit
     * @return array
     */
    public function getRecent(int $limit = 100): array
    {
        return $this->orderBy('created_at', 'DESC')
            ->findAll($limit);
    }
}
