<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CompanyProfile extends Migration
{
    public function up(): void
    {
        $columns = [];

        if (! $this->db->fieldExists('phone', 'tenants')) {
            $columns['phone'] = ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true];
        }

        if (! $this->db->fieldExists('address', 'tenants')) {
            $columns['address'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
        }

        if (! $this->db->fieldExists('logo', 'tenants')) {
            $columns['logo'] = ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true];
        }

        if ($columns !== []) {
            $this->forge->addColumn('tenants', $columns);
        }
    }

    public function down(): void
    {
        foreach (['phone', 'address', 'logo'] as $column) {
            if ($this->db->fieldExists($column, 'tenants')) {
                $this->forge->dropColumn('tenants', $column);
            }
        }
    }
}
