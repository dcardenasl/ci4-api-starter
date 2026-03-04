<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('users', ['filter' => ['jwtauth', 'throttle']], function ($routes) {
    $routes->get('(:num)', '\App\Controllers\Api\V1\Users\UserController::show/$1');

    // Admin only
    $routes->group('', ['filter' => 'roleauth:admin'], function ($routes) {
        $routes->get('', '\App\Controllers\Api\V1\Users\UserController::index');
        $routes->post('', '\App\Controllers\Api\V1\Users\UserController::create');
        $routes->put('(:num)', '\App\Controllers\Api\V1\Users\UserController::update/$1');
        $routes->delete('(:num)', '\App\Controllers\Api\V1\Users\UserController::delete/$1');
        $routes->post('(:num)/approve', '\App\Controllers\Api\V1\Users\UserController::approve/$1');
    });
});
