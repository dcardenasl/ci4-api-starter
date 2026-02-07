<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $email = env('ADMIN_SEED_EMAIL', 'admin@yourdomain.com');
        $password = env('ADMIN_SEED_PASSWORD', 'change-this-password');
        $firstName = env('ADMIN_SEED_FIRST_NAME', 'Admin');
        $lastName = env('ADMIN_SEED_LAST_NAME', 'User');

        $data = [
            [
                'email'      => $email,
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'password'   => password_hash($password, PASSWORD_BCRYPT),
                'role'       => 'admin',
                'status'     => 'active',
                'email_verified_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('users')->insertBatch($data);
    }
}
