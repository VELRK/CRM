<?php

namespace App\Database\Migrations;

use App\Libraries\AccessCatalog;
use CodeIgniter\Database\Migration;

class AlignEstate extends Migration
{
    public function up(): void
    {
        $this->column('projects', 'project_type', ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true]);
        $this->column('projects', 'total_plots', ['type' => 'INT', 'unsigned' => true, 'null' => true]);
        $this->column('projects', 'launch_on', ['type' => 'DATE', 'null' => true]);

        $this->column('plots', 'unit', ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'sqft']);
        $this->column('plots', 'price_per_unit_minor', ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0]);

        $this->column('leads', 'phone_secondary', ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true]);
        $this->column('leads', 'phone_key', ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true]);
        $this->column('leads', 'location', ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true]);
        $this->column('leads', 'budget_minor', ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0]);
        $this->column('leads', 'team_id', ['type' => 'INT', 'unsigned' => true, 'null' => true]);
        $this->column('leads', 'remarks', ['type' => 'TEXT', 'null' => true]);

        $this->column('followups', 'followup_type', ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'call']);
        $this->column('followups', 'status', ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'pending']);
        $this->column('followups', 'outcome', ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true]);
        $this->column('followups', 'assigned_user_id', ['type' => 'INT', 'unsigned' => true, 'null' => true]);

        $this->column('site_visits', 'pickup', ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true]);
        $this->column('site_visits', 'created_by', ['type' => 'INT', 'unsigned' => true, 'null' => true]);

        $this->column('staff_profiles', 'employee_code', ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true]);
        $this->column('staff_profiles', 'joining_on', ['type' => 'DATE', 'null' => true]);
        $this->column('staff_profiles', 'address', ['type' => 'TEXT', 'null' => true]);
        $this->column('staff_profiles', 'attendance_applicable', ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]);
        $this->column('staff_profiles', 'lead_eligible', ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]);
        $this->column('staff_profiles', 'incentive_eligible', ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]);
        $this->column('staff_profiles', 'relieved_on', ['type' => 'DATE', 'null' => true]);
        $this->column('staff_profiles', 'team_id', ['type' => 'INT', 'unsigned' => true, 'null' => true]);

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'   => ['type' => 'INT', 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 120],
            'head_user_id'=> ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id');
        $this->forge->createTable('teams');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
            'team_id'    => ['type' => 'INT', 'unsigned' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'from_on'    => ['type' => 'DATE'],
            'to_on'      => ['type' => 'DATE', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'team_id']);
        $this->forge->createTable('team_memberships');

        $this->historyTable('plot_status_history', 'plot_id');
        $this->historyTable('lead_status_history', 'lead_id');

        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'         => ['type' => 'INT', 'unsigned' => true],
            'plot_id'           => ['type' => 'INT', 'unsigned' => true],
            'project_id'        => ['type' => 'INT', 'unsigned' => true],
            'lead_id'           => ['type' => 'INT', 'unsigned' => true],
            'credited_user_id'  => ['type' => 'INT', 'unsigned' => true],
            'head_user_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'team_id'           => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'sale_value_minor'  => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'sale_on'           => ['type' => 'DATE'],
            'source'            => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'plot_status'       => ['type' => 'VARCHAR', 'constraint' => 16],
            'split_user_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'split_minor'       => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'status'            => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'active'],
            'reverse_note'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by'        => ['type' => 'INT', 'unsigned' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'plot_id']);
        $this->forge->createTable('sales');

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'    => ['type' => 'INT', 'unsigned' => true],
            'sale_id'      => ['type' => 'INT', 'unsigned' => true],
            'user_id'      => ['type' => 'INT', 'unsigned' => true],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'pending'],
            'amount_minor' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'rule_note'    => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'approver_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'approved_at'  => ['type' => 'DATETIME', 'null' => true],
            'paid_at'      => ['type' => 'DATETIME', 'null' => true],
            'remarks'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'sale_id']);
        $this->forge->createTable('incentives');

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'     => ['type' => 'INT', 'unsigned' => true],
            'user_id'       => ['type' => 'INT', 'unsigned' => true],
            'work_date'     => ['type' => 'DATE'],
            'check_in'      => ['type' => 'DATETIME', 'null' => true],
            'check_out'     => ['type' => 'DATETIME', 'null' => true],
            'check_in_lat'  => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'check_in_lng'  => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'check_out_lat' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'check_out_lng' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'status'        => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'present'],
            'note'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'corrected_by'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tenant_id', 'user_id', 'work_date']);
        $this->forge->createTable('attendance');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
            'kind'       => ['type' => 'VARCHAR', 'constraint' => 32],
            'code'       => ['type' => 'VARCHAR', 'constraint' => 40],
            'label'      => ['type' => 'VARCHAR', 'constraint' => 80],
            'sort_order' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tenant_id', 'kind', 'code']);
        $this->forge->createTable('lookup_items');

        $this->forge->addField([
            'tenant_id'     => ['type' => 'INT', 'unsigned' => true],
            'setting_key'   => ['type' => 'VARCHAR', 'constraint' => 64],
            'setting_value' => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->forge->addKey(['tenant_id', 'setting_key'], true);
        $this->forge->createTable('tenant_settings');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 80],
            'language'   => ['type' => 'VARCHAR', 'constraint' => 8, 'default' => 'en'],
            'body'       => ['type' => 'TEXT'],
            'active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id');
        $this->forge->createTable('whatsapp_templates');

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'   => ['type' => 'INT', 'unsigned' => true],
            'lead_id'     => ['type' => 'INT', 'unsigned' => true],
            'user_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'direction'   => ['type' => 'VARCHAR', 'constraint' => 8, 'default' => 'out'],
            'template_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'body'        => ['type' => 'TEXT'],
            'status'      => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'failed'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'lead_id']);
        $this->forge->createTable('whatsapp_messages');

        $this->db->query("UPDATE projects SET status = 'upcoming' WHERE status = 'planning'");
        $this->db->query("UPDATE projects SET status = 'ongoing' WHERE status = 'active'");
        $this->db->query("UPDATE site_visits SET status = 'scheduled' WHERE status = 'planned'");
        $this->db->query("UPDATE site_visits SET status = 'completed' WHERE status = 'attended'");
        $this->db->query("UPDATE site_visits SET status = 'no_show' WHERE status = 'missed'");
        $this->db->query("UPDATE followups SET status = 'done' WHERE done_at IS NOT NULL");

        foreach ($this->db->table('leads')->select('id, phone')->get()->getResultArray() as $lead) {
            $this->db->table('leads')->where('id', (int) $lead['id'])->update([
                'phone_key' => preg_replace('/\D+/', '', (string) $lead['phone']),
            ]);
        }

        $lookups = [
            'lead_status' => [
                ['new', 'New'], ['contacted', 'Contacted'], ['followup', 'Follow-up'],
                ['site_visit', 'Site visit scheduled'], ['site_visit_done', 'Site visit done'],
                ['negotiation', 'Negotiation'], ['booked', 'Booked'], ['lost', 'Lost'],
                ['not_interested', 'Not interested'],
            ],
            'plot_status' => [
                ['available', 'Available'], ['hold', 'Hold'], ['booked', 'Booked'],
                ['sold', 'Sold'], ['registered', 'Registered'], ['blocked', 'Blocked'],
            ],
            'lead_source' => [
                ['walk-in', 'Walk-in'], ['personal', 'Personal'], ['assigned', 'Assigned'],
                ['api', 'API'], ['whatsapp', 'WhatsApp'],
            ],
        ];

        foreach ($this->db->table('tenants')->select('id')->get()->getResultArray() as $tenant) {
            $tenantId = (int) $tenant['id'];
            foreach ($lookups as $kind => $items) {
                foreach ($items as $index => [$code, $label]) {
                    $this->db->table('lookup_items')->insert([
                        'tenant_id'  => $tenantId,
                        'kind'       => $kind,
                        'code'       => $code,
                        'label'      => $label,
                        'sort_order' => $index + 1,
                        'active'     => 1,
                    ]);
                }
            }

            $this->db->table('tenant_settings')->insert([
                'tenant_id'     => $tenantId,
                'setting_key'   => 'incentive_percent',
                'setting_value' => '2',
            ]);

            foreach (['Team Head', 'Property Advisor'] as $roleName) {
                $role = $this->db->table('roles')
                    ->where('tenant_id', $tenantId)
                    ->where('name', $roleName)
                    ->where('deleted_at', null)
                    ->get()
                    ->getRowArray();

                if ($role === null) {
                    $this->db->table('roles')->insert([
                        'tenant_id'  => $tenantId,
                        'name'       => $roleName,
                        'is_owner'   => 0,
                        'is_system'  => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        foreach ($this->db->table('roles')->where('deleted_at', null)->get()->getResultArray() as $role) {
            $keys = (int) $role['is_owner'] === 1
                ? AccessCatalog::keys()
                : AccessCatalog::forStarter((string) $role['name']);

            if ($keys === []) {
                continue;
            }

            $have = array_column(
                $this->db->table('role_permissions')->select('permission_key')->where('role_id', (int) $role['id'])->get()->getResultArray(),
                'permission_key',
            );

            foreach ($keys as $key) {
                if (! in_array($key, $have, true)) {
                    $this->db->table('role_permissions')->insert([
                        'role_id'        => (int) $role['id'],
                        'permission_key' => $key,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('whatsapp_messages', true);
        $this->forge->dropTable('whatsapp_templates', true);
        $this->forge->dropTable('tenant_settings', true);
        $this->forge->dropTable('lookup_items', true);
        $this->forge->dropTable('attendance', true);
        $this->forge->dropTable('incentives', true);
        $this->forge->dropTable('sales', true);
        $this->forge->dropTable('lead_status_history', true);
        $this->forge->dropTable('plot_status_history', true);
        $this->forge->dropTable('team_memberships', true);
        $this->forge->dropTable('teams', true);
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

    private function historyTable(string $table, string $subject): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
            $subject     => ['type' => 'INT', 'unsigned' => true],
            'old_status' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'new_status' => ['type' => 'VARCHAR', 'constraint' => 32],
            'user_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'remark'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', $subject]);
        $this->forge->createTable($table);
    }
}
