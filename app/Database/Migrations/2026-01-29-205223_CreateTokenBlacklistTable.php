<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTokenBlacklistTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'token_jti' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token_jti');
        $this->forge->addKey('expires_at');
        $this->forge->createTable('token_blacklist');
    }

    public function down()
    {
        $this->forge->dropTable('token_blacklist');
    }
}
