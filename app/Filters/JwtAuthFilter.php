<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\ApiResponse;
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

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}
