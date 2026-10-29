<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

class WorkspaceService
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * @return array{user_id: int, tenant_id: int, role_id: int}
     */
    public function register(string $name, string $email, string $password, string $workspace): array
    {
        $now = date('Y-m-d H:i:s');

        $this->db->transStart();

        $this->db->table('users')->insert([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => $this->hashPassword($password),
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
        $userId = (int) $this->db->insertID();

        $this->db->table('tenants')->insert([
            'name'       => $workspace,
            'plan_code'  => 'starter',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $tenantId = (int) $this->db->insertID();

        $ownerRoleId = 0;

        foreach (['Owner', 'Admin', 'Manager', 'Member', 'Viewer'] as $roleName) {
            $this->db->table('roles')->insert([
                'tenant_id'  => $tenantId,
                'name'       => $roleName,
                'is_owner'   => $roleName === 'Owner' ? 1 : 0,
                'is_system'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $roleId = (int) $this->db->insertID();

            if ($roleName === 'Owner') {
                $ownerRoleId = $roleId;
            }

            $this->replaceAccess($roleId, AccessCatalog::forStarter($roleName));
        }

        $this->db->table('tenant_users')->insert([
            'tenant_id'  => $tenantId,
            'user_id'    => $userId,
            'role_id'    => $ownerRoleId,
            'created_at' => $now,
        ]);

        (new BookService($this->db))->ensurePipeline($tenantId);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new \RuntimeException('Could not create the workspace.');
        }

        return [
            'user_id'   => $userId,
            'tenant_id' => $tenantId,
            'role_id'   => $ownerRoleId,
        ];
    }

    /**
     * @param list<string> $keys
     */
    public function replaceAccess(int $roleId, array $keys): void
    {
        $this->db->table('role_permissions')->where('role_id', $roleId)->delete();

        if ($keys === []) {
            return;
        }

        $rows = [];

        foreach ($keys as $key) {
            $rows[] = [
                'role_id'        => $roleId,
                'permission_key' => $key,
            ];
        }

        $this->db->table('role_permissions')->insertBatch($rows);
    }

    public function hashPassword(string $password): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;

        return password_hash($password, $algo);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function userByEmail(string $email): ?array
    {
        $row = $this->db->table('users')
            ->where('email', $email)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function membership(int $userId, ?int $tenantId = null): ?array
    {
        $builder = $this->db->table('tenant_users')
            ->select('tenant_users.tenant_id, tenant_users.role_id, tenants.name AS workspace_name, tenants.plan_code, users.name AS user_name, users.email, roles.name AS role_name, roles.is_owner')
            ->join('tenants', 'tenants.id = tenant_users.tenant_id')
            ->join('users', 'users.id = tenant_users.user_id')
            ->join('roles', 'roles.id = tenant_users.role_id')
            ->where('tenant_users.user_id', $userId)
            ->where('tenants.deleted_at', null)
            ->where('roles.deleted_at', null);

        if ($tenantId !== null) {
            $builder->where('tenant_users.tenant_id', $tenantId);
        }

        $row = $builder->orderBy('tenant_users.id', 'ASC')->get()->getRowArray();

        return $row ?: null;
    }

    /**
     * @return list<string>
     */
    public function permissions(int $roleId): array
    {
        $rows = $this->db->table('role_permissions')
            ->select('permission_key')
            ->where('role_id', $roleId)
            ->get()
            ->getResultArray();

        return array_column($rows, 'permission_key');
    }
}
