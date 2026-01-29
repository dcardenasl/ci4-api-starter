<?php

declare(strict_types=1);

namespace App\Filters;

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
            return $this->unauthorized('Authorization header missing');
        }

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->unauthorized('Invalid authorization header format');
        }

        $token = $matches[1];

        // Decode token once (optimization - prevents double decoding)
        $decoded = $jwtService->decode($token);

        if ($decoded === null) {
            return $this->unauthorized('Invalid or expired token');
        }

        // Check if token is revoked (if revocation check is enabled)
        if (env('JWT_REVOCATION_CHECK', 'true') === 'true') {
            $jti = $decoded->jti ?? null;

            if ($jti) {
                $tokenRevocationService = new \App\Services\TokenRevocationService(
                    new \App\Models\TokenBlacklistModel(),
                    new \App\Models\RefreshTokenModel()
                );

                if ($tokenRevocationService->isRevoked($jti)) {
                    return $this->unauthorized('Token has been revoked');
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
            ->setJSON([
                'success' => false,
                'message' => $message,
            ])
            ->setStatusCode(401);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}
