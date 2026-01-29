<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEmailVerificationToUsers extends Migration
{
    public function up()
    {
        $fields = [
            'email_verified_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'email_verification_token' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'null' => true,
                'unique' => true,
            ],
            'verification_token_expires' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ];

        $this->forge->addColumn('users', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['email_verified_at', 'email_verification_token', 'verification_token_expires']);
    }
}
