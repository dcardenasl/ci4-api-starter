<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

/**
 * --------------------------------------------------------------------
 * API Information
 * --------------------------------------------------------------------
 */

$routes->get('/', static function () {
    return response()->setJSON([
        'name'        => 'CodeIgniter 4 API Starter',
        'version'     => '1.0.0',
        'description' => 'Production-ready REST API with JWT authentication',
        'documentation' => [
            'openapi' => base_url('swagger.json'),
            'github'  => 'https://github.com/david-cardenas/ci4-api-starter',
        ],
        'timestamp' => date('Y-m-d H:i:s'),
    ])->setStatusCode(200);
});

/**
 * --------------------------------------------------------------------
 * Modular Route Loader
 * --------------------------------------------------------------------
 */

// 1. Load System/Health routes at root level
if (file_exists(APPPATH . 'Config/Routes/v1/system.php')) {
    require APPPATH . 'Config/Routes/v1/system.php';
}

// 2. Load Domain routes under api/v1 group
$routes->group('api/v1', function ($routes) {
    $routesDir = APPPATH . 'Config/Routes/v1';

    if (is_dir($routesDir)) {
        $files = glob($routesDir . '/*.php');
        foreach ($files as $file) {
            // Skip system routes as they are already loaded at root
            if (basename($file) === 'system.php') {
                continue;
            }
            require $file;
        }
    }
});
