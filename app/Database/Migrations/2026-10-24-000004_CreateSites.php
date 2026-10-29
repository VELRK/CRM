<?php

namespace App\Database\Migrations;

use App\Libraries\AccessCatalog;
use CodeIgniter\Database\Migration;

class CreateSites extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'   => ['type' => 'INT', 'unsigned' => true],
            'user_id'     => ['type' => 'INT', 'unsigned' => true],
            'designation' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'phone'       => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'status'      => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'active'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tenant_id', 'user_id']);
        $this->forge->createTable('staff_profiles');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 160],
            'location'   => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'planning'],
            'notes'      => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id');
        $this->forge->createTable('projects');

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'   => ['type' => 'INT', 'unsigned' => true],
            'project_id'  => ['type' => 'INT', 'unsigned' => true],
            'plot_no'     => ['type' => 'VARCHAR', 'constraint' => 40],
            'block'       => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => ''],
            'area_sqft'   => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'facing'      => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'price_minor' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'status'      => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'available'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'project_id']);
        $this->forge->createTable('plots');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 160],
            'phone'      => ['type' => 'VARCHAR', 'constraint' => 40],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'source'     => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'project_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'plot_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'owner_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'new'],
            'notes'      => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'status']);
        $this->forge->addKey('owner_id');
        $this->forge->createTable('leads');

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'    => ['type' => 'INT', 'unsigned' => true],
            'lead_id'      => ['type' => 'INT', 'unsigned' => true],
            'from_user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'to_user_id'   => ['type' => 'INT', 'unsigned' => true],
            'by_user_id'   => ['type' => 'INT', 'unsigned' => true],
            'note'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'lead_id']);
        $this->forge->createTable('lead_transfers');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
            'lead_id'    => ['type' => 'INT', 'unsigned' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'body'       => ['type' => 'TEXT'],
            'next_at'    => ['type' => 'DATETIME', 'null' => true],
            'done_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'lead_id']);
        $this->forge->createTable('followups');

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'     => ['type' => 'INT', 'unsigned' => true],
            'lead_id'       => ['type' => 'INT', 'unsigned' => true],
            'project_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'plot_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'staff_id'      => ['type' => 'INT', 'unsigned' => true],
            'scheduled_at'  => ['type' => 'DATETIME'],
            'status'        => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'planned'],
            'attended_at'   => ['type' => 'DATETIME', 'null' => true],
            'lead_attended' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'staff_present' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'marked_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'note'          => ['type' => 'TEXT', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'lead_id']);
        $this->forge->createTable('site_visits');

        $roles = $this->db->table('roles')->where('deleted_at', null)->get()->getResultArray();

        foreach ($roles as $role) {
            $keys = (int) $role['is_owner'] === 1
                ? AccessCatalog::keys()
                : AccessCatalog::forStarter((string) $role['name']);

            if ($keys === []) {
                continue;
            }

            $existing = $this->db->table('role_permissions')
                ->select('permission_key')
                ->where('role_id', (int) $role['id'])
                ->get()
                ->getResultArray();
            $have = array_column($existing, 'permission_key');

            foreach ($keys as $key) {
                if (in_array($key, $have, true)) {
                    continue;
                }

                $this->db->table('role_permissions')->insert([
                    'role_id'        => (int) $role['id'],
                    'permission_key' => $key,
                ]);
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('site_visits', true);
        $this->forge->dropTable('followups', true);
        $this->forge->dropTable('lead_transfers', true);
        $this->forge->dropTable('leads', true);
        $this->forge->dropTable('plots', true);
        $this->forge->dropTable('projects', true);
        $this->forge->dropTable('staff_profiles', true);
    }
}
