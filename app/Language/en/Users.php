<?php

/**
 * User-related language strings (English)
 */
return [
    // General messages
    'notFound'        => 'User not found',
    'idRequired'      => 'User ID is required',
    'emailRequired'   => 'Email is required',
    'passwordRequired' => 'Password is required',
    'fieldRequired'   => 'At least one field (email, first name, last name, password, role) is required',

    // Success messages
    'deletedSuccess'  => 'User deleted successfully',
    'invitationSent'  => 'Invitation email sent successfully',
    'approvedSuccess' => 'User approved successfully',

    // Error messages
    'deleteError'     => 'Error deleting user',
    'createError'     => 'Failed to create user',

    // Validation messages
    'validation' => [
        'email' => [
            'required'    => 'Email is required',
            'valid'       => 'Please provide a valid email',
            'unique'      => 'This email is already registered',
        ],
        'first_name' => [
            'maxLength' => 'First name cannot exceed {0} characters',
        ],
        'last_name' => [
            'maxLength' => 'Last name cannot exceed {0} characters',
        ],
        'password' => [
            'required'   => 'Password is required',
            'minLength'  => 'Password must be at least {0} characters',
            'complexity' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number',
        ],
    ],

    // Authentication
    'auth' => [
        'invalidCredentials' => 'Invalid credentials',
        'loginSuccess'       => 'Login successful',
        'registerSuccess'    => 'User registered successfully',
        'notAuthenticated'   => 'User not authenticated',
        'credentialsRequired' => 'Email and password are required',
    ],
];
