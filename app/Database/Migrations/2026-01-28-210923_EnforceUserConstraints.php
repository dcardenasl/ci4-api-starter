<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnforceUserConstraints extends Migration
{
    public function up()
    {
        try {
            // Clean existing NULL data first (only if table has data)
            $this->db->query("UPDATE users SET username = CONCAT('user_', id) WHERE username IS NULL OR username = ''");
            $this->db->query("UPDATE users SET email = CONCAT('user_', id, '@example.com') WHERE email IS NULL OR email = ''");
        } catch (\Exception $e) {
            // Table might be empty or not exist yet, continue
        }

        // Modify columns to NOT NULL
        $fields = [
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
        ];
        $this->forge->modifyColumn('users', $fields);

        // Add UNIQUE constraints
        $this->forge->addUniqueKey('username');
        $this->forge->processIndexes('users');

        $this->forge->addUniqueKey('email');
        $this->forge->processIndexes('users');
    }

    public function down()
    {
        // Drop UNIQUE constraints
        $this->forge->dropKey('users', 'username');
        $this->forge->dropKey('users', 'email');

        // Modify columns back to nullable
        $fields = [
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
        ];
        $this->forge->modifyColumn('users', $fields);
    }
}
