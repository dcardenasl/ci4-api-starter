<?php

declare(strict_types=1);

namespace App\Filters;

use App\HTTP\ApiRequest;
use App\Libraries\ApiResponse;
use App\Libraries\ContextHolder;
use App\Services\Users\UserAccountGuard;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class JwtAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $context = ContextHolder::get();
        if ($context !== null && $context->userId !== null) {
            if ($request instanceof ApiRequest) {
                $request->setAuthContext((int) $context->userId, (string) $context->role);
            }
            return $request;
        }

        // Use service container instead of direct instantiation
        $jwtService = Services::jwtService();
        $bearerTokenService = Services::bearerTokenService();
        $userAccessPolicy = Services::userAccessPolicyService();

        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader)) {
            return $this->unauthorized(lang('Auth.headerMissing'));
        }

        $token = $bearerTokenService->extractFromHeader($authHeader);
        if ($token === null) {
            return $this->unauthorized(lang('Auth.invalidFormat'));
        }

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
            $userModel = Services::userModel(false);
            $user = $userModel->find($userId);

            if (! is_object($user)) {
                return $this->unauthorized(lang('Auth.invalidToken'));
            }

            $policyViolation = $this->checkAccessPolicyViolation($userAccessPolicy, $user);
            if ($policyViolation !== null) {
                return $policyViolation;
            }
        }

        if ($request instanceof ApiRequest) {
            $request->setAuthContext((int) $decoded->uid, (string) $decoded->role);
        }

        // Also set global context for DTO enrichment
        ContextHolder::set(new \App\DTO\SecurityContext((int) $decoded->uid, (string) $decoded->role));

        return $request;
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

    private function checkAccessPolicyViolation(UserAccountGuard $policy, object $user): ?ResponseInterface
    {
        try {
            $policy->assertCanAuthenticate($user);
            return null;
        } catch (\App\Exceptions\AuthorizationException $e) {
            return $this->forbidden($this->resolveExceptionMessage($e));
        } catch (\App\Exceptions\AuthenticationException $e) {
            return $this->unauthorized($this->resolveExceptionMessage($e));
        }
    }

    private function resolveExceptionMessage(\App\Exceptions\ApiException $e): string
    {
        $errors = $e->getErrors();
        $firstError = reset($errors);

        return is_string($firstError) && $firstError !== ''
            ? $firstError
            : $e->getMessage();
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
