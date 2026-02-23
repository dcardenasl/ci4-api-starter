<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;

/**
 * Centralized policy checks for user authentication access.
 */
class UserAccessPolicyService
{
    /**
     * Ensure the user account can authenticate.
     *
     * @param object $user
     * @return void
     */
    public function assertCanAuthenticate(object $user): void
    {
        if (($user->status ?? null) === 'invited') {
            throw new AuthorizationException(
                lang('Auth.accountSetupRequired'),
                ['status' => lang('Auth.accountSetupRequired')]
            );
        }

        if (($user->status ?? null) !== 'active') {
            throw new AuthorizationException(
                lang('Auth.accountPendingApproval'),
                ['status' => lang('Auth.accountPendingApproval')]
            );
        }

        $isGoogleOAuth = ($user->oauth_provider ?? null) === 'google';

        if (
            is_email_verification_required()
            && $user->email_verified_at === null
            && ! $isGoogleOAuth
        ) {
            throw new AuthenticationException(
                lang('Auth.emailNotVerified'),
                ['email' => lang('Auth.emailNotVerified')]
            );
        }
    }
}
