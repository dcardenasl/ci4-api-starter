# Contribution Rules (Template)

These rules define the minimum contribution standard for this repository and downstream projects using it as a base.

## 1. Architectural Non-Negotiables

1. Controllers stay thin (`ApiController`, `handleRequest()`, `getDTO()`).
2. Services keep pure business logic; no HTTP status/response handling.
3. Data transfer between layers uses DTOs (`readonly`, validated request DTOs).
4. Runtime classes (`Commands`, `Filters`) resolve dependencies via container/helpers (`Services::*`, `model()`).

## 2. Required Artifacts Per Change

1. Code changes aligned with contracts in `docs/template/ARCHITECTURE_CONTRACT.md`.
2. EN/ES language parity where applicable (`app/Language/en`, `app/Language/es`).
3. EN/ES documentation parity for any new or modified docs in `docs/`.
4. Tests updated in the relevant suite (`Unit`, `Feature`, `Integration`).
5. Documentation placement follows `docs/DOCUMENTATION_SCOPE.md` (no cross-section duplication).

## 3. Quality Gates Before Merge

1. `composer cs-check`
2. `composer phpstan`
3. `php scripts/i18n-check.php`
4. `php scripts/docs-i18n-parity-check.php`
5. `vendor/bin/phpunit`
6. Prefer `composer quality` to run the full chain.

## 4. PR Acceptance Checklist

1. Scope and impact are explicitly documented.
2. Internal/external contract impact is declared.
3. Migration actions are documented if contracts changed.
4. No unresolved TODOs or temporary shortcuts are introduced.
