<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnforceUserConstraints extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('users')) {
            return;
        }

        try {
            // Clean existing NULL data first (only if table has data)
            $this->db->query("UPDATE users SET email = CONCAT('user_', id, '@example.com') WHERE email IS NULL OR email = ''");
        } catch (\Exception $e) {
            // Table might be empty or not exist yet, continue
        }

        // Modify columns to NOT NULL
        $fields = [
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
        ];
        try {
            $this->forge->modifyColumn('users', $fields);
        } catch (\Throwable $e) {
            // Ignore if columns cannot be modified
        }

        // Add UNIQUE constraints
        $this->forge->addUniqueKey('email');
        $this->forge->processIndexes('users');
    }

    public function down()
    {
        if (! $this->db->tableExists('users')) {
            return;
        }

        // Drop UNIQUE constraints
        try {
            $this->forge->dropKey('users', 'email');
        } catch (\Throwable $e) {
            // Ignore if key does not exist
        }

        // Modify columns back to nullable
        $fields = [
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
        ];
        try {
            $this->forge->modifyColumn('users', $fields);
        } catch (\Throwable $e) {
            // Ignore if columns cannot be modified
        }
    }
}
