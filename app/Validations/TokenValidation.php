<?php

declare(strict_types=1);

namespace App\Validations;

/**
 * Token Validation
 *
 * Validation rules for token-related actions (refresh, revoke).
 */
class TokenValidation extends BaseValidation
{
    /**
     * {@inheritDoc}
     */
    public function getRules(string $action): array
    {
        return match ($action) {
            'refresh' => [
                'refresh_token' => 'required|string|min_length[10]',
            ],

            'revoke' => [
                'refresh_token' => 'required|string|min_length[10]',
            ],

            'revoke_all' => [
                'user_id' => 'permit_empty|is_natural_no_zero',
            ],

            default => [],
        };
    }

    /**
     * {@inheritDoc}
     */
    public function getMessages(string $action): array
    {
        return match ($action) {
            'refresh', 'revoke' => [
                'refresh_token.required'   => 'Refresh token is required',
                'refresh_token.min_length' => 'Invalid refresh token format',
            ],

            'revoke_all' => [
                'user_id.is_natural_no_zero' => 'User ID must be a positive integer',
            ],

            default => [],
        };
    }
}
