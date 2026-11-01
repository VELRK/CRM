<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ProjectImage extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('projects') || $this->db->fieldExists('image_stored', 'projects')) {
            return;
        }

        $this->forge->addColumn('projects', [
            'image_stored' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
        ]);
    }

    public function down(): void
    {
    }
}
