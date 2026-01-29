<?php

namespace App\Models;

use CodeIgniter\Model;

class PasswordResetModel extends Model
{
    protected $table = 'password_resets';
    protected $allowedFields = ['email', 'token', 'created_at'];
    protected $useTimestamps = false;

    /**
     * Clean up expired reset tokens
     *
     * @param int $expiryMinutes
     * @return void
     */
    public function cleanExpired(int $expiryMinutes = 60): void
    {
        $expiredTime = date('Y-m-d H:i:s', strtotime("-{$expiryMinutes} minutes"));

        $this->where('created_at <', $expiredTime)->delete();
    }

    /**
     * Check if token is valid and not expired
     *
     * @param string $email
     * @param string $token
     * @param int $expiryMinutes
     * @return bool
     */
    public function isValidToken(string $email, string $token, int $expiryMinutes = 60): bool
    {
        $expiredTime = date('Y-m-d H:i:s', strtotime("-{$expiryMinutes} minutes"));

        $reset = $this->where('email', $email)
            ->where('token', $token)
            ->where('created_at >', $expiredTime)
            ->first();

        return $reset !== null;
    }
}
