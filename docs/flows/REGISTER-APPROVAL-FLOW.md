# Register and Approval Flow

## Overview

This flow covers two related sub-flows: **self-registration** (a user signs up via the public endpoint) and **admin approval** (an admin activates the account). A third path exists for **admin-invited users**, who receive a password-setup link directly rather than going through approval.

After self-registration the account is placed in `pending_approval` state. A verification email is also sent. Only once an admin calls the approval endpoint does the account become `active` and allow login.

---

## Sub-flow A — Self-registration

### Step-by-step

1. Client sends `POST /api/v1/auth/register` with `email`, `password`, `first_name`, `last_name`, and optionally `client_base_url`.
2. The `authThrottle` filter applies a per-IP rate limit.
3. `AuthController::register()` calls `handleRequest('register')`.
4. `ApiController::collectRequestData()` merges all request sources and sanitizes input.
5. `AuthService::register()` validates:
   - `password` must not be empty (`BadRequestException` if missing).
   - Full validation rules via `validateOrFail($data, 'auth', 'register')`: unique email, password strength, required fields.
6. User is inserted with:
   - `role = 'user'` (hardcoded — self-registration can never set a privileged role)
   - `status = 'pending_approval'`
   - Password hashed with `PASSWORD_BCRYPT`
7. `VerificationService::sendVerificationEmail(userId, {client_base_url})` is called:
   - Generates a random token, sets expiry to 24 hours from now.
   - Builds the verification link using `ResolvesWebAppLinks::buildVerificationUrl(token, clientBaseUrl)` — this points to the **frontend** (e.g. `http://myapp.com/verify-email?token=X`), not the API.
   - Queues the verification email (non-blocking; errors are logged but do not fail registration).
8. Response: `201 Created` with `{ user, message }` informing the user to verify their email and await admin approval.

### Diagram

```mermaid
sequenceDiagram
    autonumber
    participant Client
    participant authThrottle as Routes / authThrottle
    participant Controller as AuthController
    participant ApiCtrl as ApiController
    participant AuthSvc as AuthService
    participant VerifSvc as VerificationService
    participant EmailSvc as EmailService
    participant Frontend
    participant DB

    Client->>authThrottle: POST /api/v1/auth/register {email, password, first_name, last_name, client_base_url?}
    authThrottle-->>Client: 429 Too Many Requests (if limit exceeded)
    authThrottle->>Controller: request passes

    Controller->>ApiCtrl: handleRequest('register')
    ApiCtrl->>ApiCtrl: collectRequestData() + sanitizeInput()
    ApiCtrl->>AuthSvc: register(data)

    AuthSvc->>AuthSvc: validateOrFail(data, 'auth', 'register')

    alt Validation fails (weak password / duplicate email)
        AuthSvc-->>ApiCtrl: throw ValidationException (422)
        ApiCtrl-->>Client: 422 {status: error, errors: {...}}
    end

    AuthSvc->>DB: INSERT users {email, password_hash, role='user', status='pending_approval'}
    DB-->>AuthSvc: userId

    AuthSvc->>VerifSvc: sendVerificationEmail(userId, {client_base_url})
    VerifSvc->>DB: UPDATE users SET email_verification_token, verification_token_expires
    VerifSvc->>VerifSvc: buildVerificationUrl(token, clientBaseUrl)
    Note over VerifSvc: Link points to FRONTEND<br/>e.g. http://myapp.com/verify-email?token=X
    VerifSvc->>EmailSvc: queueTemplate('verification', email, {link, expires})
    EmailSvc-->>VerifSvc: queued (non-blocking)
    VerifSvc-->>AuthSvc: ok (errors logged, not rethrown)

    AuthSvc-->>ApiCtrl: ApiResponse::created({user}, 'Verify email + pending approval')
    ApiCtrl-->>Client: 201 Created {status: success, data: {user}, message: ...}

    Note over Frontend: User clicks the link in their email<br/>→ Frontend calls GET /api/v1/auth/verify-email?token=X<br/>(see EMAIL-VERIFICATION-FLOW)
```

---

## Sub-flow B — Admin approval

### Step-by-step

1. Admin lists pending users: `GET /api/v1/users?filter[status][eq]=pending_approval` (requires `jwtauth` + `roleauth:admin`).
2. Admin approves a user: `POST /api/v1/users/{id}/approve`.
3. The `jwtauth` filter decodes the Bearer token and sets `user_id` and `role` on the request. The `roleauth:admin` filter verifies the role.
4. `UserController` calls `handleRequest('approve', ['id' => $id])`.
5. `UserService::approve()` runs status guard clauses:
   - User not found → `NotFoundException` (404).
   - `status = 'active'` → `ConflictException` (409) "already approved".
   - `status = 'invited'` → `ConflictException` (409) "cannot approve invited users".
   - `status != 'pending_approval'` → `ConflictException` (409) invalid state.
6. Updates the user: `status = 'active'`, `approved_at = now()`, `approved_by = adminId`.
7. Queues an approval notification email (non-blocking).
8. Response: `200 OK` with the updated user object.
9. The user can now log in via the standard login flow.

