<?php

namespace App\Models;

use CodeIgniter\Model;

class PasswordResetModel extends Model
{
    protected $table = 'password_resets';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $allowedFields = ['email', 'token', 'created_at'];
    protected $useTimestamps = false;

    /**
     * Clean up expired reset tokens
     *
     * @param int $expiryMinutes Number of minutes before expiry (0 = delete all)
     * @return void
     */
    public function cleanExpired(int $expiryMinutes = 60): void
    {
        if ($expiryMinutes === 0) {
            // Delete all tokens when expiry is 0
            $this->builder()->truncate();
            return;
        }

        $expiredTime = date('Y-m-d H:i:s', strtotime("-{$expiryMinutes} minutes"));

        $this->where('created_at <', $expiredTime)->delete();
    }

    /**
     * Check if token is valid and not expired
     *
     * Uses hash_equals for constant-time comparison to prevent timing attacks.
     *
     * @param string $email
     * @param string $token
     * @param int $expiryMinutes
     * @return bool
     */
    public function isValidToken(string $email, string $token, int $expiryMinutes = 60): bool
    {
        $expiredTime = date('Y-m-d H:i:s', strtotime("-{$expiryMinutes} minutes"));

        // Retrieve all non-expired tokens for this email and compare using hash_equals
        // to prevent timing-based token enumeration
        $resets = $this->where('email', $email)
            ->where('created_at >', $expiredTime)
            ->findAll();

        foreach ($resets as $reset) {
            $storedToken = is_object($reset) ? $reset->token : ($reset['token'] ?? '');
            if (hash_equals($storedToken, $token)) {
                return true;
            }
        }

        return false;
    }
}
