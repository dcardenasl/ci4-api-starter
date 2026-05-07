<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\DTO\Request\Auth\IntrospectRequestDTO;
use App\DTO\Response\Auth\IntrospectResponseDTO;
use App\Interfaces\Auth\TokenIntrospectionServiceInterface;
use App\Interfaces\Tokens\JwtServiceInterface;
use App\Interfaces\Tokens\TokenRevocationServiceInterface;

/**
 * Validates JWTs on behalf of external callers (domain apps).
 *
 * Mirrors the validation flow in JwtAuthFilter: decode → check revocation
 * → return claims. Never throws; outcomes are encoded in the response DTO.
 */
class TokenIntrospectionService implements TokenIntrospectionServiceInterface
{
    public function __construct(
        private readonly JwtServiceInterface $jwtService,
        private readonly TokenRevocationServiceInterface $tokenRevocationService,
    ) {
    }

    public function introspect(IntrospectRequestDTO $request): IntrospectResponseDTO
    {
        $decoded = $this->jwtService->decode($request->token);

        if ($decoded === null) {
            return $this->invalid('invalid_or_expired');
        }

        $jti = isset($decoded->jti) ? (string) $decoded->jti : null;
        if ($jti !== null && $this->tokenRevocationService->isRevoked($jti)) {
            return $this->invalid('revoked');
        }

        $permissions = [];
        if (isset($decoded->scope) && is_array($decoded->scope)) {
            $permissions = array_values(array_map(static fn ($p) => (string) $p, $decoded->scope));
        }

        return new IntrospectResponseDTO(
            valid: true,
            uid: isset($decoded->uid) ? (int) $decoded->uid : null,
            permissions: $permissions,
            exp: isset($decoded->exp) ? (int) $decoded->exp : null,
            error: null,
        );
    }

    private function invalid(string $reason): IntrospectResponseDTO
    {
        return new IntrospectResponseDTO(
            valid: false,
            uid: null,
            permissions: [],
            exp: null,
            error: $reason,
        );
    }
}
