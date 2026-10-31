<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PushNotifications extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('mobile_device_tokens')) {
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'    => ['type' => 'INT', 'unsigned' => true],
                'user_id'      => ['type' => 'INT', 'unsigned' => true],
                'device_token' => ['type' => 'VARCHAR', 'constraint' => 255],
                'device_type'  => ['type' => 'VARCHAR', 'constraint' => 16],
                'device_name'  => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'is_active'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'last_used_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['tenant_id', 'device_token']);
            $this->forge->addKey(['tenant_id', 'user_id', 'is_active']);
            $this->forge->createTable('mobile_device_tokens');
        }

        if (! $this->db->tableExists('notification_templates')) {
            $this->forge->addField([
                'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'         => ['type' => 'INT', 'unsigned' => true],
                'notification_name' => ['type' => 'VARCHAR', 'constraint' => 120],
                'module'            => ['type' => 'VARCHAR', 'constraint' => 40],
                'event_key'         => ['type' => 'VARCHAR', 'constraint' => 40],
                'title'             => ['type' => 'VARCHAR', 'constraint' => 190],
                'message'           => ['type' => 'VARCHAR', 'constraint' => 500],
                'payload_json'      => ['type' => 'TEXT', 'null' => true],
                'status'            => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'active'],
                'created_by'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at'        => ['type' => 'DATETIME', 'null' => true],
                'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['tenant_id', 'event_key', 'status']);
            $this->forge->createTable('notification_templates');
        }

        if (! $this->db->tableExists('notification_configurations')) {
            $this->forge->addField([
                'id'                    => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'             => ['type' => 'INT', 'unsigned' => true],
                'template_id'           => ['type' => 'INT', 'unsigned' => true],
                'recipient_type'        => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'combination'],
                'recipient_config_json' => ['type' => 'TEXT', 'null' => true],
                'conditions_json'       => ['type' => 'TEXT', 'null' => true],
                'is_enabled'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at'            => ['type' => 'DATETIME', 'null' => true],
                'updated_at'            => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['tenant_id', 'template_id', 'is_enabled']);
            $this->forge->createTable('notification_configurations');
        }

        if (! $this->db->tableExists('notification_logs')) {
            $this->forge->addField([
                'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'         => ['type' => 'INT', 'unsigned' => true],
                'template_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'event_key'         => ['type' => 'VARCHAR', 'constraint' => 40],
                'title'             => ['type' => 'VARCHAR', 'constraint' => 190],
                'message'           => ['type' => 'VARCHAR', 'constraint' => 500],
                'subject_type'      => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
                'subject_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'triggered_by'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'total_recipients'  => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'successful_count'  => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'failed_count'      => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
                'created_at'        => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['tenant_id', 'event_key', 'created_at']);
            $this->forge->addKey(['tenant_id', 'subject_type', 'subject_id']);
            $this->forge->createTable('notification_logs');
        }

        if (! $this->db->tableExists('notification_recipients')) {
            $this->forge->addField([
                'id'                  => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'           => ['type' => 'INT', 'unsigned' => true],
                'notification_log_id' => ['type' => 'INT', 'unsigned' => true],
                'user_id'             => ['type' => 'INT', 'unsigned' => true],
                'device_token_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'notice_id'           => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'delivery_status'     => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'failed'],
                'fcm_message_id'      => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'error_message'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'sent_at'             => ['type' => 'DATETIME', 'null' => true],
                'read_at'             => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['tenant_id', 'notification_log_id']);
            $this->forge->addKey(['tenant_id', 'user_id']);
            $this->forge->createTable('notification_recipients');
        }

        if ($this->db->tableExists('role_permissions')) {
            $roles = $this->db->table('roles')
                ->select('id')
                ->groupStart()
                    ->where('is_owner', 1)
                    ->orWhere('name', 'Admin')
                ->groupEnd()
                ->where('deleted_at', null)
                ->get()
                ->getResultArray();

            foreach ($roles as $role) {
                $exists = $this->db->table('role_permissions')
                    ->where('role_id', (int) $role['id'])
                    ->where('permission_key', 'notifications.manage')
                    ->countAllResults();

                if ($exists > 0) {
                    continue;
                }

                $this->db->table('role_permissions')->insert([
                    'role_id'        => (int) $role['id'],
                    'permission_key' => 'notifications.manage',
                ]);
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('notification_recipients', true);
        $this->forge->dropTable('notification_logs', true);
        $this->forge->dropTable('notification_configurations', true);
        $this->forge->dropTable('notification_templates', true);
        $this->forge->dropTable('mobile_device_tokens', true);
    }
}
