<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// API v1 Routes with rate limiting
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1', 'filter' => 'throttle'], function ($routes) {
    // Public authentication routes
    $routes->post('auth/login', 'AuthController::login');
    $routes->post('auth/register', 'AuthController::register');

    // Email verification routes (public)
    $routes->post('auth/verify-email', 'VerificationController::verify');

    // Password reset routes (public)
    $routes->post('auth/forgot-password', 'PasswordResetController::sendResetLink');
    $routes->get('auth/validate-reset-token', 'PasswordResetController::validateToken');
    $routes->post('auth/reset-password', 'PasswordResetController::resetPassword');

    // Protected routes (require JWT authentication)
    $routes->group('', ['filter' => 'jwtauth'], function ($routes) {
        // Auth routes
        $routes->get('auth/me', 'AuthController::me');

        // Email verification (protected)
        $routes->post('auth/resend-verification', 'VerificationController::resend');

        // User routes - read-only for all authenticated users
        $routes->get('users', 'UserController::index');
        $routes->get('users/(:num)', 'UserController::show/$1');

        // User routes - admin only (create, update, delete)
        $routes->group('', ['filter' => 'roleauth:admin'], function ($routes) {
            $routes->post('users', 'UserController::create');
            $routes->put('users/(:num)', 'UserController::update/$1');
            $routes->delete('users/(:num)', 'UserController::delete/$1');
        });
    });
});
