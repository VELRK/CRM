<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PlotCommission extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('commission_minor', 'plots')) {
            $this->forge->addColumn('plots', [
                'commission_minor' => ['type' => 'BIGINT', 'default' => 0],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('commission_minor', 'plots')) {
            $this->forge->dropColumn('plots', 'commission_minor');
        }
    }
}
