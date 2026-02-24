# Validation System

This project uses a layered validation strategy with a strict domain/action contract.

## Validation Layers

1. Input validation (`app/Validations/*Validation.php`)
2. Model validation (`Model::$validationRules`)
3. Business rules (service logic)

Each layer has a different responsibility:
- Input validation: request shape, format, constraints.
- Model validation: DB integrity and persistence-level constraints.
- Business rules: domain decisions (ownership, state transitions, side effects).

## Domain/Action Contract

Validation is grouped by domain (for example: `auth`, `user`, `file`, `token`, `audit`) and action (`login`, `update`, `upload`, etc.).

Main helpers:

```php
validateOrFail($data, 'auth', 'login');
$validation = getValidationRules('file', 'upload');
$errors = validateInputs($data, $validation['rules'], $validation['messages']);
```

Important behavior:
- `validateOrFail()` now fails fast if the domain is not registered.
- `validateOrFail()` now fails fast if the action is unknown for a valid domain.
- Unknown domain/action raises `InvalidArgumentException` (configuration/programming error).
- Invalid user input raises `ValidationException` (HTTP 422).

## How Services Apply Validation

Two patterns are intentionally used:

1. `validateOrFail(...)` for direct 422 flow.
2. `getValidationRules(...) + validateInputs(...)` when service maps errors to `BadRequestException` with custom message context.

Recent examples in the codebase:
- Password reset uses `validateOrFail($data, 'auth', ...)`.
- User update uses `validateOrFail($data, 'user', 'update')`.
- File/audit/token services consume centralized rule sets via helpers.

## Model Validation Notes

Model rules should avoid conflicting with partial update semantics.

Example:
- `UserModel` email rule uses `permit_empty|valid_email_idn|max_length[255]|is_unique[...]`.
- Required fields for update must be enforced at input-validation level (`user:update`), not by forcing model-required for all writes.

## Common Rules Used

- `required`, `permit_empty`
- `is_natural_no_zero`
- `valid_email_idn`
- `valid_token[64]`
- `strong_password`
- `max_length[N]`, `min_length[N]`
