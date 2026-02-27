<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\SecurityContext;
use App\Exceptions\AuthenticationException;
use App\Exceptions\BadRequestException;
use App\Interfaces\AuditServiceInterface;
use App\Interfaces\JwtServiceInterface;
use App\Interfaces\TokenRevocationServiceInterface;
use App\Models\RefreshTokenModel;
use App\Models\TokenBlacklistModel;
use App\Traits\ValidatesRequiredFields;
use CodeIgniter\Cache\CacheInterface;

/**
 * Token Revocation Service
 *
 * Handles revocation of access tokens (JWT) and refresh tokens
 */
class TokenRevocationService implements TokenRevocationServiceInterface
{
    use ValidatesRequiredFields;

    public function __construct(
        protected TokenBlacklistModel $blacklistModel,
        protected RefreshTokenModel $refreshTokenModel,
        protected JwtServiceInterface $jwtService,
        protected AuditServiceInterface $auditService,
        protected ?CacheInterface $cache = null,
        protected ?BearerTokenService $bearerTokenService = null
    ) {
        // Use injected cache or get from Services
        $this->cache = $cache ?? \Config\Services::cache();
        $this->bearerTokenService ??= \Config\Services::bearerTokenService();
    }

    /**
     * Revoke an access token by adding its JTI to blacklist
     */
    public function revokeToken(string $jti, int $expiresAt, ?SecurityContext $context = null): bool
    {
        $added = $this->blacklistModel->addToBlacklist($jti, $expiresAt);

        if (!$added) {
            throw new BadRequestException(
                lang('Tokens.revocationFailed'),
                ['token' => lang('Tokens.revocationFailed')]
            );
        }

        // Mark as revoked in cache immediately.
        $cacheKey = "token_revoked_{$jti}";
        $ttl      = (int) env('JWT_ACCESS_TOKEN_TTL', 3600);
        $this->cache->save($cacheKey, 1, $ttl);

        // Log token revocation
        $this->auditService->log(
            'token_revoked',
            'tokens',
            null,
            [],
            ['jti' => $jti],
            $context
        );

        return true;
    }

    /**
     * Revoke an access token from authorization header
     */
    public function revokeAccessToken(array $data, ?SecurityContext $context = null): bool
    {
        $this->validateRequiredFields($data, [
            'authorization_header' => lang('Tokens.authorizationHeaderRequired'),
        ], lang('Tokens.invalidRequest'));

        $token = $this->bearerTokenService->extractFromHeader((string) $data['authorization_header']);
        if ($token === null) {
            throw new BadRequestException(
                lang('Tokens.invalidRequest'),
                ['authorization_header' => lang('Tokens.invalidAuthorizationFormat')]
            );
        }

        // Decodificar y validar JWT
        $payload = $this->jwtService->decode($token);

        if (!$payload) {
            throw new AuthenticationException(
                lang('Tokens.invalidToken'),
                ['token' => lang('Tokens.tokenDecodeFailed')]
            );
        }

        if (!isset($payload->jti) || !isset($payload->exp)) {
            throw new AuthenticationException(
                lang('Tokens.invalidToken'),
                ['token' => lang('Tokens.missingRequiredClaims')]
            );
        }

        // Revocar token agregando JTI a blacklist
        return $this->revokeToken($payload->jti, (int) $payload->exp, $context);
    }

    /**
     * Check if a token is revoked
     */
    public function isRevoked(string $jti): bool
    {
        // Check cache first for performance
        $cacheKey = "token_revoked_{$jti}";

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return (bool) $cached;
        }

        // Check database
        $isBlacklisted = $this->blacklistModel->isBlacklisted($jti);

        if ($isBlacklisted) {
            $ttl = (int) env('JWT_ACCESS_TOKEN_TTL', 3600);
        } else {
            $ttl = (int) env('JWT_REVOCATION_CACHE_TTL', 60);
        }

        $this->cache->save($cacheKey, $isBlacklisted ? 1 : 0, $ttl);

        return $isBlacklisted;
    }

    /**
     * Revoke all tokens for a user
     */
    public function revokeAllUserTokens(int $userId, ?SecurityContext $context = null): bool
    {
        // Revoke all refresh tokens
        $this->refreshTokenModel->revokeAllUserTokens($userId);

        // Log all tokens revocation
        $this->auditService->log(
            'all_tokens_revoked',
            'users',
            $userId,
            [],
            [],
            $context
        );

        return true;
    }

    /**
     * Clean up expired blacklisted tokens
     */
    public function cleanupExpired(): int
    {
        $deletedBlacklist = $this->blacklistModel->deleteExpired();
        $deletedRefresh = $this->refreshTokenModel->deleteExpired();

        return $deletedBlacklist + $deletedRefresh;
    }
}
