<?php

namespace Tests\Support\Factories;

use App\Models\UserModel;
use Faker\Factory;

class UserFactory
{
    private static $faker;

    /**
     * Create a test user with optional attributes
     *
     * @param array $attributes Custom attributes to override defaults
     * @return object User entity
     */
    public static function create(array $attributes = []): object
    {
        self::$faker = self::$faker ?? Factory::create();
        $userModel = new UserModel();

        $data = array_merge([
            'username' => self::$faker->userName(),
            'email' => self::$faker->email(),
            'password' => password_hash('Testpass123', PASSWORD_BCRYPT),
            'role' => 'user',
        ], $attributes);

        $id = $userModel->insert($data);
        return $userModel->find($id);
    }

    /**
     * Create a test admin user
     *
     * @param array $attributes Custom attributes to override defaults
     * @return object Admin user entity
     */
    public static function createAdmin(array $attributes = []): object
    {
        return self::create(array_merge(['role' => 'admin'], $attributes));
    }
}
