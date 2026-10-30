<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AttendanceLocations extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('attendance_location_categories')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
                'name'       => ['type' => 'VARCHAR', 'constraint' => 80],
                'status'     => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'active'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['tenant_id', 'status']);
            $this->forge->createTable('attendance_location_categories');
        }

        if (! $this->db->tableExists('attendance_locations')) {
            $this->forge->addField([
                'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'        => ['type' => 'INT', 'unsigned' => true],
                'category_id'      => ['type' => 'INT', 'unsigned' => true],
                'name'             => ['type' => 'VARCHAR', 'constraint' => 120],
                'address'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'latitude'         => ['type' => 'DECIMAL', 'constraint' => '10,7'],
                'longitude'        => ['type' => 'DECIMAL', 'constraint' => '10,7'],
                'radius_metres'    => ['type' => 'INT', 'unsigned' => true],
                'work_start'       => ['type' => 'TIME', 'null' => true],
                'work_end'         => ['type' => 'TIME', 'null' => true],
                'accuracy_metres'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'status'           => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'active'],
                'entity_type'      => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'entity_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
                'updated_at'       => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['tenant_id', 'status']);
            $this->forge->addKey(['tenant_id', 'entity_type', 'entity_id']);
            $this->forge->createTable('attendance_locations');
        }

        if (! $this->db->tableExists('attendance_location_users')) {
            $this->forge->addField([
                'tenant_id'   => ['type' => 'INT', 'unsigned' => true],
                'location_id' => ['type' => 'INT', 'unsigned' => true],
                'user_id'     => ['type' => 'INT', 'unsigned' => true],
            ]);
            $this->forge->addKey(['tenant_id', 'location_id', 'user_id'], true);
            $this->forge->createTable('attendance_location_users');
        }

        if (! $this->db->tableExists('attendance_sessions')) {
            $this->forge->addField([
                'id'                  => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'           => ['type' => 'INT', 'unsigned' => true],
                'user_id'             => ['type' => 'INT', 'unsigned' => true],
                'location_id'         => ['type' => 'INT', 'unsigned' => true],
                'work_date'           => ['type' => 'DATE'],
                'check_in'            => ['type' => 'DATETIME', 'null' => true],
                'check_out'           => ['type' => 'DATETIME', 'null' => true],
                'check_in_lat'        => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'check_in_lng'        => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'check_in_accuracy'   => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
                'check_out_lat'       => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'check_out_lng'       => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'check_out_accuracy'  => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
                'status'              => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'present'],
                'note'                => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'corrected_by'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at'          => ['type' => 'DATETIME', 'null' => true],
                'updated_at'          => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['tenant_id', 'user_id', 'work_date']);
            $this->forge->createTable('attendance_sessions');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('attendance_sessions', true);
        $this->forge->dropTable('attendance_location_users', true);
        $this->forge->dropTable('attendance_locations', true);
        $this->forge->dropTable('attendance_location_categories', true);
    }
}
