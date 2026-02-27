<?php

declare(strict_types=1);

namespace App\Services\Auth\Support;

/**
 * Auth User Mapper
 *
 * Handles the conversion of User entities into data structures
 * required by authentication responses.
 */
class AuthUserMapper
{
    /**
     * Build standard user data for successful login
     */
    public function mapAuthenticated(object $user): array
    {
        return [
            'id' => (int) ($user->id ?? 0),
            'email' => (string) ($user->email ?? ''),
            'first_name' => (string) ($user->first_name ?? ''),
            'last_name' => (string) ($user->last_name ?? ''),
            'avatar_url' => (string) ($user->avatar_url ?? ''),
            'role' => (string) ($user->role ?? 'user'),
        ];
    }

    /**
     * Build minimal user data for pending accounts
     */
    public function mapPending(object $user): array
    {
        return [
            'id' => (int) ($user->id ?? 0),
            'email' => (string) ($user->email ?? ''),
            'status' => (string) ($user->status ?? ''),
        ];
    }
}
