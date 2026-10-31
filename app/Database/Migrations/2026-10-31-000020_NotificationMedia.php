<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NotificationMedia extends Migration
{
    public function up(): void
    {
        foreach (['notification_logs', 'notifications'] as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            $columns = [];

            if (! $this->db->fieldExists('kind', $table)) {
                $columns['kind'] = ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'text'];
            }

            if (! $this->db->fieldExists('media', $table)) {
                $columns['media'] = ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true];
            }

            if ($columns !== []) {
                $this->forge->addColumn($table, $columns);
            }
        }
    }

    public function down(): void
    {
        foreach (['notification_logs', 'notifications'] as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            foreach (['media', 'kind'] as $column) {
                if ($this->db->fieldExists($column, $table)) {
                    $this->forge->dropColumn($table, $column);
                }
            }
        }
    }
}
