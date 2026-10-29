<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PasswordResets extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'token_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'expires_at' => ['type' => 'DATETIME'],
            'used_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('token_hash');
        $this->forge->addKey('user_id');
        $this->forge->createTable('password_resets');
    }

    public function down(): void
    {
        $this->forge->dropTable('password_resets', true);
    }
}
