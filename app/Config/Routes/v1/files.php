<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('files', ['filter' => ['jwtauth', 'throttle']], function ($routes) {
    $routes->get('', '\App\Controllers\Api\V1\Files\FileController::index');
    $routes->post('upload', '\App\Controllers\Api\V1\Files\FileController::upload');
    $routes->get('(:num)', '\App\Controllers\Api\V1\Files\FileController::show/$1');
    $routes->delete('(:num)', '\App\Controllers\Api\V1\Files\FileController::delete/$1');
});
