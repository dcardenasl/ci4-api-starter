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

    /**
     * User Service
     *
     * Proporciona UserService con todas sus dependencias inyectadas
     *
     * @param bool $getShared
     * @return \App\Services\UserService
     */
    public static function userService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('userService');
        }

        // Crear UserModel (CI4 proporciona la conexión DB automáticamente)
        $userModel = new \App\Models\UserModel();

        return new \App\Services\UserService($userModel);
    }

    /**
     * JWT Service
     *
     * Provides JWT token encoding and decoding functionality
     *
     * @param bool $getShared
     * @return \App\Services\JwtService
     */
    public static function jwtService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('jwtService');
        }

        return new \App\Services\JwtService();
    }

    // Servicios futuros seguirán el mismo patrón:
    // public static function productService(bool $getShared = true) { ... }
}
