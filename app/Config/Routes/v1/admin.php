<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */

// API Keys
$routes->group('api-keys', ['filter' => ['jwtauth', 'roleauth:admin', 'throttle']], function ($routes) {
    $routes->get('', '\App\Controllers\Api\V1\Admin\ApiKeyController::index');
    $routes->post('', '\App\Controllers\Api\V1\Admin\ApiKeyController::create');
    $routes->get('(:num)', '\App\Controllers\Api\V1\Admin\ApiKeyController::show/$1');
    $routes->put('(:num)', '\App\Controllers\Api\V1\Admin\ApiKeyController::update/$1');
    $routes->delete('(:num)', '\App\Controllers\Api\V1\Admin\ApiKeyController::delete/$1');
});

// Metrics
$routes->group('metrics', ['filter' => ['jwtauth', 'roleauth:admin', 'throttle', 'featureToggle:metrics']], function ($routes) {
    $routes->get('', '\App\Controllers\Api\V1\Admin\MetricsController::index');
    $routes->get('requests', '\App\Controllers\Api\V1\Admin\MetricsController::requests');
    $routes->get('slow-requests', '\App\Controllers\Api\V1\Admin\MetricsController::slowRequests');
    $routes->get('custom/(:segment)', '\App\Controllers\Api\V1\Admin\MetricsController::custom/$1');
    $routes->post('record', '\App\Controllers\Api\V1\Admin\MetricsController::record');
});

// Audit
$routes->group('audit', ['filter' => ['jwtauth', 'roleauth:admin', 'throttle']], function ($routes) {
    $routes->get('', '\App\Controllers\Api\V1\Admin\AuditController::index');
    $routes->get('(:num)', '\App\Controllers\Api\V1\Admin\AuditController::show/$1');
    $routes->get('entity/(:segment)/(:num)', '\App\Controllers\Api\V1\Admin\AuditController::byEntity/$1/$2');
});

// Admin Catalog Management
$routes->group('catalogs', ['filter' => ['jwtauth', 'roleauth:admin', 'throttle']], function ($routes) {
    $routes->get('', '\App\Controllers\Api\V1\Admin\CatalogController::index');
    $routes->get('audit-facets', '\App\Controllers\Api\V1\Admin\CatalogController::auditFacets');
});
