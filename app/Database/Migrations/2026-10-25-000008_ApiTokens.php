<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ApiTokens extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('api_tokens')) {
            return;
        }

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'    => ['type' => 'INT', 'unsigned' => true],
            'user_id'      => ['type' => 'INT', 'unsigned' => true],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 80],
            'token_hash'   => ['type' => 'CHAR', 'constraint' => 64],
            'last_used_at' => ['type' => 'DATETIME', 'null' => true],
            'revoked_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey(['tenant_id', 'user_id']);
        $this->forge->createTable('api_tokens');
    }

    public function down(): void
    {
        $this->forge->dropTable('api_tokens', true);
    }
}
