# Feature Implementation Playbook (Reusable Guide)

## Purpose
Standardize how new features are designed and implemented in this CI4 API. Focus on architectural consistency, security, documentation, testing, and maintainability.

## Scope
- New modules/entities (CRUD, reports, integrations).
- Changes to existing endpoints.
- Performance and security improvements.

---

## Principles
1. Clear layer separation: `Controller → Service → Model → Entity`.
1. Two-level validation: input (domain) + model (integrity).
1. Security by default: JWT + roles + ownership when needed.
1. Consistent responses with `ApiResponse`.
1. i18n: messages in `en` and `es`.

---

## File Structure Reference
- `app/Controllers/Api/V1/` API controllers.
- `app/Services/` business logic.
- `app/Models/` data access.
- `app/Entities/` transformation/hidden fields.
- `app/Validations/` domain input rules.
- `app/Documentation/` OpenAPI annotations.
- `app/Filters/` middleware.
- `tests/Unit/`, `tests/Integration/`, `tests/Feature/`.

---

## End-to-End Checklist

1. **Analysis & Design**
1. Define entities and relationships.
1. Identify business rules and states.
1. Define public vs protected endpoints.
1. Define permissions by role/ownership.

2. **Schema & Migrations**
1. Create migrations with indexes and constraints.
1. Use soft delete if needed.
1. Add search indexes when applicable.

3. **Models & Entities**
1. Models with `allowedFields`, `validationRules`.
1. `searchableFields`, `filterableFields`, `sortableFields`.
1. Entities with `toArray()` hiding sensitive data.

4. **Input Validation**
1. Create `XValidation` rules per action (`store`, `update`).
1. Register in `InputValidationService`.
1. Add messages in `app/Language/en` and `app/Language/es`.

5. **Services**
1. Implement CRUD + business rules.
1. Use `QueryBuilder` for filters/search/pagination.
1. Enforce ACL (owner/admin).
1. Throw domain exceptions with clear messages.

6. **Controllers**
1. Extend `ApiController`.
1. Map methods to services.
1. Use `getUserId()` and `getUserRole()`.

7. **Routes**
1. Separate public vs protected (`jwtauth`).
1. Use `roleauth` for admin.
1. Keep `/api/v1` versioning.

8. **OpenAPI Docs**
1. Add endpoints and schemas under `app/Documentation/`.
1. Keep consistent request/response shapes.

9. **Tests**
1. Unit tests for business rules.
1. Feature tests for critical endpoints.
1. Integration tests when filters/search/joins exist.

10. **Repo Docs**
1. Update `README.md` and `README.es.md`.
1. Document supported filters/params.

---

## Minimal Example (file structure)

```
app/
  Controllers/Api/V1/PostController.php
  Services/PostService.php
  Models/PostModel.php
  Entities/PostEntity.php
  Validations/PostValidation.php
  Documentation/Blog/PostEndpoints.php
tests/
  Unit/Services/PostServiceTest.php
  Feature/Controllers/PostControllerTest.php
  Integration/Services/PostServiceTest.php
```

---

## Do
- Use `ApiResponse` for responses.
- Validate input with `validateOrFail`.
- Enforce authorization inside services.
- Use `QueryBuilder` with whitelisted fields.
- Update docs and tests.

## Don’t
- Don’t bypass services from controllers.
- Don’t expose sensitive fields in entities.
- Don’t leave auth-required endpoints unprotected.
- Don’t rely on frontend validation.
- Don’t put business logic in controllers.

---

## Endpoint Template (pseudo)
```
POST /api/v1/resource
Headers: Authorization: Bearer <token>

Controller -> handleRequest('store')
Service -> validateOrFail() -> business rules -> model->insert()
ApiResponse::created()
```

---

## Acceptance Criteria (baseline)
1. Endpoints implemented and documented.
1. ACL enforced correctly.
1. Validations working.
1. Tests cover main cases.
1. No regressions to existing contracts.

---

## Assumptions & Defaults
1. Layered architecture is respected (Controller → Service → Model → Entity).
1. Uniform responses with `ApiResponse`.
1. Soft delete when applicable.
1. i18n in `en` and `es`.
