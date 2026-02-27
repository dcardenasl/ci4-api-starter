# Documentation Scope Matrix

This matrix defines where each type of documentation belongs to avoid duplication.

## Canonical Sources

1. `docs/architecture/*`: Architecture contracts, invariants, layer patterns, request lifecycle.
2. `docs/tech/*`: Operational and subsystem implementation details (config, runtime behavior, troubleshooting).
3. `docs/features/*`: Feature playbooks (what to implement/change), acceptance criteria, and links to canonical docs.
4. `docs/flows/*`: Sequence diagrams and scenario walkthroughs.
5. `docs/template/*`: Governance contracts and quality gates for template adopters.
6. `docs/release/*`: Audit closeout and release-level traceability.

## Authoring Rules

1. Do not duplicate full technical implementations in `docs/features/*` when `docs/tech/*` already covers them.
2. Do not duplicate architecture rules in `docs/tech/*`; link to `docs/architecture/*`.
3. For each new topic, define one canonical file and keep other files as concise references.
4. Keep EN/ES parity for all active docs in `docs/`.

## Practical Mapping

1. Queue worker configuration/troubleshooting: `docs/tech/QUEUE.md`.
2. Rate limiting internals and headers: `docs/tech/rate-limiting.md`.
3. API testing strategy and constraints: `docs/architecture/TESTING.md`.
4. API testing practical rules/checklists: `docs/tech/TESTING_GUIDELINES.md`.
5. Feature rollout checklist for queue/testing modules: `docs/features/*.md` as playbook + references.
