<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// API v1 Routes
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1'], function($routes) {
    // Public authentication routes
    $routes->post('auth/login', 'AuthController::login');
    $routes->post('auth/register', 'AuthController::register');

    // Protected routes (require JWT authentication)
    $routes->group('', ['filter' => 'jwtauth'], function($routes) {
        // Auth routes
        $routes->get('auth/me', 'AuthController::me');

        // User routes
        $routes->get('users', 'UserController::index');
        $routes->get('users/(:num)', 'UserController::show/$1');
        $routes->post('users', 'UserController::create');
        $routes->put('users/(:num)', 'UserController::update/$1');
        $routes->delete('users/(:num)', 'UserController::delete/$1');
    });
});
