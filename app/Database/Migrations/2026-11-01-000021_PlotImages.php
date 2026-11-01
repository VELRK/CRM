<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PlotImages extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('plot_images')) {
            return;
        }

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
            'plot_id'    => ['type' => 'INT', 'unsigned' => true],
            'stored'     => ['type' => 'VARCHAR', 'constraint' => 80],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'plot_id']);
        $this->forge->createTable('plot_images');
    }

    public function down(): void
    {
        $this->forge->dropTable('plot_images', true);
    }
}
