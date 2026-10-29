<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AdvisorReports extends Migration
{
    public function up(): void
    {
        $roles = $this->db->table('roles')
            ->select('id')
            ->where('name', 'Property Advisor')
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        foreach ($roles as $role) {
            foreach (['reports.view', 'exports.download'] as $key) {
                $has = $this->db->table('role_permissions')
                    ->where('role_id', (int) $role['id'])
                    ->where('permission_key', $key)
                    ->countAllResults();

                if ($has > 0) {
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
        $roles = $this->db->table('roles')
            ->select('id')
            ->where('name', 'Property Advisor')
            ->get()
            ->getResultArray();

        foreach ($roles as $role) {
            $this->db->table('role_permissions')
                ->where('role_id', (int) $role['id'])
                ->whereIn('permission_key', ['reports.view', 'exports.download'])
                ->delete();
        }
    }
}
