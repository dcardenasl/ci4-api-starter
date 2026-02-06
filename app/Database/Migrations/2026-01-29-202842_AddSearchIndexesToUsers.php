<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSearchIndexesToUsers extends Migration
{
    public function up()
    {
        try {
            if (! $this->db->tableExists('users')) {
                return;
            }

            if ($this->db->fieldExists('first_name', 'users')) {
                // Updated index
                $this->db->query('ALTER TABLE users ADD FULLTEXT KEY idx_search (email, first_name, last_name)');
            } else {
                // Minimal index (email only)
                $this->db->query('ALTER TABLE users ADD FULLTEXT KEY idx_search (email)');
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet or index already exists, skip silently
        }
    }

    public function down()
    {
        try {
            // Drop FULLTEXT index
            $this->db->query('ALTER TABLE users DROP INDEX idx_search');
        } catch (\Exception $e) {
            // Table doesn't exist or index doesn't exist, skip silently
        }
    }
}
