<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateIdentity extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 160],
            'plan_code'  => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'starter'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('tenants');

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 160],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 190],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->createTable('users');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 80],
            'is_owner'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_system'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id');
        $this->forge->addUniqueKey(['tenant_id', 'name']);
        $this->forge->createTable('roles');

        $this->forge->addField([
            'role_id'        => ['type' => 'INT', 'unsigned' => true],
            'permission_key' => ['type' => 'VARCHAR', 'constraint' => 64],
        ]);
        $this->forge->addKey(['role_id', 'permission_key'], true);
        $this->forge->createTable('role_permissions');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'role_id'    => ['type' => 'INT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tenant_id', 'user_id']);
        $this->forge->addKey('role_id');
        $this->forge->createTable('tenant_users');

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'   => ['type' => 'INT', 'unsigned' => true],
            'email'       => ['type' => 'VARCHAR', 'constraint' => 190],
            'role_id'     => ['type' => 'INT', 'unsigned' => true],
            'token_hash'  => ['type' => 'CHAR', 'constraint' => 64],
            'expires_at'  => ['type' => 'DATETIME'],
            'accepted_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('token_hash');
        $this->forge->createTable('invites');
    }

    public function down(): void
    {
        $this->forge->dropTable('invites', true);
        $this->forge->dropTable('tenant_users', true);
        $this->forge->dropTable('role_permissions', true);
        $this->forge->dropTable('roles', true);
        $this->forge->dropTable('users', true);
        $this->forge->dropTable('tenants', true);
    }
}
