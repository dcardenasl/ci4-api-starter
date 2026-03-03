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
        $this->jwtRevocationCheck = filter_var(env('JWT_REVOCATION_CHECK', true), FILTER_VALIDATE_BOOLEAN);
        $this->requireEmailVerification = filter_var(env('AUTH_REQUIRE_EMAIL_VERIFICATION', true), FILTER_VALIDATE_BOOLEAN);
        $this->jwtSecretKey = trim((string) (getenv('JWT_SECRET_KEY') ?: env('JWT_SECRET_KEY', '')));
        $this->jwtAccessTokenTtl = (int) (getenv('JWT_ACCESS_TOKEN_TTL') ?: env('JWT_ACCESS_TOKEN_TTL', 3600));
        $this->jwtRefreshTokenTtl = (int) (getenv('JWT_REFRESH_TOKEN_TTL') ?: env('JWT_REFRESH_TOKEN_TTL', 604800));
        $this->jwtRevocationCacheTtl = (int) (getenv('JWT_REVOCATION_CACHE_TTL') ?: env('JWT_REVOCATION_CACHE_TTL', 60));
        $this->googleClientId = trim((string) env('GOOGLE_CLIENT_ID', ''));

        $this->rateLimitWindow = (int) env('RATE_LIMIT_WINDOW', 60);
        $this->rateLimitRequests = (int) env('RATE_LIMIT_REQUESTS', 60);
        $this->rateLimitUserRequests = (int) env('RATE_LIMIT_USER_REQUESTS', 100);
        $this->authRateLimitRequests = (int) env('AUTH_RATE_LIMIT_REQUESTS', 5);
        $this->authRateLimitWindow = (int) env('AUTH_RATE_LIMIT_WINDOW', 900);

        $this->searchEnabled = filter_var(env('SEARCH_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
        $this->searchUseFulltext = filter_var(env('SEARCH_USE_FULLTEXT', true), FILTER_VALIDATE_BOOLEAN);
        $this->searchMinLength = (int) env('SEARCH_MIN_LENGTH', 3);

        $this->paginationDefaultLimit = (int) env('PAGINATION_DEFAULT_LIMIT', 20);
        $this->paginationMaxLimit = (int) env('PAGINATION_MAX_LIMIT', 100);

        $this->fileMaxSize = (int) env('FILE_MAX_SIZE', 10485760);
        $this->fileAllowedTypes = (string) env('FILE_ALLOWED_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip');
        $this->fileStorageDriver = (string) env('FILE_STORAGE_DRIVER', 'local');
        $this->fileUploadPath = (string) env('FILE_UPLOAD_PATH', 'writable/uploads/');

        $this->requestLoggingEnabled = filter_var(env('REQUEST_LOGGING_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
        $this->slowQueryThreshold = (int) env('SLOW_QUERY_THRESHOLD', 1000);
        $this->sloP95TargetMs = (int) env('SLO_API_P95_TARGET_MS', 500);
    }
}
