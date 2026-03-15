<?php

declare(strict_types=1);

namespace Config;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

/**
 * System Monitoring Services
 *
 * Handles services related to system health, audit logging,
 * metrics, and automated notifications.
 */
trait SystemMonitoringServices
{
    public static function emailService(bool $getShared = true): \App\Interfaces\System\EmailServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('emailService');
        }

        $emailConfig = config('Email');
        $fromAddress = (string) ($emailConfig->fromEmail ?: 'no-reply@example.com');
        $fromName = (string) ($emailConfig->fromName ?: Project::NAME);
        $defaultLocale = (string) config('App')->defaultLocale;
        $mailer = self::buildMailerFromConfig($emailConfig);

        return new \App\Services\System\EmailService(
            $mailer,
            static::queueManager(),
            $fromAddress,
            $fromName,
            $defaultLocale
        );
    }

    public static function auditService(bool $getShared = true): \App\Interfaces\System\AuditServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('auditService');
        }

        return new \App\Services\System\AuditService(
            static::auditRepository(),
            static::auditResponseMapper(),
            static::auditWriter(),
            static::queueManager(),
            config('Audit'),
            ENVIRONMENT !== 'testing',
            '127.0.0.1',
            'system',
            static::auditPayloadSanitizer()
        );
    }

    public static function auditWriter(bool $getShared = true): \App\Services\System\AuditWriter
    {
        if ($getShared) {
            return static::getSharedInstance('auditWriter');
        }

        return new \App\Services\System\AuditWriter(
            static::auditRepository()
        );
    }

    public static function auditPayloadSanitizer(bool $getShared = true): \App\Services\System\AuditPayloadSanitizer
    {
        if ($getShared) {
            return static::getSharedInstance('auditPayloadSanitizer');
        }

        return new \App\Services\System\AuditPayloadSanitizer();
    }

    public static function requestAuditContextFactory(bool $getShared = true): \App\Support\RequestAuditContextFactory
    {
        if ($getShared) {
            return static::getSharedInstance('requestAuditContextFactory');
        }

        return new \App\Support\RequestAuditContextFactory();
    }

    public static function securityAuditLogger(bool $getShared = true): \App\Services\System\SecurityAuditLogger
    {
        if ($getShared) {
            return static::getSharedInstance('securityAuditLogger');
        }

        return new \App\Services\System\SecurityAuditLogger(
            static::auditService(),
            static::requestAuditContextFactory()
        );
    }

    public static function auditResponseMapper(bool $getShared = true): \App\Interfaces\Mappers\ResponseMapperInterface
    {
        if ($getShared) {
            return static::getSharedInstance('auditResponseMapper');
        }

        return new \App\Services\Core\Mappers\DtoResponseMapper(
            \App\DTO\Response\Audit\AuditResponseDTO::class
        );
    }

    public static function metricsService(bool $getShared = true): \App\Interfaces\System\MetricsServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('metricsService');
        }

        $apiConfig = config('Api');
        $slowQueryThreshold = $apiConfig->slowQueryThreshold;
        $p95Target = $apiConfig->sloP95TargetMs;

        return new \App\Services\System\MetricsService(
            new \App\Models\RequestLogModel(),
            new \App\Models\MetricModel(),
            $slowQueryThreshold,
            $p95Target
        );
    }

    public static function catalogService(bool $getShared = true): \App\Interfaces\System\CatalogServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('catalogService');
        }

        return new \App\Services\System\CatalogService(
            static::auditRepository()
        );
    }

    private static function buildMailerFromConfig(\Config\Email $config): ?MailerInterface
    {
        $provider = strtolower(trim((string) $config->provider));

        if ($provider === '' || in_array($provider, ['none', 'null', 'disabled'], true)) {
            return null;
        }

        if ($provider !== 'smtp') {
            log_message('warning', "EmailService: Unsupported EMAIL_PROVIDER '{$provider}'.");
            return null;
        }

        $host = trim((string) $config->SMTPHost);
        $port = (int) $config->SMTPPort;
        $user = (string) $config->SMTPUser;
        $pass = (string) $config->SMTPPass;
        $crypto = strtolower(trim((string) $config->SMTPCrypto));

        if ($host === '') {
            log_message('warning', 'EmailService: EMAIL_SMTP_HOST is empty. Mailer disabled.');
            return null;
        }

        $scheme = $crypto === 'ssl' ? 'smtps' : 'smtp';
        $encryption = $crypto === 'tls' ? '?encryption=tls' : '';
        $credentials = '';
        if ($user !== '' || $pass !== '') {
            $credentials = rawurlencode($user) . ':' . rawurlencode($pass) . '@';
        }

        $dsn = sprintf('%s://%s%s:%d%s', $scheme, $credentials, $host, $port, $encryption);

        try {
            return new Mailer(Transport::fromDsn($dsn));
        } catch (\Throwable $e) {
            log_message('error', 'EmailService: Invalid mailer configuration - ' . $e->getMessage());
            return null;
        }
    }
}
