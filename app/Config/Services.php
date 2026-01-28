<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */

    /*
     * =========================================================================
     * Dependency Injection Pattern for API Services
     * =========================================================================
     *
     * As the application grows and you add more resources (Products, Orders, etc.),
     * you can centralize service creation here to:
     *
     * 1. Avoid manual DI in controllers
     * 2. Enable service reusability across the application
     * 3. Simplify testing with shared instances
     * 4. Maintain consistent initialization logic
     *
     * Example pattern for UserService (for future implementation):
     *
     * public static function userService($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('userService');
     *     }
     *
     *     $db = \Config\Database::connect();
     *     $repository = new \App\Repositories\UserRepository($db);
     *     return new \App\Services\UserService($repository);
     * }
     *
     * Usage in controllers would then become:
     *
     * protected $userService;
     *
     * public function __construct()
     * {
     *     $this->userService = \Config\Services::userService();
     * }
     *
     * Benefits:
     * - Single source of truth for service dependencies
     * - Easy to swap implementations for testing
     * - Shared instances reduce memory overhead
     * - Consistent initialization across the app
     */
}
