<?php

namespace Tests\Support\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TestUserSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'username' => 'testuser',
                'email'    => 'test@example.com',
                'password' => password_hash('Testpass123', PASSWORD_BCRYPT),
                'role'     => 'user',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'username' => 'adminuser',
                'email'    => 'admin@example.com',
                'password' => password_hash('Adminpass123', PASSWORD_BCRYPT),
                'role'     => 'admin',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        // Clear existing test data
        $this->db->table('users')->truncate();

        // Insert test data
        $this->db->table('users')->insertBatch($data);
    }
}
