<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('demo-products', ['namespace' => '\App\Controllers\Api\V1\Catalog'], function ($routes) {

    // Auth & Admin Protected Group
    $routes->group('', ['filter' => ['jwtauth', 'roleauth:admin', 'throttle']], function ($routes) {
        $routes->get('', 'DemoproductController::index');
        $routes->get('(:num)', 'DemoproductController::show/$1');
        $routes->post('', 'DemoproductController::create');
        $routes->put('(:num)', 'DemoproductController::update/$1');
        $routes->delete('(:num)', 'DemoproductController::delete/$1');
    });
});
