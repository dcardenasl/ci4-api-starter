<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Token Blacklist Model
 *
 * Manages blacklisted JWT tokens (revoked access tokens)
 */
class TokenBlacklistModel extends Model
{
    protected $table = 'token_blacklist';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'token_jti',
        'expires_at',
        'created_at',
    ];

    // No timestamps (using custom created_at)
    protected $useTimestamps = false;
    protected $dateFormat = 'datetime';

    // Validation rules
    protected $validationRules = [
        'token_jti' => 'required|max_length[255]|is_unique[token_blacklist.token_jti]',
        'expires_at' => 'required|valid_date',
    ];

    protected $validationMessages = [];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    /**
     * Check if a token JTI is blacklisted
     *
     * Case-sensitive comparison to ensure token security.
     *
     * @param string $jti Token JTI
     * @return bool
     */
    public function isBlacklisted(string $jti): bool
    {
        // Use BINARY comparison for case-sensitive token matching
        $record = $this->where("BINARY token_jti = BINARY '{$this->db->escapeString($jti)}'", null, false)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->first();

        return $record !== null;
    }

    /**
     * Check if a token JTI exists in the blacklist (regardless of expiration)
     *
     * @param string $jti Token JTI
     * @return bool
     */
    public function existsByJti(string $jti): bool
    {
        $record = $this->where("BINARY token_jti = BINARY '{$this->db->escapeString($jti)}'", null, false)
            ->first();

        return $record !== null;
    }

    /**
     * Add token JTI to blacklist
     *
     * @param string $jti Token JTI
     * @param int $expiresAt Token expiration timestamp
     * @return bool
     */
    public function addToBlacklist(string $jti, int $expiresAt): bool
    {
        if ($this->existsByJti($jti)) {
            return true;
        }

        $id = $this->insert([
            'token_jti' => $jti,
            'expires_at' => date('Y-m-d H:i:s', $expiresAt),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $id !== false;
    }

    /**
     * Delete expired blacklisted tokens
     *
     * @return int Number of deleted tokens
     */
    public function deleteExpired(): int
    {
        $builder = $this->builder();
        $builder->where('expires_at <', date('Y-m-d H:i:s'));
        $builder->delete();

        return $this->db->affectedRows();
    }
}
