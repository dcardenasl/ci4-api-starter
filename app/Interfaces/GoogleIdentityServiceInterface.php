<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Google Identity Service Interface
 *
 * Verifies Google ID tokens and returns normalized identity claims.
 */
interface GoogleIdentityServiceInterface
{
    /**
     * Verify a Google ID token and return normalized claims.
     *
     * @param string $idToken
     * @return array<string, mixed>
     */
    public function verifyIdToken(string $idToken): array;
}
