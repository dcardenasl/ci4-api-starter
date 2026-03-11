<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Main API Configuration
 *
 * Centralizes all environment variables into strictly typed properties.
 * Prevents scattered env() calls throughout the business logic.
 */
class Api extends BaseConfig
{
    // Auth & Security
    public bool $jwtRevocationCheck = true;
    public bool $requireEmailVerification = true;
    public string $jwtSecretKey = '';
    public int $jwtAccessTokenTtl = 3600;
    public int $jwtRefreshTokenTtl = 604800;
    public int $jwtRevocationCacheTtl = 60;
    public string $googleClientId = '';

    // Rate Limiting
    public int $rateLimitWindow = 60;
    public int $rateLimitRequests = 60;
    public int $rateLimitUserRequests = 100;
    public int $authRateLimitRequests = 5;
    public int $authRateLimitWindow = 900;

    // Search Engine
    public bool $searchEnabled = true;
    public bool $searchUseFulltext = true;
    public int $searchMinLength = 3;

    // Pagination
    public int $paginationDefaultLimit = 20;
    public int $paginationMaxLimit = 100;

    // File Management
    public int $fileMaxSize = 10485760; // 10MB
    public string $fileAllowedTypes = 'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip';
    public string $fileStorageDriver = 'local';
    public string $fileUploadPath = 'writable/uploads/';

    // Logging & Monitoring
    public bool $requestLoggingEnabled = true;
    public int $slowQueryThreshold = 1000;
    public int $sloP95TargetMs = 500;

    public function __construct()
    {
        parent::__construct();

        // Initialize from environment with fallbacks
        $this->jwtRevocationCheck = filter_var($this->envValue('JWT_REVOCATION_CHECK', true), FILTER_VALIDATE_BOOLEAN);
        $this->requireEmailVerification = filter_var($this->envValue('AUTH_REQUIRE_EMAIL_VERIFICATION', true), FILTER_VALIDATE_BOOLEAN);
        $this->jwtSecretKey = trim((string) $this->envValue('JWT_SECRET_KEY', ''));
        $this->jwtAccessTokenTtl = (int) $this->envValue('JWT_ACCESS_TOKEN_TTL', 3600);
        $this->jwtRefreshTokenTtl = (int) $this->envValue('JWT_REFRESH_TOKEN_TTL', 604800);
        $this->jwtRevocationCacheTtl = (int) $this->envValue('JWT_REVOCATION_CACHE_TTL', 60);
        $this->googleClientId = trim((string) $this->envValue('GOOGLE_CLIENT_ID', ''));

        $this->rateLimitWindow = (int) $this->envValue('RATE_LIMIT_WINDOW', 60);
        $this->rateLimitRequests = (int) $this->envValue('RATE_LIMIT_REQUESTS', 60);
        $this->rateLimitUserRequests = (int) $this->envValue('RATE_LIMIT_USER_REQUESTS', 100);
        $this->authRateLimitRequests = (int) $this->envValue('AUTH_RATE_LIMIT_REQUESTS', 5);
        $this->authRateLimitWindow = (int) $this->envValue('AUTH_RATE_LIMIT_WINDOW', 900);

        $this->searchEnabled = filter_var($this->envValue('SEARCH_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
        $this->searchUseFulltext = filter_var($this->envValue('SEARCH_USE_FULLTEXT', true), FILTER_VALIDATE_BOOLEAN);
        $this->searchMinLength = (int) $this->envValue('SEARCH_MIN_LENGTH', 3);

        $this->paginationDefaultLimit = (int) $this->envValue('PAGINATION_DEFAULT_LIMIT', 20);
        $this->paginationMaxLimit = (int) $this->envValue('PAGINATION_MAX_LIMIT', 100);

        $this->fileMaxSize = (int) $this->envValue('FILE_MAX_SIZE', 10485760);
        $this->fileAllowedTypes = (string) $this->envValue('FILE_ALLOWED_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip');
        $this->fileStorageDriver = (string) $this->envValue('FILE_STORAGE_DRIVER', 'local');
        $this->fileUploadPath = (string) $this->envValue('FILE_UPLOAD_PATH', 'writable/uploads/');

        $this->requestLoggingEnabled = filter_var($this->envValue('REQUEST_LOGGING_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
        $this->slowQueryThreshold = (int) $this->envValue('SLOW_QUERY_THRESHOLD', 1000);
        $this->sloP95TargetMs = (int) $this->envValue('SLO_API_P95_TARGET_MS', 500);
    }

    /**
     * Prefer getenv() (mutable via putenv) over env() which checks $_ENV/$_SERVER first.
     */
    private function envValue(string $key, $default = null)
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return env($key, $default);
    }
}
