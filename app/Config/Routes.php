<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// API Routes
$routes->group('api', ['namespace' => 'App\Controllers'], function($routes) {
    $routes->group('v1', function($routes) {
        $routes->resource('users', ['controller' => 'UserController']);
    });
});
