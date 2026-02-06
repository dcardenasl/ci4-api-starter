<?php

/**
 * Authentication language strings (English)
 */
return [
    // JWT messages
    'headerMissing'           => 'Authorization header missing',
    'invalidFormat'           => 'Invalid authorization header format',
    'invalidToken'            => 'Invalid or expired token',
    'tokenRevoked'            => 'Token has been revoked',

    // Authentication messages
    'authRequired'            => 'Authentication required',
    'insufficientPermissions' => 'Insufficient permissions',

    // Rate limiting
    'rateLimitExceeded'       => 'Rate limit exceeded. Please try again later.',
    'tooManyRequests'         => 'Too many requests. Maximum {0} requests per {1} seconds allowed.',
    'tooManyLoginAttempts'    => 'Too many authentication attempts. Maximum {0} attempts per {1} minutes. Please try again later.',
];
