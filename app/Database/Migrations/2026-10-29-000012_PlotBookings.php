<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PlotBookings extends Migration
{
    public function up(): void
    {
        $this->column('plots', 'phase', ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true]);
        $this->column('plots', 'customer_name', ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true]);
        $this->column('plots', 'customer_phone', ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true]);
        $this->column('plots', 'associate_user_id', ['type' => 'INT', 'unsigned' => true, 'null' => true]);
        $this->column('plots', 'head_user_id', ['type' => 'INT', 'unsigned' => true, 'null' => true]);
        $this->column('plots', 'booking_on', ['type' => 'DATE', 'null' => true]);
        $this->column('plots', 'advance_minor', ['type' => 'BIGINT', 'default' => 0]);
        $this->column('plots', 'advance_received_on', ['type' => 'DATE', 'null' => true]);
        $this->column('plots', 'booking_amount_minor', ['type' => 'BIGINT', 'default' => 0]);
        $this->column('plots', 'expected_registration_on', ['type' => 'DATE', 'null' => true]);
        $this->column('plots', 'registered_on', ['type' => 'DATE', 'null' => true]);
        $this->column('plots', 'remarks', ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true]);
    }

    public function down(): void
    {
        foreach (['phase', 'customer_name', 'customer_phone', 'associate_user_id', 'head_user_id', 'booking_on', 'advance_minor', 'advance_received_on', 'booking_amount_minor', 'expected_registration_on', 'registered_on', 'remarks'] as $field) {
            if ($this->db->fieldExists($field, 'plots')) {
                $this->forge->dropColumn('plots', $field);
            }
        }
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function column(string $table, string $field, array $definition): void
    {
        if (! $this->db->fieldExists($field, $table)) {
            $this->forge->addColumn($table, [$field => $definition]);
        }
    }
}
