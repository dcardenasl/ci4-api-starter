<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('iam', ['namespace' => '\App\Controllers\Api\V1\Iam'], function ($routes) {

    // Roles, permissions and applications are managed exclusively by superadmins.
    // Admins consume the IAM graph indirectly: they assign roles to users via
    // the Users module form, gated by anti-escalation in UserRoleAssignmentService.
    $routes->group('', ['filter' => ['jwtauth', 'permission:iam.superadmin-access', 'throttle']], function ($routes) {
        // Application Routes (read-only lookup; apps are created via `php spark apps:bootstrap`)
        $routes->get('applications', 'ApplicationController::index');
        $routes->get('applications/(:num)', 'ApplicationController::show/$1');

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

        // User effective permissions scoped to an application (by code)
        $routes->get('users/(:num)/permissions', 'UserPermissionsController::index/$1');

        // Resource routes will be injected here
    });
});
