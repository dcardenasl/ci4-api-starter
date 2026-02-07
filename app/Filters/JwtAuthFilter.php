<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\ApiResponse;
use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class JwtAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Use service container instead of direct instantiation
        $jwtService = Services::jwtService();

        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader)) {
            return $this->unauthorized(lang('Auth.headerMissing'));
        }

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->unauthorized(lang('Auth.invalidFormat'));
        }

        $token = $matches[1];

        // Decode token once (optimization - prevents double decoding)
        $decoded = $jwtService->decode($token);

        if ($decoded === null) {
            return $this->unauthorized(lang('Auth.invalidToken'));
        }

        // Check if token is revoked (if revocation check is enabled)
        if (env('JWT_REVOCATION_CHECK', 'true') === 'true') {
            $jti = $decoded->jti ?? null;

            if ($jti) {
                $tokenRevocationService = Services::tokenRevocationService();

                if ($tokenRevocationService->isRevoked($jti)) {
                    return $this->unauthorized(lang('Auth.tokenRevoked'));
                }
            }
        }

        // Enforce email verification for non-OAuth users
        $userId = (int) ($decoded->uid ?? 0);
        if ($userId > 0) {
            $userModel = new UserModel();
            $user = $userModel->find($userId);

            if (! $user) {
                return $this->unauthorized(lang('Auth.invalidToken'));
            }

            if (($user->status ?? null) !== 'active') {
                return $this->forbidden(lang('Auth.accountPendingApproval'));
            }

            $isGoogleOAuth = ($user->oauth_provider ?? null) === 'google';
            if (
                is_email_verification_required()
                && $user->email_verified_at === null
                && ! $isGoogleOAuth
            ) {
                return $this->unauthorized(lang('Auth.emailNotVerified'));
            }
        }

        // Inject user data into request
        $request->userId = $decoded->uid;
        $request->userRole = $decoded->role;
    }

    /**
     * Helper method to return unauthorized response
     *
     * @param string $message Error message
     * @return ResponseInterface
     */
    private function unauthorized(string $message): ResponseInterface
    {
        return Services::response()
            ->setJSON(ApiResponse::unauthorized($message))
            ->setStatusCode(401);
    }

    /**
     * Helper method to return forbidden response
     *
     * @param string $message Error message
     * @return ResponseInterface
     */
    private function forbidden(string $message): ResponseInterface
    {
        return Services::response()
            ->setJSON(ApiResponse::forbidden($message))
            ->setStatusCode(403);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}
