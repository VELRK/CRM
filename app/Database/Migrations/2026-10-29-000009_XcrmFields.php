<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * XCRM lead, follow-up, visit, and user columns.
 * The workspace is tenant_id. There is no company id column.
 */
class XcrmFields extends Migration
{
    public function up(): void
    {
        $this->column('leads', 'client_name_prefix', ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true]);
        $this->column('leads', 'client_first_name', ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true]);
        $this->column('leads', 'client_last_name', ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true]);
        $this->column('leads', 'phone_number', ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true]);
        $this->column('leads', 'alternate_phone_number', ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true]);
        $this->column('leads', 'lead_source', ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true]);
        $this->column('leads', 'campaign_source', ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true]);
        $this->column('leads', 'lead_stage', ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true]);
        $this->column('leads', 'assigned_to', ['type' => 'INT', 'unsigned' => true, 'null' => true]);
        $this->column('leads', 'assigned_type', ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'USER']);
        $this->column('leads', 'assigned_name', ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true]);
        $this->column('leads', 'assigned_by', ['type' => 'INT', 'unsigned' => true, 'null' => true]);
        $this->column('leads', 'assigned_date', ['type' => 'DATETIME', 'null' => true]);
        $this->column('leads', 'property_type', ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true]);
        $this->column('leads', 'area', ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true]);
        $this->column('leads', 'client_preference', ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true]);
        $this->column('leads', 'client_budget_value', ['type' => 'INT', 'unsigned' => true, 'default' => 0]);
        $this->column('leads', 'client_budget_type', ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true]);
        $this->column('leads', 'client_street', ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true]);
        $this->column('leads', 'client_city', ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true]);
        $this->column('leads', 'client_state', ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true]);
        $this->column('leads', 'client_country', ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true]);
        $this->column('leads', 'client_zip_code', ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true]);
        $this->column('leads', 'description', ['type' => 'TEXT', 'null' => true]);
        $this->column('leads', 'interested_projects', ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true]);
        $this->column('leads', 'lead_type', ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'USER']);
        $this->column('leads', 'group_id', ['type' => 'INT', 'unsigned' => true, 'null' => true]);
        $this->column('leads', 'self_assigned', ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0]);
        $this->column('leads', 'inserted_by', ['type' => 'INT', 'unsigned' => true, 'null' => true]);
        $this->column('leads', 'project_name', ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true]);

        $this->column('followups', 'call_subject', ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true]);
        $this->column('followups', 'call_purpose', ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true]);
        $this->column('followups', 'call_feedback', ['type' => 'TEXT', 'null' => true]);
        $this->column('followups', 'call_notes', ['type' => 'TEXT', 'null' => true]);
        $this->column('followups', 'call_description', ['type' => 'TEXT', 'null' => true]);
        $this->column('followups', 'call_scheduled_at', ['type' => 'DATETIME', 'null' => true]);
        $this->column('followups', 'current_state', ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true]);
        $this->column('followups', 'assigned_to', ['type' => 'INT', 'unsigned' => true, 'null' => true]);
        $this->column('followups', 'assigned_by', ['type' => 'INT', 'unsigned' => true, 'null' => true]);
        $this->column('followups', 'completed_date', ['type' => 'DATETIME', 'null' => true]);
        $this->column('followups', 'self_assigned', ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0]);
        $this->column('followups', 'inserted_by', ['type' => 'INT', 'unsigned' => true, 'null' => true]);

        $this->column('site_visits', 'description', ['type' => 'TEXT', 'null' => true]);
        $this->column('site_visits', 'current_state', ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true]);
        $this->column('site_visits', 'assigned_to', ['type' => 'INT', 'unsigned' => true, 'null' => true]);
        $this->column('site_visits', 'visit_type', ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true]);
        $this->column('site_visits', 'visit_result', ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true]);
        $this->column('site_visits', 'visit_feedback', ['type' => 'TEXT', 'null' => true]);
        $this->column('site_visits', 'scheduled_date_time', ['type' => 'DATETIME', 'null' => true]);
        $this->column('site_visits', 'inserted_by', ['type' => 'INT', 'unsigned' => true, 'null' => true]);
        $this->column('site_visits', 'assigned_by', ['type' => 'INT', 'unsigned' => true, 'null' => true]);
        $this->column('site_visits', 'completed_date', ['type' => 'DATETIME', 'null' => true]);
        $this->column('site_visits', 'self_assigned', ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0]);

        $this->column('staff_profiles', 'phone_number', ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true]);
        $this->column('staff_profiles', 'profile', ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true]);
        $this->column('staff_profiles', 'dob', ['type' => 'DATE', 'null' => true]);
        $this->column('staff_profiles', 'street', ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true]);
        $this->column('staff_profiles', 'city', ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true]);
        $this->column('staff_profiles', 'state', ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true]);
        $this->column('staff_profiles', 'zip_code', ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true]);
        $this->column('staff_profiles', 'user_status', ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]);
        $this->column('staff_profiles', 'active', ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1]);
        $this->column('staff_profiles', 'group_id', ['type' => 'INT', 'unsigned' => true, 'null' => true]);

        $this->db->query(
            "UPDATE leads SET
                client_first_name = IFNULL(client_first_name, name),
                phone_number = IFNULL(phone_number, phone),
                alternate_phone_number = IFNULL(alternate_phone_number, phone_secondary),
                lead_source = IFNULL(lead_source, source),
                lead_stage = IFNULL(lead_stage, status),
                assigned_to = IFNULL(assigned_to, owner_id),
                assigned_type = IF(assigned_type IS NULL OR assigned_type = '', 'USER', assigned_type),
                description = IFNULL(description, notes)"
        );
        $this->db->query(
            'UPDATE leads l LEFT JOIN users u ON u.id = l.owner_id
             SET l.assigned_name = u.name
             WHERE (l.assigned_name IS NULL OR l.assigned_name = \'\') AND l.owner_id IS NOT NULL'
        );
        $this->db->query(
            "UPDATE followups SET
                call_description = IFNULL(call_description, body),
                call_scheduled_at = IFNULL(call_scheduled_at, next_at),
                current_state = IFNULL(current_state, UPPER(status)),
                assigned_to = IFNULL(assigned_to, assigned_user_id),
                completed_date = IFNULL(completed_date, done_at),
                inserted_by = IFNULL(inserted_by, user_id)"
        );
        $this->db->query(
            "UPDATE site_visits SET
                description = IFNULL(description, note),
                current_state = IFNULL(current_state, UPPER(status)),
                assigned_to = IFNULL(assigned_to, staff_id),
                scheduled_date_time = IFNULL(scheduled_date_time, scheduled_at),
                inserted_by = IFNULL(inserted_by, created_by)"
        );
        $this->db->query(
            "UPDATE staff_profiles SET
                phone_number = IFNULL(phone_number, phone),
                user_status = IF(status = 'active', 1, 0),
                active = IF(status IN ('inactive', 'relieved'), 0, 1),
                group_id = IFNULL(group_id, team_id)"
        );
    }

    public function down(): void
    {
        foreach ([
            'leads' => ['client_name_prefix', 'client_first_name', 'client_last_name', 'phone_number', 'alternate_phone_number', 'lead_source', 'campaign_source', 'lead_stage', 'assigned_to', 'assigned_type', 'assigned_name', 'assigned_by', 'assigned_date', 'property_type', 'area', 'client_preference', 'client_budget_value', 'client_budget_type', 'client_street', 'client_city', 'client_state', 'client_country', 'client_zip_code', 'description', 'interested_projects', 'lead_type', 'group_id', 'self_assigned', 'inserted_by', 'project_name'],
            'followups' => ['call_subject', 'call_purpose', 'call_feedback', 'call_notes', 'call_description', 'call_scheduled_at', 'current_state', 'assigned_to', 'assigned_by', 'completed_date', 'self_assigned', 'inserted_by'],
            'site_visits' => ['description', 'current_state', 'assigned_to', 'visit_type', 'visit_result', 'visit_feedback', 'scheduled_date_time', 'inserted_by', 'assigned_by', 'completed_date', 'self_assigned'],
            'staff_profiles' => ['phone_number', 'profile', 'dob', 'street', 'city', 'state', 'zip_code', 'user_status', 'active', 'group_id'],
        ] as $table => $fields) {
            foreach ($fields as $field) {
                if ($this->db->fieldExists($field, $table)) {
                    $this->forge->dropColumn($table, $field);
                }
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
