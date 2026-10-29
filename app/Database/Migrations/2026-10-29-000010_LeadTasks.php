<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class LeadTasks extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('lead_tasks')) {
            return;
        }

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'       => ['type' => 'INT', 'unsigned' => true],
            'lead_id'         => ['type' => 'INT', 'unsigned' => true],
            'assigned_to'     => ['type' => 'INT', 'unsigned' => true],
            'assigned_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'subject'         => ['type' => 'VARCHAR', 'constraint' => 160],
            'priority'        => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'MEDIUM'],
            'task_type'       => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'LEAD'],
            'description'     => ['type' => 'TEXT', 'null' => true],
            'task_feedback'   => ['type' => 'TEXT', 'null' => true],
            'current_state'   => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'SCHEDULED'],
            'scheduled_at'    => ['type' => 'DATETIME'],
            'completed_date'  => ['type' => 'DATETIME', 'null' => true],
            'self_assigned'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'inserted_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'lead_id']);
        $this->forge->createTable('lead_tasks');
    }

    public function down(): void
    {
        $this->forge->dropTable('lead_tasks', true);
    }
}
