# Testing APIs (Feature Playbook)

This document is a feature playbook for API testing execution in new modules.

## Purpose

Ensure each feature delivers stable HTTP contracts and architecture-compliant behavior.

## Implementation Checklist

1. Use `Tests\Support\ApiTestCase` for endpoint tests.
2. Cover success and failure paths in service unit tests.
3. Add integration tests when persistence/query behavior is part of scope.
4. Validate auth and authorization behavior explicitly.
5. Enforce JSON response contract assertions (`status`, `message`, `data/errors`).

## Commands

```bash
php spark tests:prepare-db
vendor/bin/phpunit --configuration=phpunit.xml --no-coverage --testdox
composer quality
```

## Acceptance Criteria

1. Feature tests verify final API payload shape.
2. Unit tests verify service/domain behavior without HTTP coupling.
3. Integration tests verify DB/model behavior when applicable.
4. Architecture tests stay green.

## Canonical Technical Reference

1. Testing strategy and architecture constraints: `../architecture/TESTING.md`.
2. Practical testing rules and patterns: `../tech/TESTING_GUIDELINES.md`.
