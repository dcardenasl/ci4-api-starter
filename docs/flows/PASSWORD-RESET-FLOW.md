# Password Reset Flow

This document describes the sequence of events during the password reset process.

## 1. Request Reset Link

**Endpoint:** `POST /api/v1/auth/forgot-password`

1.  Client sends `email`.
2.  `Identity\PasswordResetController::sendResetLink()` is called.
3.  `ForgotPasswordRequestDTO` is instantiated (Validates email format).
4.  Controller calls `PasswordResetService::sendResetLink($dto)`.
5.  `PasswordResetService`:
    *   Checks if user exists by email.
    *   If user is active:
        *   Generates a unique 32-char token.
        *   Deletes any existing tokens for this email.
        *   Inserts new token into `password_resets` table.
        *   Queues a `password-reset` email using `EmailService`.
    *   If user was soft-deleted:
        *   Triggers account reactivation flow (sets to `pending_approval`).
6.  Service returns success message (generic for security).
7.  Controller returns `200 OK`.

## 2. Validate Reset Token

**Endpoint:** `GET /api/v1/auth/validate-reset-token`

1.  Client sends `email` and `token`.
2.  `PasswordResetTokenValidationDTO` is instantiated (Validates required fields).
3.  Controller calls `PasswordResetService::validateToken($dto)`.
4.  `PasswordResetService`:
    *   Cleans expired tokens (> 60 mins).
    *   Checks if `email` and `token` match in `password_resets` table.
    *   If invalid/expired → throws `NotFoundException` (404).
5.  Service returns `['valid' => true]`.
6.  Controller returns `200 OK`.

## 3. Reset Password

**Endpoint:** `POST /api/v1/auth/reset-password`

1.  Client sends `email`, `token`, and `password`.
2.  `ResetPasswordRequestDTO` is instantiated (Validates token, email, and strong password).
3.  Controller calls `PasswordResetService::resetPassword($dto)`.
4.  `PasswordResetService`:
    *   Re-validates token and email match.
    *   Retrieves user.
    *   **Atomic Transaction:**
        *   Updates user `password` (hashed).
        *   If user was `invited`, sets `status` to `active` and verifies email.
        *   Deletes reset token from DB.
        *   Logs `password_reset_success` in audit trail.
5.  Service returns `PasswordResetResponseDTO`.
6.  Controller returns `200 OK`.
