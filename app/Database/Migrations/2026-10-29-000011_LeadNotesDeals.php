<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class LeadNotesDeals extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('lead_notes')) {
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'    => ['type' => 'INT', 'unsigned' => true],
                'lead_id'      => ['type' => 'INT', 'unsigned' => true],
                'user_id'      => ['type' => 'INT', 'unsigned' => true],
                'note_heading' => ['type' => 'VARCHAR', 'constraint' => 160],
                'note_content' => ['type' => 'TEXT', 'null' => true],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['tenant_id', 'lead_id']);
            $this->forge->createTable('lead_notes');
        }

        if (! $this->db->tableExists('lead_deals')) {
            $this->forge->addField([
                'id'                          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'                   => ['type' => 'INT', 'unsigned' => true],
                'lead_id'                     => ['type' => 'INT', 'unsigned' => true],
                'deal_stage'                  => ['type' => 'VARCHAR', 'constraint' => 40],
                'deal_stage_description'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'total_deal_value'            => ['type' => 'BIGINT', 'default' => 0],
                'deal_description'            => ['type' => 'TEXT', 'null' => true],
                'project_details'             => ['type' => 'TEXT', 'null' => true],
                'booking_date'                => ['type' => 'DATE', 'null' => true],
                'expected_registration_date'  => ['type' => 'DATE', 'null' => true],
                'registration_date'           => ['type' => 'DATE', 'null' => true],
                'inserted_by'                 => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at'                  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'                  => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'                  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['tenant_id', 'lead_id']);
            $this->forge->createTable('lead_deals');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('lead_deals', true);
        $this->forge->dropTable('lead_notes', true);
    }
}
