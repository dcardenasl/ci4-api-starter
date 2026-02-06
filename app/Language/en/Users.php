<?php

/**
 * User-related language strings (English)
 */
return [
    // General messages
    'notFound'        => 'User not found',
    'idRequired'      => 'User ID is required',
    'emailRequired'   => 'Email is required',
    'usernameRequired' => 'Username is required',
    'passwordRequired' => 'Password is required',
    'fieldRequired'   => 'At least one field (email or username) is required',

    // Success messages
    'deletedSuccess'  => 'User deleted successfully',

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
        'username' => [
            'required'     => 'Username is required',
            'alphaNumeric' => 'Username can only contain letters and numbers',
            'minLength'    => 'Username must be at least {0} characters',
            'unique'       => 'This username is already taken',
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
        'credentialsRequired' => 'Username and password are required',
    ],
];
