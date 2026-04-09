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

        $timestamp = strtotime("-{$expiryMinutes} minutes");
        $expiredTime = date('Y-m-d H:i:s', $timestamp !== false ? $timestamp : time());

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
        $timestamp = strtotime("-{$expiryMinutes} minutes");
        $expiredTime = date('Y-m-d H:i:s', $timestamp !== false ? $timestamp : time());
        $tokenHash = \hash_token($token);

        return $this->where('email', $email)
            ->where('created_at >', $expiredTime)
            ->where('token', $tokenHash)
            ->countAllResults() > 0;
    }
}
