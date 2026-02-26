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

// Health check endpoints
$routes->group('', ['namespace' => '\App\Controllers\Api\V1\System'], function ($routes) {
    $routes->get('health', 'HealthController::index');
    $routes->get('ping', 'HealthController::ping');
    $routes->get('ready', 'HealthController::ready');
    $routes->get('live', 'HealthController::live');
});

// API v1 Routes
$routes->group('api/v1', function ($routes) {

    // Public authentication routes
    $routes->group('', ['filter' => 'authThrottle'], function ($routes) {
        $routes->post('auth/login', '\App\Controllers\Api\V1\Auth\AuthController::login');
        $routes->post('auth/google-login', '\App\Controllers\Api\V1\Auth\AuthController::googleLogin');
        $routes->post('auth/register', '\App\Controllers\Api\V1\Identity\RegistrationController::register');
        $routes->post('auth/refresh', '\App\Controllers\Api\V1\Auth\TokenController::refresh');

        // Password reset routes
        $routes->post('auth/forgot-password', '\App\Controllers\Api\V1\Identity\PasswordResetController::sendResetLink');
        $routes->post('auth/reset-password', '\App\Controllers\Api\V1\Identity\PasswordResetController::resetPassword');
        $routes->get('auth/validate-reset-token', '\App\Controllers\Api\V1\Identity\PasswordResetController::validateToken');

        // Email verification (Public GET/POST)
        $routes->get('auth/verify-email', '\App\Controllers\Api\V1\Identity\VerificationController::verify');
        $routes->post('auth/verify-email', '\App\Controllers\Api\V1\Identity\VerificationController::verify');
    });

    // Protected routes (require JWT authentication)
    $routes->group('', ['filter' => 'jwtauth'], function ($routes) {
        // Auth routes
        $routes->get('auth/me', '\App\Controllers\Api\V1\Auth\AuthController::me');

        // Email verification (Protected)
        $routes->post('auth/resend-verification', '\App\Controllers\Api\V1\Identity\VerificationController::resend');

        // Token revocation
        $routes->post('auth/revoke', '\App\Controllers\Api\V1\Auth\TokenController::revoke');
        $routes->post('auth/revoke-all', '\App\Controllers\Api\V1\Auth\TokenController::revokeAll');

        // User routes
        $routes->get('users/(:num)', '\App\Controllers\Api\V1\Users\UserController::show/$1');

        // File routes
        $routes->get('files', '\App\Controllers\Api\V1\Files\FileController::index');
        $routes->post('files/upload', '\App\Controllers\Api\V1\Files\FileController::upload');
        $routes->get('files/(:num)', '\App\Controllers\Api\V1\Files\FileController::show/$1');
        $routes->delete('files/(:num)', '\App\Controllers\Api\V1\Files\FileController::delete/$1');

        // Admin routes
        $routes->group('', ['filter' => 'roleauth:admin'], function ($routes) {
            $routes->get('users', '\App\Controllers\Api\V1\Users\UserController::index');
            $routes->post('users', '\App\Controllers\Api\V1\Users\UserController::create');
            $routes->put('users/(:num)', '\App\Controllers\Api\V1\Users\UserController::update/$1');
            $routes->delete('users/(:num)', '\App\Controllers\Api\V1\Users\UserController::delete/$1');
            $routes->post('users/(:num)/approve', '\App\Controllers\Api\V1\Users\UserController::approve/$1');

            $routes->get('api-keys', '\App\Controllers\Api\V1\Admin\ApiKeyController::index');
            $routes->post('api-keys', '\App\Controllers\Api\V1\Admin\ApiKeyController::create');
            $routes->get('api-keys/(:num)', '\App\Controllers\Api\V1\Admin\ApiKeyController::show/$1');
            $routes->put('api-keys/(:num)', '\App\Controllers\Api\V1\Admin\ApiKeyController::update/$1');
            $routes->delete('api-keys/(:num)', '\App\Controllers\Api\V1\Admin\ApiKeyController::delete/$1');

            $routes->get('metrics', '\App\Controllers\Api\V1\Admin\MetricsController::index');
            $routes->get('metrics/requests', '\App\Controllers\Api\V1\Admin\MetricsController::requests');
            $routes->get('metrics/slow-requests', '\App\Controllers\Api\V1\Admin\MetricsController::slowRequests');
            $routes->get('metrics/custom/(:segment)', '\App\Controllers\Api\V1\Admin\MetricsController::custom/$1');
            $routes->post('metrics/record', '\App\Controllers\Api\V1\Admin\MetricsController::record');

            $routes->get('audit', '\App\Controllers\Api\V1\Admin\AuditController::index');
            $routes->get('audit/(:num)', '\App\Controllers\Api\V1\Admin\AuditController::show/$1');
            $routes->get('audit/entity/(:segment)/(:num)', '\App\Controllers\Api\V1\Admin\AuditController::byEntity/$1/$2');
        });
    });
});
