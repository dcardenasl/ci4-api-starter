<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Shared locale-resolution helpers for transactional email senders.
 *
 * Used by services/actions that queue a localized email (password reset,
 * verification, invitation, account approval, self-registration). Consumers
 * gain two entry points:
 *
 *   - normalizeLocale(?string $locale): string
 *     Validates the candidate against `Config\App::$supportedLocales`,
 *     falling back to the current request locale and then to
 *     `Config\App::$defaultLocale` when the candidate is empty or unknown.
 *
 *   - subjectForLocale(string $line, string $locale): string
 *     Temporarily swaps the app's active locale (request + language
 *     services), renders the given `lang()` line, and restores the previous
 *     locale in a `finally` block so the swap never leaks past this call —
 *     safe to use even when queuing is fire-and-forget.
 *
 * Extracted so the same normalize/apply/restore logic isn't duplicated
 * across PasswordResetService, VerificationService, UserInvitationService,
 * ApproveUserAction, and RegisterUserAction.
 */
trait LocalizedEmailSubjectResolver
{
    /**
     * Resolve a caller-supplied locale to one of `Config\App::$supportedLocales`.
     *
     * Empty/null input falls back to the current request locale; anything
     * that still doesn't match a supported locale falls back to
     * `Config\App::$defaultLocale`.
     */
    private function normalizeLocale(?string $locale): string
    {
        $locale = strtolower(trim((string) $locale));
        if ($locale === '') {
            $locale = (string) $this->currentLocale();
        }

        $supported = config('App')->supportedLocales ?? [];
        foreach ($supported as $supportedLocale) {
            if (strtolower(trim((string) $supportedLocale)) === $locale) {
                return $locale;
            }
        }

        return (string) (config('App')->defaultLocale ?? 'en');
    }

    /**
     * Render a `lang()` line under a temporarily-applied locale, restoring
     * whatever locale was active beforehand — even if `lang()` throws.
     */
    private function subjectForLocale(string $line, string $locale): string
    {
        $previous = $this->currentLocale();
        $this->applyLocale($locale);

        try {
            return lang($line);
        } finally {
            if ($previous !== null && $previous !== '') {
                $this->applyLocale($previous);
            }
        }
    }

    private function currentLocale(): ?string
    {
        try {
            return (string) service('request')->getLocale();
        } catch (\Throwable) {
            return null;
        }
    }

    private function applyLocale(string $locale): void
    {
        try {
            service('request')->setLocale($locale);
        } catch (\Throwable) {
            // no-op in CLI contexts without a request
        }

        try {
            service('language')->setLocale($locale);
        } catch (\Throwable) {
            // no-op if language service is unavailable
        }
    }
}
