<?php

/**
 * Input Validation Language Strings
 *
 * Contains all validation error messages for the InputValidation layer.
 * Organized by domain (auth, user, file, token, audit) and common messages.
 */
return [
    // ========================================
    // Common/Shared Messages
    // ========================================
    'common' => [
        // ID validation
        'idRequired'         => '{0} is required',
        'idMustBePositive'   => '{0} must be a positive integer',

        // Pagination
        'pageMustBePositive'     => 'Page must be a positive integer',
        'perPageMustBePositive'  => 'Items per page must be a positive integer',
        'perPageExceedsMax'      => 'Items per page cannot exceed 100',
        'sortFieldInvalid'       => 'Sort field contains invalid characters',
        'sortDirInvalid'         => 'Sort direction must be either asc or desc',
        'searchTooLong'          => 'Search query is too long',

        // Username
        'usernameRequired'       => 'Username is required',
        'usernameAlphaNumeric'   => 'Username can only contain letters and numbers',
        'usernameMinLength'      => 'Username must be at least 3 characters',
        'usernameMaxLength'      => 'Username cannot exceed 100 characters',
        'usernameTooLong'        => 'Username is too long',

        // Email
        'emailRequired'          => 'Email is required',
        'emailInvalid'           => 'Please provide a valid email address',
        'emailMaxLength'         => 'Email cannot exceed 255 characters',

        // Password
        'passwordRequired'       => 'Password is required',
        'passwordStrength'       => 'Password must be 8-128 characters with uppercase, lowercase, number, and special character',
        'newPasswordRequired'    => 'New password is required',

        // Role
        'roleInvalid'            => 'Role must be either user or admin',

        // User ID
        'userIdMustBePositive'   => 'User ID must be a positive integer',
        'userIdRequired'         => 'User ID is required',
    ],

    // ========================================
    // Auth Domain
    // ========================================
    'auth' => [
        // Login
        'usernameOrEmailRequired' => 'Username or email is required',

        // Token
        'resetTokenRequired'      => 'Reset token is required',
        'resetTokenInvalid'       => 'Invalid reset token format',
        'verificationTokenRequired' => 'Verification token is required',
        'verificationTokenInvalid'  => 'Invalid verification token format',
        'refreshTokenRequired'    => 'Refresh token is required',
        'refreshTokenInvalid'     => 'Invalid refresh token',
    ],

    // ========================================
    // File Domain
    // ========================================
    'file' => [
        'noFileUploaded'         => 'No file was uploaded',
        'fileTooLarge'           => 'File size cannot exceed 10MB',
        'fileTypeNotAllowed'     => 'File type is not allowed',
    ],

    // ========================================
    // Token Domain
    // ========================================
    'token' => [
        'refreshTokenRequired'   => 'Refresh token is required',
        'refreshTokenInvalid'    => 'Invalid refresh token format',
    ],

    // ========================================
    // Audit Domain
    // ========================================
    'audit' => [
        'actionInvalidChars'     => 'Action contains invalid characters',
        'actionTooLong'          => 'Action cannot exceed 50 characters',
        'entityTypeRequired'     => 'Entity type is required',
        'entityTypeInvalidChars' => 'Entity type contains invalid characters',
        'entityTypeTooLong'      => 'Entity type cannot exceed 50 characters',
        'entityIdRequired'       => 'Entity ID is required',
        'entityIdMustBePositive' => 'Entity ID must be a positive integer',
        'fromDateInvalid'        => 'From date must be in Y-m-d format',
        'toDateInvalid'          => 'To date must be in Y-m-d format',
        'auditLogIdRequired'     => 'Audit log ID is required',
        'auditLogEntityRequired' => 'Entity type and ID are required',
        'auditLogNotFound'       => 'Audit log not found',
    ],
];
