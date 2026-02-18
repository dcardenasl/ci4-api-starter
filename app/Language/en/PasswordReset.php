<?php

/**
 * Password reset language strings (English)
 */
return [
    // Success messages
    'linkSent'           => 'If an account exists with that email, a password reset link has been sent.',
    'tokenValid'         => 'Token is valid',
    'passwordReset'      => 'Your password has been reset successfully',

    // Error messages
    'emailRequired'            => 'Valid email is required',
    'tokenRequired'            => 'Reset token and email are required',
    'invalidToken'             => 'Invalid or expired reset token',
    'userNotFound'             => 'User not found',
    'allFieldsRequired'        => 'All fields are required',
    'passwordMinLength'        => 'Password must be at least 8 characters long',
    'passwordMaxLength'        => 'Password must not exceed 128 characters',
    'passwordComplexity'       => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character',
    'passwordValidationFailed' => 'Password validation failed',

    // Response data messages
    'sentMessage'        => 'Password reset link sent',
    'resetMessage'       => 'Password reset successfully',
];
