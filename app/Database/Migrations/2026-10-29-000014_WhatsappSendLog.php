<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class WhatsappSendLog extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('to_phone', 'whatsapp_messages')) {
            $this->forge->addColumn('whatsapp_messages', [
                'to_phone' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'template_id'],
            ]);
        }

        if (! $this->db->fieldExists('subject', 'whatsapp_messages')) {
            $this->forge->addColumn('whatsapp_messages', [
                'subject' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true, 'after' => 'to_phone'],
            ]);
        }

        $this->db->query("UPDATE whatsapp_messages m
            LEFT JOIN leads l ON l.id = m.lead_id
            LEFT JOIN whatsapp_templates t ON t.id = m.template_id
            SET m.to_phone = IFNULL(NULLIF(m.to_phone, ''), IFNULL(l.phone_number, l.phone)),
                m.subject = IFNULL(NULLIF(m.subject, ''), t.name)");
    }

    public function down(): void
    {
        if ($this->db->fieldExists('subject', 'whatsapp_messages')) {
            $this->forge->dropColumn('whatsapp_messages', 'subject');
        }

        if ($this->db->fieldExists('to_phone', 'whatsapp_messages')) {
            $this->forge->dropColumn('whatsapp_messages', 'to_phone');
        }
    }
}
