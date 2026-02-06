<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateUsersForNamesAndOAuth extends Migration
{
    public function up()
    {
        // Drop username-related indexes if they exist
        try {
            $this->forge->dropKey('users', 'username');
        } catch (\Throwable $e) {
            // Ignore if key does not exist
        }

        try {
            $this->db->query('ALTER TABLE users DROP INDEX idx_search');
        } catch (\Throwable $e) {
            // Ignore if index does not exist
        }

        // Add new columns for names and OAuth metadata
        $this->forge->addColumn('users', [
            'first_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'email',
            ],
            'last_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'first_name',
            ],
            'oauth_provider' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'last_name',
            ],
            'oauth_provider_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'oauth_provider',
            ],
            'avatar_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'oauth_provider_id',
            ],
        ]);

        // Allow NULL password for future OAuth-only accounts
        $this->forge->modifyColumn('users', [
            'password' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
        ]);

        // Drop username column (if present)
        $fieldsList = $this->db->getFieldNames('users') ?? [];
        if (in_array('username', $fieldsList, true)) {
            try {
                $this->forge->dropColumn('users', 'username');
            } catch (\Throwable $e) {
                // Ignore if column cannot be dropped
            }
        }

        // Add updated FULLTEXT index
        try {
            $this->db->query('ALTER TABLE users ADD FULLTEXT KEY idx_search (email, first_name, last_name)');
        } catch (\Throwable $e) {
            // Ignore if not supported or already exists
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('users')) {
            return;
        }

        // Drop updated FULLTEXT index
        try {
            $this->db->query('ALTER TABLE users DROP INDEX idx_search');
        } catch (\Throwable $e) {
            // Ignore if index does not exist
        }

        // Re-add username column
        if (! $this->db->fieldExists('username', 'users')) {
            try {
                $this->forge->addColumn('users', [
                    'username' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'null'       => true,
                        'after'      => 'email',
                    ],
                ]);
            } catch (\Throwable $e) {
                // Ignore if column already exists or cannot be added
            }
        }

        // Drop new columns
        foreach (['avatar_url', 'oauth_provider_id', 'oauth_provider', 'last_name', 'first_name'] as $column) {
            if ($this->db->fieldExists($column, 'users')) {
                $this->forge->dropColumn('users', $column);
            }
        }

        // Restore password NOT NULL
        $this->forge->modifyColumn('users', [
            'password' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
        ]);

        // Restore FULLTEXT index (legacy)
        try {
            if ($this->db->fieldExists('username', 'users')) {
                $this->db->query('ALTER TABLE users ADD FULLTEXT KEY idx_search (username, email)');
            } elseif ($this->db->fieldExists('first_name', 'users')) {
                $this->db->query('ALTER TABLE users ADD FULLTEXT KEY idx_search (email, first_name, last_name)');
            } else {
                $this->db->query('ALTER TABLE users ADD FULLTEXT KEY idx_search (email)');
            }
        } catch (\Throwable $e) {
            // Ignore if not supported or already exists
        }

        // Restore unique index for username if present
        try {
            if ($this->db->fieldExists('username', 'users')) {
                $this->forge->addUniqueKey('username');
                $this->forge->processIndexes('users');
            }
        } catch (\Throwable $e) {
            // Ignore if unsupported
        }
    }
}
