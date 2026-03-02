# Documentation Alignment Report (2026-03-02)

## Scope and Date Window

- Analysis date: 2026-03-02
- Commit window reviewed: 2026-02-27 to 2026-03-02 (last 3 days)
- Total commits reviewed: 51
- Total Markdown files reviewed in repository: 341 (`find . -name '*.md'`)

## Commit Analysis Summary

### Volume

- Net code churn in the window: `+7088 / -4709`
- Highest commit categories:
  - `refactor(core)` (5)
  - `refactor(dto)` (3)
  - `fix(auth)` (3)
  - `fix(arch)` (3)
  - `docs(openapi)` (3)

### Main Architectural Changes Introduced

1. Controllers now resolve services explicitly with `resolveDefaultService()`.
2. `BaseRequestDTO` was decoupled from direct security-context enrichment.
3. Security context propagation moved to `ApiController` boundary (`withSecurityContext(...)`).
4. Domain services moved/standardized under subdirectories (e.g., `Services/Tokens/*`, `Services/System/*`).
5. Write-side business logic was further decomposed into `Actions/*` classes in Auth/Users/Tokens.
6. Queue config now auto-selects DB connection in testing (`tests` by default).

## Documentation Gap Assessment

### Outdated patterns found

1. Legacy controller pattern references (`$serviceName`, `getDTO()`) in guidance docs.
2. Outdated statement that `BaseRequestDTO` enriches identity context by itself.
3. Flow docs describing `collectRequestData()` as if it injected `user_id` directly.
4. Technical docs still referencing pre-domain service paths (e.g., `app/Services/EmailService.php`).
5. Skill docs with stale examples (`app/Services/UserService.php`, snake_case normalization note).

### Intentionally not rewritten

- `docs/reports/*AUDIT*` and dated technical audit snapshots were kept as historical artifacts.
- These may contain old file paths by design because they describe prior states.

## Documentation Updated in this pass

### Core/project guidance

- `AGENTS.md`
- `ARCHITECTURE.md`
- `GEMINI.md`
- `GETTING_STARTED.md`

### Architecture docs

- `docs/architecture/REQUEST_FLOW.md`
- `docs/architecture/REQUEST_FLOW.es.md`
- `docs/architecture/SERVICES.md`
- `docs/architecture/SERVICES.es.md`
- `docs/architecture/LAYERS.es.md`

### Flow docs

- `docs/flows/FILE-UPLOAD-FLOW.md`
- `docs/flows/REGISTER-APPROVAL-FLOW.md`
- `docs/flows/REGISTER-APPROVAL-FLOW.es.md`
- `docs/flows/EMAIL-VERIFICATION-FLOW.md`
- `docs/flows/EMAIL-VERIFICATION-FLOW.es.md`

### Technical docs

- `docs/tech/QUEUE.md`
- `docs/tech/QUEUE.es.md`
- `docs/tech/audit-logging.md`
- `docs/tech/audit-logging.es.md`
- `docs/tech/email.md`
- `docs/tech/email.es.md`
- `docs/tech/refresh-tokens.md`
- `docs/tech/refresh-tokens.es.md`
- `docs/tech/token-revocation.md`
- `docs/tech/token-revocation.es.md`
- `docs/tech/transactions.md`
- `docs/tech/transactions.es.md`

### Template and skill docs

- `docs/template/CONTRIBUTION_RULES.md`
- `docs/template/CONTRIBUTION_RULES.es.md`
- `skills/ci4-api-crud-expert/references/crud-playbook.md`
- `skills/ci4-api-crud-expert/references/crud-snippets.md`

## Verification Notes

Post-update global scans confirm that living docs (excluding historical reports) no longer reference:

- obsolete service paths such as `app/Services/EmailService.php`
- deprecated controller field pattern `protected string $serviceName`
- deprecated helper call pattern `getDTO()`
- outdated phrase that `BaseRequestDTO` automatically enriches security context
- stale flow statement "collectRequestData adds user_id from JWT"

## Outcome

- Documentation is now aligned with the current code architecture introduced in the last 3 days.
- New documentation file added: this report (`DOCUMENTATION_ALIGNMENT_2026-03-02.md`) for traceability of analysis and remediation.
