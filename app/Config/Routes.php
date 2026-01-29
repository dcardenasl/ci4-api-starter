<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// API v1 Routes with rate limiting
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1', 'filter' => 'throttle'], function($routes) {
    // Public authentication routes
    $routes->post('auth/login', 'AuthController::login');
    $routes->post('auth/register', 'AuthController::register');

    // Protected routes (require JWT authentication)
    $routes->group('', ['filter' => 'jwtauth'], function($routes) {
        // Auth routes
        $routes->get('auth/me', 'AuthController::me');

        // User routes - read-only for all authenticated users
        $routes->get('users', 'UserController::index');
        $routes->get('users/(:num)', 'UserController::show/$1');

        // User routes - admin only (create, update, delete)
        $routes->group('', ['filter' => 'roleauth:admin'], function($routes) {
            $routes->post('users', 'UserController::create');
            $routes->put('users/(:num)', 'UserController::update/$1');
            $routes->delete('users/(:num)', 'UserController::delete/$1');
        });
    });
});
