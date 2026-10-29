<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SeedDesignations extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('lookup_items')) {
            return;
        }

        $items = [
            ['property_advisor', 'Property Advisor', 1],
            ['team_head', 'Team Head', 2],
            ['office_staff', 'Office staff', 3],
            ['sales_manager', 'Sales manager', 4],
        ];

        foreach ($this->db->table('tenants')->select('id')->get()->getResultArray() as $tenant) {
            $tenantId = (int) $tenant['id'];

            foreach ($items as [$code, $label, $order]) {
                $exists = $this->db->table('lookup_items')
                    ->where('tenant_id', $tenantId)
                    ->where('kind', 'designation')
                    ->where('code', $code)
                    ->countAllResults();

                if ($exists > 0) {
                    continue;
                }

                $this->db->table('lookup_items')->insert([
                    'tenant_id'  => $tenantId,
                    'kind'       => 'designation',
                    'code'       => $code,
                    'label'      => $label,
                    'sort_order' => $order,
                    'active'     => 1,
                ]);
            }
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('lookup_items')) {
            $this->db->table('lookup_items')->where('kind', 'designation')->delete();
        }
    }
}
