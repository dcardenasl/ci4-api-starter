# Plan: Database Concurrency and Contention Mitigation

### CI4 API Starter — Technical Document

---

## Executive Summary

Reduce concurrent reads on `users`, remove race conditions around refresh tokens, token blacklist, and password reset flows, and make rate limiting atomic. Immediate consistency is achieved via **authentication state versioning** (`auth_version`) embedded in JWT claims, so each authenticated request does not require a DB lookup. The plan supports external Redis with robust DB fallback.

---

## API / Interface / Type Changes

- Add JWT claims:
  - `av` (`int`): user `auth_version`
  - `st` (`string`, optional): account status
  - `ev` (`bool`, optional): email verification state
- Update JWT service signature:
  - `encode(int $userId, string $role, array $extraClaims = []): string`
- Add `UserAuthStateService` for `auth_version` read/update with cache.

---

## Schema Changes

- `users`: add `auth_version` (default `1`), optional index.
- `password_resets`: unique index on `email`, index on `created_at`.
- Add `rate_limits` table for DB fallback:
  - key, count, reset_at.

---

## Implementation Highlights

- JWT validation compares token `av` vs current `auth_version`.
- Invalidate tokens by bumping `auth_version` on role/status/email/password changes, approvals, verification, reset-password, and relevant Google auth updates.
- Make refresh token rotation transactional with row lock semantics.
- Use atomic blacklist writes (`INSERT IGNORE`) with unique JTI index.
- Use atomic password reset token consumption (`DELETE ... WHERE token ...`) to guarantee one-time use.
- Use atomic counters for rate limits:
  - Redis (`INCR`/`EXPIRE`) or DB upsert fallback.

---

## Tests

- JWT version mismatch returns `401`.
- Concurrent refresh only succeeds once.
- Concurrent blacklist revoke is idempotent.
- Concurrent password reset only consumes token once.
- Rate limit counters remain correct under concurrency.

---

## Defaults / Assumptions

- MySQL/MariaDB backend.
- Shared hosting target.
- Optional external Redis, automatic DB fallback.
- Low traffic baseline.
- Immediate consistency for auth state invalidation.
