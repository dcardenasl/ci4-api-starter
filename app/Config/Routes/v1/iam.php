<?php

/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('iam', ['namespace' => '\App\Controllers\Api\V1\Iam'], function ($routes) {

    // Admin-accessible group: a regular admin can read the IAM graph and
    // manage memberships/roles for non-SuperAdmin users. The hierarchical
    // guardrails inside each service prevent privilege escalation:
    //   - `is_system` roles are immutable for non-SA actors,
    //   - permissions/roles cannot be granted that the actor doesn't hold,
    //   - SuperAdmin subjects (users, memberships) are off-limits to admins.
    // SA-only mutations (Permission CRUD, modifying is_system roles) are
    // enforced one layer deeper, in the service `before*` hooks.
    $routes->group('', ['filter' => ['jwtauth', 'permission:iam.admin-access', 'throttle']], function ($routes) {
        // AppUserMembership Routes
        $routes->get('memberships', 'AppUserMembershipController::index');
        $routes->get('memberships/(:num)', 'AppUserMembershipController::show/$1');
        $routes->post('memberships', 'AppUserMembershipController::create');
        $routes->put('memberships/(:num)', 'AppUserMembershipController::update/$1');
        $routes->delete('memberships/(:num)', 'AppUserMembershipController::delete/$1');

        // Membership ↔ Role relations
        $routes->get('memberships/(:num)/roles', 'AppUserMembershipController::listRoles/$1');
        $routes->post('memberships/(:num)/roles/attach', 'AppUserMembershipController::attachRoles/$1');
        $routes->delete('memberships/(:num)/roles/(:num)', 'AppUserMembershipController::detachRole/$1/$2');

        // User-centric IAM lookups
        $routes->get('users/(:num)/memberships', 'AppUserMembershipController::listForUser/$1');
        $routes->get('users/(:num)/permissions', 'AppUserMembershipController::listEffectivePermissionsForUser/$1');

        // Application Routes (read-only lookup)
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

        // Resource routes will be injected here
    });
});
