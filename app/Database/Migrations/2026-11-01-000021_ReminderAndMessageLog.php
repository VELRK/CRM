<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ReminderAndMessageLog extends Migration
{
    public function up(): void
    {
        foreach (['followups', 'site_visits', 'lead_tasks'] as $table) {
            if ($this->db->tableExists($table) && ! $this->db->fieldExists('reminder_for', $table)) {
                $this->forge->addColumn($table, [
                    'reminder_for' => ['type' => 'DATETIME', 'null' => true],
                ]);
            }
        }

        if (! $this->db->tableExists('whatsapp_messages')) {
            return;
        }

        if (! $this->db->fieldExists('detail', 'whatsapp_messages')) {
            $this->forge->addColumn('whatsapp_messages', [
                'detail' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true, 'after' => 'status'],
            ]);
        }

        if (! $this->db->fieldExists('kind', 'whatsapp_messages')) {
            $this->forge->addColumn('whatsapp_messages', [
                'kind' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'lead', 'after' => 'status'],
            ]);
        }

        $name = str_replace('`', '', $this->db->prefixTable('whatsapp_messages'));
        $column = $this->db->query('SHOW COLUMNS FROM `' . $name . "` LIKE 'lead_id'")->getRowArray();

        if ($column !== null && strtoupper((string) ($column['Null'] ?? '')) === 'NO') {
            $this->db->query('ALTER TABLE `' . $name . '` MODIFY `lead_id` INT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('whatsapp_messages')) {
            if ($this->db->fieldExists('detail', 'whatsapp_messages')) {
                $this->forge->dropColumn('whatsapp_messages', 'detail');
            }

            if ($this->db->fieldExists('kind', 'whatsapp_messages')) {
                $this->forge->dropColumn('whatsapp_messages', 'kind');
            }
        }

        foreach (['followups', 'site_visits', 'lead_tasks'] as $table) {
            if ($this->db->tableExists($table) && $this->db->fieldExists('reminder_for', $table)) {
                $this->forge->dropColumn($table, 'reminder_for');
            }
        }
    }
}
