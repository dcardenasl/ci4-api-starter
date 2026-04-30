# Changelog

All notable changes to ci4-api-starter will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- PHPUnit upgraded from `^10.5.16` to `^11.0`
- PHPStan upgraded from `^1.10` to `^2.0` (aligns with ci4-admin-starter)
- Removed deprecated `beStrictAboutOutputDuringTests` attribute from `phpunit.xml` (removed in PHPUnit 11)
- Added `treatPhpDocTypesAsCertain: false` to `phpstan.neon` for PHPStan 2.0 compatibility

## [1.0.0] — 2026-04-29

### Added
- Initial release: DTO-First layered REST API starter for CodeIgniter 4
- JWT authentication with access + refresh tokens and JTI revocation
- Role-based access control (user / admin / superadmin)
- Google OAuth integration
- CRUD scaffolding engine (`bin/make-crud.sh` + `php spark make:crud`)
- OpenAPI 3.0 auto-generation (`php spark swagger:generate`)
- PHPStan level 7, PSR-12 via PHP-CS-Fixer, pre-commit hooks
- 25 architecture validation tests preventing structural drift
- Audit trail, security logging, metrics, Sentry integration
- File storage with local and AWS S3 backends
- Email verification and password reset flows
- Rate limiting (per-IP, per-user, per-API-key)
- Docker support (multi-stage Dockerfile + docker-compose with MySQL)
- Full bilingual support (English + Spanish)

[unreleased]: https://github.com/dcardenasl/ci4-api-starter/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/dcardenasl/ci4-api-starter/releases/tag/v1.0.0
