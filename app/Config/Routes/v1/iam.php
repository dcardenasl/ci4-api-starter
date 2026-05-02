<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('iam', ['namespace' => '\App\Controllers\Api\V1\Iam'], function ($routes) {

    // Auth & Admin Protected Group
    $routes->group('', ['filter' => ['jwtauth', 'permission:iam.admin-access', 'throttle']], function ($routes) {
        // Permission Routes
        $routes->get('permissions', 'PermissionController::index');
        $routes->get('permissions/(:num)', 'PermissionController::show/$1');
        $routes->post('permissions', 'PermissionController::create');
        $routes->put('permissions/(:num)', 'PermissionController::update/$1');
        $routes->delete('permissions/(:num)', 'PermissionController::delete/$1');

        // Role Routes
        $routes->get('roles', 'RoleController::index');
        $routes->get('roles/(:num)', 'RoleController::show/$1');
        $routes->post('roles', 'RoleController::create');
        $routes->put('roles/(:num)', 'RoleController::update/$1');
        $routes->delete('roles/(:num)', 'RoleController::delete/$1');

        // Role ↔ Permission relations
        $routes->get('roles/(:num)/permissions', 'RoleController::listPermissions/$1');
        $routes->post('roles/(:num)/permissions/attach', 'RoleController::attachPermissions/$1');
        $routes->delete('roles/(:num)/permissions/(:num)', 'RoleController::detachPermission/$1/$2');

        // Resource routes will be injected here
    });
});
