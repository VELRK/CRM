<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class TeamProjects extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('team_projects')) {
            return;
        }

        $this->forge->addField([
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
            'team_id'    => ['type' => 'INT', 'unsigned' => true],
            'project_id' => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addKey(['tenant_id', 'team_id', 'project_id'], true);
        $this->forge->createTable('team_projects');
    }

    public function down(): void
    {
        $this->forge->dropTable('team_projects', true);
    }
}
