<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBook extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 160],
            'phone'      => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'website'    => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'owner_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id');
        $this->forge->createTable('companies');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 160],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'phone'      => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'tags'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'owner_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id');
        $this->forge->createTable('contacts');

        $this->forge->addField([
            'contact_id' => ['type' => 'INT', 'unsigned' => true],
            'company_id' => ['type' => 'INT', 'unsigned' => true],
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addKey(['contact_id', 'company_id'], true);
        $this->forge->addKey('company_id');
        $this->forge->createTable('contact_company');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 80],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id');
        $this->forge->createTable('pipelines');

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'   => ['type' => 'INT', 'unsigned' => true],
            'pipeline_id' => ['type' => 'INT', 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 80],
            'position'    => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'is_won'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_lost'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['pipeline_id', 'position']);
        $this->forge->createTable('stages');

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'    => ['type' => 'INT', 'unsigned' => true],
            'pipeline_id'  => ['type' => 'INT', 'unsigned' => true],
            'stage_id'     => ['type' => 'INT', 'unsigned' => true],
            'title'        => ['type' => 'VARCHAR', 'constraint' => 180],
            'company_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'contact_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'owner_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'amount_minor' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'currency'     => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'INR'],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id');
        $this->forge->addKey('stage_id');
        $this->forge->createTable('deals');
    }

    public function down(): void
    {
        $this->forge->dropTable('deals', true);
        $this->forge->dropTable('stages', true);
        $this->forge->dropTable('pipelines', true);
        $this->forge->dropTable('contact_company', true);
        $this->forge->dropTable('contacts', true);
        $this->forge->dropTable('companies', true);
    }
}