### Diagram

```mermaid
sequenceDiagram
    autonumber
    participant Admin
    participant jwtauth as Routes / jwtauth + roleauth:admin
    participant Controller as UserController
    participant ApiCtrl as ApiController
    participant UserSvc as UserService
    participant EmailSvc as EmailService
    participant DB

    Admin->>jwtauth: POST /api/v1/users/{id}/approve (Bearer <token>)

    jwtauth->>jwtauth: Decode JWT + verify role = admin
    alt Invalid token or insufficient role
        jwtauth-->>Admin: 401 / 403
    end

    jwtauth->>Controller: request passes

    Controller->>ApiCtrl: handleRequest('approve', {id})
    ApiCtrl->>ApiCtrl: collectRequestData() — adds user_id from JWT
    ApiCtrl->>UserSvc: approve({id, user_id: adminId})

    UserSvc->>DB: SELECT user WHERE id = ?
    DB-->>UserSvc: UserEntity

    alt User not found
        UserSvc-->>ApiCtrl: throw NotFoundException (404)
        ApiCtrl-->>Admin: 404 {status: error, message: User not found}
    end

    alt status = 'active'
        UserSvc-->>ApiCtrl: throw ConflictException (409)
        ApiCtrl-->>Admin: 409 {status: error, message: Already approved}
    end

    alt status = 'invited'
        UserSvc-->>ApiCtrl: throw ConflictException (409)
        ApiCtrl-->>Admin: 409 {status: error, message: Cannot approve invited users}
    end

    alt status != 'pending_approval'
        UserSvc-->>ApiCtrl: throw ConflictException (409)
        ApiCtrl-->>Admin: 409 {status: error, message: Invalid approval state}
    end

    UserSvc->>DB: UPDATE users SET status='active', approved_at=now, approved_by=adminId
    UserSvc->>EmailSvc: queueTemplate('account-approved', email, {...})
    EmailSvc-->>UserSvc: queued

    UserSvc-->>ApiCtrl: ApiResponse::success(updatedUser)
    ApiCtrl-->>Admin: 200 OK {status: success, data: {user}}
```

---

## User state machine

```mermaid
stateDiagram-v2
    [*] --> pending_approval : POST /api/v1/auth/register (self-registration)
    [*] --> invited : POST /api/v1/users (admin creates user)

    pending_approval --> active : POST /api/v1/users/{id}/approve (admin action)
    invited --> active : POST /api/v1/auth/reset-password (user sets first password)

    active --> active : standard login / normal use

    note right of pending_approval
        Email verification also
        required (if enabled)
    end note

    note right of invited
        Admin-created accounts skip
        pending_approval — they go
        directly to active once the
        invitation link is used
    end note
```

---

## URL resolution for email links (ResolvesWebAppLinks)

Verification and invitation emails contain links that point to the **frontend**, not the API. The `ResolvesWebAppLinks` trait resolves the base URL as follows:

1. If `client_base_url` is present in the request body, validate it:
   - Must be a valid `http` or `https` URL (production requires `https`).
   - Must be present in the `WEBAPP_ALLOWED_BASE_URLS` allowlist.
   - If not in the allowlist: logs a warning and falls back to `WEBAPP_BASE_URL`.
2. If `client_base_url` is absent or invalid, fall back to `WEBAPP_BASE_URL` env var.
3. If `WEBAPP_BASE_URL` is also missing, fall back to `app.baseURL`.

Required environment variables:

```env
WEBAPP_BASE_URL=http://localhost:8081
WEBAPP_ALLOWED_BASE_URLS=http://localhost:8081,https://myapp.com
```

---

## Key validations

- `role` is always forced to `'user'` on self-registration — the client cannot set a privileged role.
- Password strength and unique email are validated by `validateOrFail()` using CI4 validation rules.
- Verification email errors do not roll back the registration; they are logged silently.
- Only users with `status = 'pending_approval'` can be approved. Other statuses produce a `ConflictException`.
- Admin endpoints require both `jwtauth` and `roleauth:admin` filters.

---

## Error cases

| Condition | Exception | HTTP | Notes |
|-----------|-----------|------|-------|
| Missing password | `BadRequestException` | 400 | Before validation rules |
| Validation fails (weak password, duplicate email) | `ValidationException` | 422 | From `validateOrFail()` |
| Rate limit exceeded (registration) | — (filter) | 429 | authThrottle filter |
| User not found (approval) | `NotFoundException` | 404 | — |
| User already active | `ConflictException` | 409 | Already approved |
| User is invited, not pending | `ConflictException` | 409 | Wrong flow |
| Invalid token or non-admin role (approval) | — (filter) | 401 / 403 | jwtauth / roleauth |

---

## Examples

Self-registration:

```bash
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "MyPass123!",
    "first_name": "Jane",
    "last_name": "Doe",
    "client_base_url": "https://myapp.com"
  }'
```

Admin approval:

```bash
curl -X POST http://localhost:8080/api/v1/users/42/approve \
  -H "Authorization: Bearer ADMIN_ACCESS_TOKEN"
```
