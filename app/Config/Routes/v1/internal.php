<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */

/**
 * Internal M2M routes — accessible only via X-App-Key.
 *
 * These endpoints let trusted Domain apps call Hub services (email queuing,
 * file metadata resolution, …) without exposing them to the public internet
 * and without requiring a user JWT. Authentication is via the
 * `appKeyRequired` filter (same mechanism as public.php).
 *
 * This file is a template for the "internal M2M endpoint" pattern — add a
 * new route here (plus a controller under
 * app/Controllers/Api/V1/Internal/) any time a Domain app needs to call
 * back into the Hub for something the Hub already owns.
 */
$routes->group('internal', ['filter' => ['appKeyRequired', 'throttle']], function ($routes): void {
    $routes->post('email/queue', '\App\Controllers\Api\V1\Internal\InternalEmailController::queue');
    $routes->get('files/batch-meta', '\App\Controllers\Api\V1\Internal\InternalFileMetaController::batchMeta');
});
