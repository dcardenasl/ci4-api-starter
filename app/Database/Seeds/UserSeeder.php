<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'username'   => 'john_doe',
                'email'      => 'john@example.com',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'username'   => 'jane_smith',
                'email'      => 'jane@example.com',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'username'   => 'bob_wilson',
                'email'      => 'bob@example.com',
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ];

        // Using Query Builder
        $this->db->table('users')->insertBatch($data);
    }
}
