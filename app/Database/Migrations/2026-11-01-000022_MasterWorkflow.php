<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class MasterWorkflow extends Migration
{
    public function up(): void
    {
        $this->add('leads', 'approval_status', ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'approved']);
        $this->add('leads', 'approved_by', ['type' => 'INT', 'unsigned' => true, 'null' => true]);
        $this->add('leads', 'approved_at', ['type' => 'DATETIME', 'null' => true]);
        $this->add('followups', 'call_feedback', ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true]);
        $this->add('followups', 'experience', ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true]);
        $this->add('site_visits', 'visit_feedback', ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true]);
        $this->add('site_visits', 'experience', ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true]);
        $this->add('site_visits', 'visit_note', ['type' => 'TEXT', 'null' => true]);
        $this->add('lead_deals', 'plot_id', ['type' => 'INT', 'unsigned' => true, 'null' => true]);
        $this->add('lead_deals', 'approval_status', ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'approved']);
        $this->add('lead_deals', 'requested_stage', ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true]);
        $this->add('lead_deals', 'approved_by', ['type' => 'INT', 'unsigned' => true, 'null' => true]);
        $this->add('lead_deals', 'approved_at', ['type' => 'DATETIME', 'null' => true]);
    }

    public function down(): void
    {
    }

    /**
     * @param array<string, mixed> $field
     */
    private function add(string $table, string $column, array $field): void
    {
        if (! $this->db->tableExists($table) || $this->db->fieldExists($column, $table)) {
            return;
        }

        $this->forge->addColumn($table, [$column => $field]);
    }
}
