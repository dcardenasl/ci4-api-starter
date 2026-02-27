# Module Bootstrap Checklist

Use this checklist when creating a new module from this template.

## 1. Scaffold

1. Run `php spark make:crud {Resource} --domain {Domain} --route {slug}`.
2. Verify generated files exist in `Controllers`, `DTO`, `Interfaces`, `Services`, `Documentation`, `Language`, and `tests`.

## 2. Persistence

1. Create migration(s) for the resource table(s).
2. Validate `Model` fields: `allowedFields`, validation rules, searchable/filterable/sortable fields.
3. Confirm `Entity` casts/dates are correct.

## 3. DTOs

1. Finalize `*IndexRequestDTO`, `*CreateRequestDTO`, `*UpdateRequestDTO`.
2. Finalize response DTO fields and `fromArray()` normalization.
3. Ensure request DTO rules/messages are complete and localized.

## 4. Service

1. Keep business logic in service methods only.
2. Keep return types aligned with contracts:
   - `index()` -> `DataTransferObjectInterface` (paginated DTO)
   - `show/store/update()` -> resource DTO
   - commands -> `OperationResult`
3. Register service in `app/Config/Services.php` if not already generated.

## 5. Controller and Routes

1. Keep controller methods thin and use `handleRequest(...)`.
2. Use request DTO class in `handleRequest` for endpoints with input.
3. Add/verify routes in `app/Config/Routes.php`.
4. Apply filters/authorization (`auth`, `roleauth`, etc.) based on access requirements.

## 6. Documentation and i18n

1. Add/verify OpenAPI endpoint docs in `app/Documentation/...`.
2. Ensure language parity in `app/Language/en` and `app/Language/es`.

## 7. Tests

1. Unit tests for service behavior.
2. Feature tests for endpoint responses and authorization.
3. Integration tests for model/database behavior when needed.
4. Run `composer quality`.

## 8. Done Criteria

1. `composer quality` passes.
2. Module respects the architecture contract in `docs/template/ARCHITECTURE_CONTRACT.md`.
3. No unresolved TODO placeholders remain in generated files.
