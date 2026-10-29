<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class RoleService
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly WorkspaceService $workspaces,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId): array
    {
        return $this->db->query(
            'SELECT roles.id, roles.name, roles.is_owner, roles.is_system,
                    (SELECT COUNT(*) FROM tenant_users WHERE tenant_users.role_id = roles.id) AS member_count,
                    (SELECT COUNT(*) FROM role_permissions WHERE role_permissions.role_id = roles.id) AS access_count
             FROM roles
             WHERE roles.tenant_id = ? AND roles.deleted_at IS NULL
             ORDER BY roles.is_owner DESC, roles.is_system DESC, roles.name ASC',
            [$tenantId],
        )->getResultArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $tenantId, int $roleId): ?array
    {
        $row = $this->db->table('roles')
            ->where('tenant_id', $tenantId)
            ->where('id', $roleId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    public function create(int $tenantId, string $planCode, string $name): int
    {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('Give the role a name.');
        }

        $cap   = AccessCatalog::PLAN_CAPS[$planCode] ?? AccessCatalog::PLAN_CAPS['starter'];
        $count = $this->db->table('roles')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->countAllResults();

        if ($count >= $cap) {
            throw new RuntimeException('This plan allows ' . $cap . ' roles. Remove a custom role or change plan before adding another.');
        }

        $exists = $this->db->table('roles')
            ->where('tenant_id', $tenantId)
            ->where('name', $name)
            ->where('deleted_at', null)
            ->countAllResults();

        if ($exists > 0) {
            throw new RuntimeException('A role with that name already exists.');
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('roles')->insert([
            'tenant_id'  => $tenantId,
            'name'       => $name,
            'is_owner'   => 0,
            'is_system'  => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * @param list<string> $keys
     */
    public function allocate(int $tenantId, int $roleId, array $keys): void
    {
        $role = $this->find($tenantId, $roleId);

        if ($role === null) {
            throw new RuntimeException('That role is not in this workspace.');
        }

        if ((int) $role['is_owner'] === 1) {
            throw new RuntimeException('Owner access stays locked.');
        }

        $allowed = array_flip(AccessCatalog::keys());
        $clean   = [];

        foreach ($keys as $key) {
            if (isset($allowed[$key])) {
                $clean[$key] = $key;
            }
        }

        $this->db->transStart();
        $this->workspaces->replaceAccess($roleId, array_values($clean));
        $this->db->table('roles')->where('id', $roleId)->update([
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new RuntimeException('Could not save access.');
        }
    }

    /**
     * @return list<string>
     */
    public function keysFor(int $roleId): array
    {
        return array_column(
            $this->db->table('role_permissions')->select('permission_key')->where('role_id', $roleId)->get()->getResultArray(),
            'permission_key',
        );
    }

    /**
     * Create a person on this workspace. The company is the signed-in tenant.
     *
     * @param array<string, mixed> $input
     */
    public function createMember(int $tenantId, string $planCode, array $input, bool $actorIsOwner, int $actorId): int
    {
        $name  = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $phone = trim((string) ($input['phoneNumber'] ?? $input['phone_number'] ?? $input['phone'] ?? ''));
        $roleId = (int) ($input['role_id'] ?? $input['roleId'] ?? 0);
        $password = (string) ($input['password'] ?? '');

        if ($name === '' || $email === '' || $phone === '') {
            throw new RuntimeException('A user needs a name, email, and phone number.');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email.');
        }

        $role = $this->find($tenantId, $roleId);

        if ($role === null) {
            throw new RuntimeException('Choose a role on this workspace.');
        }

        $existing = $this->workspaces->userByEmail($email);

        if ($existing !== null) {
            $already = $this->db->table('tenant_users')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $existing['id'])
                ->countAllResults();

            if ($already > 0) {
                throw new RuntimeException('This person is already in the workspace.');
            }
        }

        $teamId = (int) ($input['groupId'] ?? $input['group_id'] ?? $input['team_id'] ?? 0);
        $active = array_key_exists('active', $input) ? (empty($input['active']) ? 0 : 1) : 1;
        $now    = date('Y-m-d H:i:s');

        $this->db->transStart();

        if ($existing === null) {
            if (strlen($password) < 8) {
                throw new RuntimeException('Use a password of at least 8 characters.');
            }

            $this->db->table('users')->insert([
                'name'          => $name,
                'email'         => $email,
                'password_hash' => $this->workspaces->hashPassword($password),
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $userId = (int) $this->db->insertID();
        } else {
            $userId = (int) $existing['id'];
        }

        $this->db->table('tenant_users')->insert([
            'tenant_id'  => $tenantId,
            'user_id'    => $userId,
            'role_id'    => $roleId,
            'created_at' => $now,
        ]);

        $this->db->table('staff_profiles')->insert([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'phone'        => $phone,
            'phone_number' => $phone,
            'profile'      => $this->text($input['profile'] ?? null),
            'dob'          => $this->text($input['dob'] ?? null),
            'street'       => $this->text($input['street'] ?? null),
            'city'         => $this->text($input['city'] ?? null),
            'state'        => $this->text($input['state'] ?? null),
            'zip_code'     => $this->text($input['zipCode'] ?? $input['zip_code'] ?? null),
            'team_id'      => $teamId > 0 ? $teamId : null,
            'group_id'     => $teamId > 0 ? $teamId : null,
            'status'       => $active === 1 ? 'active' : 'inactive',
            'user_status'  => $active,
            'active'       => $active,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new RuntimeException('Could not create the user.');
        }

        return $userId;
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function delete(int $tenantId, int $roleId): void
    {
        $role = $this->find($tenantId, $roleId);

        if ($role === null) {
            throw new RuntimeException('That role is not in this workspace.');
        }

        if ((int) $role['is_system'] === 1 || (int) $role['is_owner'] === 1) {
            throw new RuntimeException('Starter roles stay in the workspace. You can change their access, except Owner.');
        }

        $members = $this->db->table('tenant_users')->where('role_id', $roleId)->countAllResults();

        if ($members > 0) {
            throw new RuntimeException('Move people off this role before deleting it.');
        }

        $suffix = ' #' . $roleId;
        $base   = mb_substr((string) $role['name'], 0, 80 - strlen($suffix));

        $this->db->table('roles')->where('id', $roleId)->update([
            'name'       => $base . $suffix,
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function assign(int $tenantId, int $memberId, int $roleId, bool $actorIsOwner): void
    {
        $role = $this->find($tenantId, $roleId);

        if ($role === null) {
            throw new RuntimeException('That role is not in this workspace.');
        }

        if ((int) $role['is_owner'] === 1 && ! $actorIsOwner) {
            throw new RuntimeException('Only an Owner can give someone the Owner role.');
        }

        $member = $this->db->table('tenant_users')
            ->select('tenant_users.id, tenant_users.role_id, roles.is_owner')
            ->join('roles', 'roles.id = tenant_users.role_id')
            ->where('tenant_users.id', $memberId)
            ->where('tenant_users.tenant_id', $tenantId)
            ->get()
            ->getRowArray();

        if ($member === null) {
            throw new RuntimeException('That person is not in this workspace.');
        }

        if ((int) $member['is_owner'] === 1 && (int) $role['is_owner'] !== 1) {
            $owners = $this->db->table('tenant_users')
                ->join('roles', 'roles.id = tenant_users.role_id')
                ->where('tenant_users.tenant_id', $tenantId)
                ->where('roles.is_owner', 1)
                ->countAllResults();

            if ($owners <= 1) {
                throw new RuntimeException('The workspace needs at least one Owner.');
            }
        }

        $this->db->table('tenant_users')->where('id', $memberId)->update([
            'role_id' => $roleId,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function members(int $tenantId): array
    {
        return $this->db->table('tenant_users')
            ->select('tenant_users.id, tenant_users.user_id, users.name, users.email, roles.name AS role_name, roles.id AS role_id')
            ->join('users', 'users.id = tenant_users.user_id')
            ->join('roles', 'roles.id = tenant_users.role_id')
            ->where('tenant_users.tenant_id', $tenantId)
            ->where('users.deleted_at', null)
            ->orderBy('users.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function invite(int $tenantId, string $email, int $roleId, bool $actorIsOwner): string
    {
        $role = $this->find($tenantId, $roleId);

        if ($role === null) {
            throw new RuntimeException('That role is not in this workspace.');
        }

        if ((int) $role['is_owner'] === 1 && ! $actorIsOwner) {
            throw new RuntimeException('Only an Owner can invite another Owner.');
        }

        $email = strtolower(trim($email));
        $token = bin2hex(random_bytes(24));
        $now   = date('Y-m-d H:i:s');

        $this->db->table('invites')->insert([
            'tenant_id'  => $tenantId,
            'email'      => $email,
            'role_id'    => $roleId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 7),
            'created_at' => $now,
        ]);

        return $token;
    }

    public function temporaryPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $length   = strlen($alphabet) - 1;
        $password = '';

        for ($i = 0; $i < 10; $i++) {
            $password .= $alphabet[random_int(0, $length)];
        }

        return $password;
    }

    public function issuePasswordToken(int $userId): string
    {
        if (! $this->db->tableExists('password_resets')) {
            throw new RuntimeException('Password links are not installed yet.');
        }

        $token = bin2hex(random_bytes(24));
        $this->db->table('password_resets')->insert([
            'user_id'    => $userId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 7),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    public function applyPasswordToken(string $token, string $password): void
    {
        if (strlen($password) < 8) {
            throw new RuntimeException('Use a password of at least 8 characters.');
        }

        $row = $this->db->table('password_resets')
            ->where('token_hash', hash('sha256', $token))
            ->where('used_at', null)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw new RuntimeException('This link is no longer valid.');
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('users')->where('id', (int) $row['user_id'])->update([
            'password_hash' => $this->workspaces->hashPassword($password),
            'updated_at'    => $now,
        ]);
        $this->db->table('password_resets')->where('id', (int) $row['id'])->update([
            'used_at' => $now,
        ]);
    }

    public function changePassword(int $userId, string $current, string $next): void
    {
        if (strlen($next) < 8) {
            throw new RuntimeException('Use a password of at least 8 characters.');
        }

        $user = $this->db->table('users')->where('id', $userId)->where('deleted_at', null)->get()->getRowArray();

        if ($user === null || ! password_verify($current, (string) $user['password_hash'])) {
            throw new RuntimeException('The current password is wrong.');
        }

        $this->db->table('users')->where('id', $userId)->update([
            'password_hash' => $this->workspaces->hashPassword($next),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array{user_id: int, tenant_id: int, role_id: int}
     */
    public function acceptInvite(string $token, string $name, string $password): array
    {
        $invite = $this->db->table('invites')
            ->where('token_hash', hash('sha256', $token))
            ->where('accepted_at', null)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->get()
            ->getRowArray();

        if ($invite === null) {
            throw new RuntimeException('This invite is no longer valid.');
        }

        $now   = date('Y-m-d H:i:s');
        $email = $invite['email'];
        $user  = $this->workspaces->userByEmail($email);

        if ($user !== null) {
            $already = $this->db->table('tenant_users')
                ->where('tenant_id', $invite['tenant_id'])
                ->where('user_id', $user['id'])
                ->countAllResults();

            if ($already > 0) {
                throw new RuntimeException('This person is already in the workspace.');
            }
        }

        $this->db->transStart();

        if ($user === null) {
            $this->db->table('users')->insert([
                'name'          => $name,
                'email'         => $email,
                'password_hash' => $this->workspaces->hashPassword($password),
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $userId = (int) $this->db->insertID();
        } else {
            $userId = (int) $user['id'];
        }

        $this->db->table('tenant_users')->insert([
            'tenant_id'  => $invite['tenant_id'],
            'user_id'    => $userId,
            'role_id'    => $invite['role_id'],
            'created_at' => $now,
        ]);

        $this->db->table('invites')->where('id', $invite['id'])->update([
            'accepted_at' => $now,
        ]);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            throw new RuntimeException('Could not accept the invite.');
        }

        return [
            'user_id'   => $userId,
            'tenant_id' => (int) $invite['tenant_id'],
            'role_id'   => (int) $invite['role_id'],
        ];
    }
}
