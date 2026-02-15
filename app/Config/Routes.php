<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
// API Information Endpoint
$routes->get('/', static function () {
    return response()->setJSON([
        'name'        => 'CodeIgniter 4 API Starter',
        'version'     => '1.0.0',
        'description' => 'Production-ready REST API with JWT authentication',
        'documentation' => [
            'openapi' => base_url('swagger.json'),
            'github'  => 'https://github.com/david-cardenas/ci4-api-starter',
        ],
        'endpoints' => [
            'health' => base_url('health'),
            'ping'   => base_url('ping'),
            'auth'   => base_url('api/v1/auth'),
            'users'  => base_url('api/v1/users'),
            'files'  => base_url('api/v1/files'),
        ],
        'timestamp' => date('Y-m-d H:i:s'),
    ])->setStatusCode(200);
});

// Health check endpoints (public, no rate limiting for Kubernetes/monitoring)
$routes->group('', ['namespace' => 'App\Controllers\Api\V1'], function ($routes) {
    $routes->get('health', 'HealthController::index');
    $routes->get('ping', 'HealthController::ping');
    $routes->get('ready', 'HealthController::ready');
    $routes->get('live', 'HealthController::live');
});

// API v1 Routes with rate limiting
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1', 'filter' => 'throttle'], function ($routes) {
    // Public authentication routes (with stricter auth-specific rate limiting)
    $routes->group('', ['filter' => 'authThrottle'], function ($routes) {
        $routes->post('auth/login', 'AuthController::login');
        $routes->post('auth/register', 'AuthController::register');
        $routes->post('auth/refresh', 'TokenController::refresh');

        // Password reset routes (public)
        $routes->post('auth/forgot-password', 'PasswordResetController::sendResetLink');
        $routes->post('auth/reset-password', 'PasswordResetController::resetPassword');
    });

    // Email verification routes (public, standard rate limiting)
    $routes->get('auth/verify-email', 'VerificationController::verify');
    $routes->post('auth/verify-email', 'VerificationController::verify');
    $routes->get('auth/validate-reset-token', 'PasswordResetController::validateToken');

    // Protected routes (require JWT authentication)
    $routes->group('', ['filter' => 'jwtauth'], function ($routes) {
        // Auth routes
        $routes->get('auth/me', 'AuthController::me');

        // Email verification (protected)
        $routes->post('auth/resend-verification', 'VerificationController::resend');

        // Token revocation routes (protected)
        $routes->post('auth/revoke', 'TokenController::revoke');
        $routes->post('auth/revoke-all', 'TokenController::revokeAll');

        // User routes - read-only for all authenticated users
        $routes->get('users', 'UserController::index');
        $routes->get('users/(:num)', 'UserController::show/$1');

        // File routes - all authenticated users
        $routes->get('files', 'FileController::index');
        $routes->post('files/upload', 'FileController::upload');
        $routes->get('files/(:num)', 'FileController::show/$1');
        $routes->delete('files/(:num)', 'FileController::delete/$1');

        // User routes - admin only (create, update, delete)
        $routes->group('', ['filter' => 'roleauth:admin'], function ($routes) {
            $routes->post('users', 'UserController::create');
            $routes->put('users/(:num)', 'UserController::update/$1');
            $routes->delete('users/(:num)', 'UserController::delete/$1');
            $routes->post('users/(:num)/approve', 'UserController::approve/$1');

            // Metrics endpoints (admin only)
            $routes->get('metrics', 'MetricsController::index');
            $routes->get('metrics/requests', 'MetricsController::requests');
            $routes->get('metrics/slow-requests', 'MetricsController::slowRequests');
            $routes->get('metrics/custom/(:segment)', 'MetricsController::custom/$1');
            $routes->post('metrics/record', 'MetricsController::record');

            // Audit endpoints (admin only)
            $routes->get('audit', 'AuditController::index');
            $routes->get('audit/(:num)', 'AuditController::show/$1');
            $routes->get('audit/entity/(:segment)/(:num)', 'AuditController::byEntity/$1/$2');
        });
    });
});
