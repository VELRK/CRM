<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class EstateService
{
    public function __construct(private BaseConnection $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function options(int $tenantId, string $kind): array
    {
        if (! $this->db->tableExists('lookup_items')) {
            return [];
        }

        return $this->db->table('lookup_items')
            ->where('tenant_id', $tenantId)
            ->where('kind', $kind)
            ->where('deleted_at', null)
            ->where('active', 1)
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allOptions(int $tenantId): array
    {
        if (! $this->db->tableExists('lookup_items')) {
            return [];
        }

        return $this->db->table('lookup_items')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->orderBy('kind', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveOption(int $tenantId, ?int $id, array $input): void
    {
        $kind  = (string) ($input['kind'] ?? '');
        $code  = strtolower(trim((string) ($input['code'] ?? '')));
        $label = trim((string) ($input['label'] ?? ''));

        if (! in_array($kind, ['lead_status', 'plot_status', 'lead_source', 'designation'], true) || $code === '' || $label === '') {
            throw new RuntimeException('A list item needs a type, code and label.');
        }

        if (! preg_match('/^[a-z0-9_-]{2,40}$/', $code)) {
            throw new RuntimeException('Use a short code such as walk-in or site_visit.');
        }

        $now = date('Y-m-d H:i:s');
        $data = [
            'kind'       => $kind,
            'code'       => $code,
            'label'      => $label,
            'sort_order' => max(0, (int) ($input['sort_order'] ?? 0)),
            'active'     => empty($input['active']) ? 0 : 1,
        ];

        if ($id === null) {
            $trashed = $this->db->table('lookup_items')
                ->where('tenant_id', $tenantId)
                ->where('kind', $kind)
                ->where('code', $code)
                ->where('deleted_at IS NOT NULL', null, false)
                ->get()
                ->getRowArray();

            if ($trashed !== null) {
                $data['deleted_at'] = null;
                $this->db->table('lookup_items')->where('id', (int) $trashed['id'])->update($data);

                return;
            }

            $data['tenant_id'] = $tenantId;
            $this->db->table('lookup_items')->insert($data);

            return;
        }

        $row = $this->db->table('lookup_items')->where('tenant_id', $tenantId)->where('id', $id)->where('deleted_at', null)->get()->getRowArray();

        if ($row === null) {
            throw new RuntimeException('That list item is not in this workspace.');
        }

        $this->db->table('lookup_items')->where('id', $id)->update($data);

        if ($kind === 'designation' && (string) $row['code'] !== $code) {
            $this->db->table('staff_profiles')
                ->where('tenant_id', $tenantId)
                ->where('designation', $row['code'])
                ->update(['designation' => $code, 'updated_at' => $now]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function designations(int $tenantId): array
    {
        if (! $this->db->tableExists('lookup_items')) {
            return [];
        }

        return $this->db->table('lookup_items')
            ->where('tenant_id', $tenantId)
            ->where('kind', 'designation')
            ->where('deleted_at', null)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('label', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return array<string, string>
     */
    public function designationLabels(int $tenantId): array
    {
        $labels = [];

        if (! $this->db->tableExists('lookup_items')) {
            return $labels;
        }

        $rows = $this->db->table('lookup_items')
            ->select('code, label')
            ->where('tenant_id', $tenantId)
            ->where('kind', 'designation')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $labels[(string) $row['code']] = (string) $row['label'];
        }

        return $labels;
    }

    public function deleteDesignation(int $tenantId, int $id): void
    {
        $row = $this->db->table('lookup_items')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('kind', 'designation')
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw new RuntimeException('That designation is not in this workspace.');
        }

        $this->db->table('lookup_items')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function catalogItems(int $tenantId, string $kind, ?bool $activeOnly = null): array
    {
        $this->assertCatalogKind($kind);
        $this->seedCatalog($tenantId, $kind);

        if (! $this->db->tableExists('lookup_items')) {
            return [];
        }

        $builder = $this->db->table('lookup_items')
            ->where('tenant_id', $tenantId)
            ->where('kind', $kind)
            ->where('deleted_at', null)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('label', 'ASC');

        if ($activeOnly === true) {
            $builder->where('active', 1);
        }

        return array_map(fn (array $row): array => $this->presentCatalog($row), $builder->get()->getResultArray());
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function saveCatalog(int $tenantId, string $kind, ?int $id, array $input): array
    {
        $this->assertCatalogKind($kind);

        if (! $this->db->tableExists('lookup_items')) {
            throw new RuntimeException('The list is not ready yet.');
        }

        $name = trim((string) ($input['name'] ?? $input['label'] ?? ''));

        if ($name === '' || strlen($name) > 80) {
            throw new RuntimeException('Enter a name of 80 characters or less.');
        }

        $code = $this->catalogCode($name);
        $taken = $this->db->table('lookup_items')
            ->where('tenant_id', $tenantId)
            ->where('kind', $kind)
            ->groupStart()
                ->where('code', $code)
                ->orWhere('label', $name)
            ->groupEnd()
            ->get()
            ->getResultArray();

        foreach ($taken as $row) {
            if ($id !== null && (int) $row['id'] === $id) {
                continue;
            }

            if ($row['deleted_at'] === null) {
                throw new RuntimeException('That name is already in the list.');
            }
        }

        $active = array_key_exists('active', $input) ? (empty($input['active']) ? 0 : 1) : null;
        $sort = array_key_exists('sort_order', $input) ? max(0, (int) $input['sort_order']) : null;

        if ($id === null) {
            $trashed = null;

            foreach ($taken as $row) {
                if ($row['deleted_at'] !== null && (string) $row['code'] === $code) {
                    $trashed = $row;
                    break;
                }
            }

            if ($trashed !== null) {
                $id = (int) $trashed['id'];
            }
        }

        if ($id === null) {
            $max = $this->db->table('lookup_items')
                ->selectMax('sort_order', 'top')
                ->where('tenant_id', $tenantId)
                ->where('kind', $kind)
                ->get()
                ->getRowArray();
            $this->db->table('lookup_items')->insert([
                'tenant_id'  => $tenantId,
                'kind'       => $kind,
                'code'       => $code,
                'label'      => $name,
                'sort_order' => $sort ?? ((int) ($max['top'] ?? 0) + 1),
                'active'     => $active ?? 1,
                'deleted_at' => null,
            ]);

            return $this->presentCatalog($this->catalogRow($tenantId, $kind, (int) $this->db->insertID()));
        }

        $current = $this->db->table('lookup_items')
            ->where('tenant_id', $tenantId)
            ->where('kind', $kind)
            ->where('id', $id)
            ->get()
            ->getRowArray();

        if ($current === null) {
            throw new RuntimeException('That item is not in this workspace.');
        }

        $this->db->table('lookup_items')->where('id', $id)->update([
            'code'       => $code,
            'label'      => $name,
            'sort_order' => $sort ?? (int) $current['sort_order'],
            'active'     => $active ?? (int) $current['active'],
            'deleted_at' => null,
        ]);

        return $this->presentCatalog($this->catalogRow($tenantId, $kind, $id));
    }

    public function deleteCatalog(int $tenantId, string $kind, int $id): void
    {
        $this->assertCatalogKind($kind);
        $row = $this->db->table('lookup_items')
            ->where('tenant_id', $tenantId)
            ->where('kind', $kind)
            ->where('id', $id)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw new RuntimeException('That item is not in this workspace.');
        }

        $this->db->table('lookup_items')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Counts for the mobile home. Scoped the same way as the desk dashboard.
     *
     * @param array{level?: string, user_ids?: ?list<int>, team_ids?: ?list<int>}|null $scope
     * @return array<string, mixed>
     */
    public function mobileCounts(int $tenantId, ?array $scope = null): array
    {
        $userIds = $scope['user_ids'] ?? null;
        $board = $this->dashboard($tenantId, $scope);
        $newLeads = 0;

        foreach ($board['funnel'] ?? [] as $row) {
            if ((string) ($row['label'] ?? '') === 'New') {
                $newLeads = (int) ($row['total'] ?? 0);
            }
        }

        $tasks = 0;

        if ($this->db->tableExists('lead_tasks')) {
            $tasks = $this->applyUsers(
                $this->onlyLiveLeads($this->db->table('lead_tasks')
                    ->where('tenant_id', $tenantId)
                    ->where('deleted_at', null)
                    ->where('completed_date', null)
                    ->whereNotIn('current_state', ['COMPLETED', 'CANCELLED', 'CANCELED']), $tenantId),
                'assigned_to',
                $userIds,
            )->countAllResults();
        }

        $campaigns = [];

        if ($this->db->fieldExists('campaign_source', 'leads')) {
            $campaigns = $this->namedCounts($this->grouped($tenantId, 'leads', 'campaign_source', 'owner_id', $userIds), 'campaign_source');
        }

        $stages = [];

        foreach ($board['funnel'] ?? [] as $row) {
            $stages[] = ['name' => (string) ($row['label'] ?? ''), 'total' => (int) ($row['total'] ?? 0)];
        }

        $sources = [];

        foreach ($board['sources'] ?? [] as $row) {
            $sources[] = ['name' => (string) ($row['label'] ?? $row['source'] ?? ''), 'total' => (int) ($row['total'] ?? 0)];
        }

        return [
            'leads'               => (int) ($board['leads'] ?? 0),
            'new_leads'           => $newLeads,
            'followups'           => (int) ($board['followups'] ?? 0),
            'followups_overdue'   => (int) ($board['follow_split']['overdue'] ?? 0),
            'followups_upcoming'  => (int) ($board['follow_split']['upcoming'] ?? 0),
            'followups_done'      => (int) ($board['follow_split']['done'] ?? 0),
            'visits'              => (int) ($board['visits'] ?? 0),
            'tasks'               => $tasks,
            'plots_available'     => (int) ($board['available'] ?? 0),
            'stages'              => $stages,
            'sources'             => $sources,
            'campaigns'           => $campaigns,
        ];
    }

    public function assertCatalogValue(int $tenantId, string $kind, string $label): void
    {
        $label = trim($label);
        $this->seedCatalog($tenantId, $kind);

        foreach ($this->catalogItems($tenantId, $kind, true) as $row) {
            if ((string) ($row['name'] ?? '') === $label) {
                return;
            }
        }

        throw new RuntimeException('Choose a value from the master list.');
    }

    public function ensureWorkflowAccess(): void
    {
        static $done = false;

        if ($done || ! $this->db->tableExists('roles') || ! $this->db->tableExists('tenants')) {
            return;
        }

        $done = true;
        $tenants = $this->db->table('tenants')->select('id')->get()->getResultArray();

        foreach ($tenants as $tenant) {
            $tenantId = (int) $tenant['id'];
            $this->grantRoleKeys($tenantId, ['Admin'], ['masters.view', 'masters.manage', 'leads.approve', 'deals.approve']);
            $this->grantRoleKeys($tenantId, ['Manager', 'Team Head'], ['leads.approve', 'deals.approve', 'deals.view', 'masters.view']);
            $this->ensureNamedRole($tenantId, 'Collaborator', AccessCatalog::forStarter('Collaborator'));
        }
    }

    /**
     * @param list<string> $names
     * @param list<string> $keys
     */
    private function grantRoleKeys(int $tenantId, array $names, array $keys): void
    {
        $roles = $this->db->table('roles')
            ->select('id')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->whereIn('name', $names)
            ->get()
            ->getResultArray();

        foreach ($roles as $role) {
            $this->grantKeys((int) $role['id'], $keys);
        }
    }

    /**
     * @param list<string> $keys
     */
    private function ensureNamedRole(int $tenantId, string $name, array $keys): void
    {
        $role = $this->db->table('roles')
            ->select('id')
            ->where('tenant_id', $tenantId)
            ->where('name', $name)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($role === null) {
            $row = [
                'tenant_id'  => $tenantId,
                'name'       => $name,
                'is_system'  => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($this->db->fieldExists('is_owner', 'roles')) {
                $row['is_owner'] = 0;
            }

            $this->db->table('roles')->insert($row);
            $roleId = (int) $this->db->insertID();
        } else {
            $roleId = (int) $role['id'];
        }

        $this->grantKeys($roleId, $keys);
    }

    /**
     * @param list<string> $keys
     */
    private function grantKeys(int $roleId, array $keys): void
    {
        if ($roleId < 1 || ! $this->db->tableExists('role_permissions')) {
            return;
        }

        foreach ($keys as $key) {
            $exists = $this->db->table('role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_key', $key)
                ->countAllResults();

            if ($exists > 0) {
                continue;
            }

            $this->db->table('role_permissions')->insert([
                'role_id'        => $roleId,
                'permission_key' => $key,
            ]);
        }
    }

    private function seedCatalog(int $tenantId, string $kind): void
    {
        if (! $this->db->tableExists('lookup_items')) {
            return;
        }

        $live = $this->db->table('lookup_items')
            ->where('tenant_id', $tenantId)
            ->where('kind', $kind)
            ->where('deleted_at', null)
            ->countAllResults();

        if ($live > 0) {
            return;
        }

        $order = 1;

        foreach ($this->catalogDefaults($kind) as $label) {
            $code = $this->catalogCode($label);
            $existing = $this->db->table('lookup_items')
                ->where('tenant_id', $tenantId)
                ->where('kind', $kind)
                ->where('code', $code)
                ->get()
                ->getRowArray();

            if ($existing !== null) {
                $this->db->table('lookup_items')->where('id', (int) $existing['id'])->update([
                    'label'      => $label,
                    'sort_order' => $order,
                    'active'     => 1,
                    'deleted_at' => null,
                ]);
            } else {
                $this->db->table('lookup_items')->insert([
                    'tenant_id'  => $tenantId,
                    'kind'       => $kind,
                    'code'       => $code,
                    'label'      => $label,
                    'sort_order' => $order,
                    'active'     => 1,
                    'deleted_at' => null,
                ]);
            }

            $order++;
        }
    }

    /**
     * @return list<string>
     */
    private function catalogDefaults(string $kind): array
    {
        return match ($kind) {
            'lead_stage' => ['New', 'Qualified', 'Follow Up', 'Proposal Sent', 'Site Visit Scheduled', 'Negotiation', 'Booked', 'Lost'],
            'lead_source' => ['Website', 'Facebook', 'Instagram', 'Walk-in', 'Referral', 'WhatsApp'],
            'campaign_source' => ['Website', 'Facebook', 'Instagram', 'Email', 'Portal', 'Event'],
            'followup_purpose' => ['Introduction', 'Follow up', 'Site visit reminder', 'Negotiation', 'Closure'],
            'call_feedback' => ['Interested', 'Call back later', 'Not reachable', 'Not interested', 'Site visit planned'],
            'visit_feedback' => ['Interested', 'Needs time', 'Not interested', 'Ready to book', 'Reschedule'],
            default => [],
        };
    }

    private function assertCatalogKind(string $kind): void
    {
        if (! in_array($kind, ['lead_stage', 'lead_source', 'campaign_source', 'followup_purpose', 'call_feedback', 'visit_feedback'], true)) {
            throw new RuntimeException('That list is not available.');
        }
    }

    private function catalogCode(string $name): string
    {
        $code = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));

        if (strlen($code) < 2) {
            $code = 'item';
        }

        return substr($code, 0, 40);
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogRow(int $tenantId, string $kind, int $id): array
    {
        $row = $this->db->table('lookup_items')
            ->where('tenant_id', $tenantId)
            ->where('kind', $kind)
            ->where('id', $id)
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw new RuntimeException('That item is not in this workspace.');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function presentCatalog(array $row): array
    {
        return [
            'id'         => (int) $row['id'],
            'name'       => (string) $row['label'],
            'active'     => (int) $row['active'] === 1,
            'sort_order' => (int) $row['sort_order'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function namedCounts(array $rows, string $column): array
    {
        $out = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row[$column] ?? ''));

            if ($name === '') {
                continue;
            }

            $out[] = ['name' => $name, 'total' => (int) ($row['total'] ?? 0)];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function teams(int $tenantId): array
    {
        if (! $this->db->tableExists('teams')) {
            return [];
        }

        $this->ensureTeamProjects();
        $rows = $this->db->table('teams t')
            ->select('t.*, u.name AS head_name')
            ->join('users u', 'u.id = t.head_user_id', 'left')
            ->where('t.tenant_id', $tenantId)
            ->where('t.deleted_at', null)
            ->orderBy('t.name', 'ASC')
            ->get()
            ->getResultArray();
        $links = $this->db->table('team_projects tp')
            ->select('tp.team_id, tp.project_id, p.name')
            ->join('projects p', 'p.id = tp.project_id')
            ->where('tp.tenant_id', $tenantId)
            ->where('p.deleted_at', null)
            ->orderBy('p.name', 'ASC')
            ->get()
            ->getResultArray();
        $byTeam = [];

        foreach ($links as $link) {
            $byTeam[(int) $link['team_id']][] = $link;
        }

        foreach ($rows as &$team) {
            $mine = $byTeam[(int) $team['id']] ?? [];
            $team['project_ids'] = array_map(static fn (array $link): int => (int) $link['project_id'], $mine);
            $team['projects'] = implode(', ', array_map(static fn (array $link): string => (string) $link['name'], $mine));
        }
        unset($team);

        return $rows;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveTeam(int $tenantId, ?int $id, array $input): int
    {
        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('A team needs a name.');
        }

        $head = (int) ($input['head_user_id'] ?? 0);
        $now  = date('Y-m-d H:i:s');
        $data = [
            'name'         => $name,
            'head_user_id' => $head > 0 ? $head : null,
            'updated_at'   => $now,
        ];

        if ($id === null) {
            $data['tenant_id']  = $tenantId;
            $data['created_at'] = $now;
            $this->db->table('teams')->insert($data);
            $id = (int) $this->db->insertID();
        } else {
            $this->db->table('teams')->where('tenant_id', $tenantId)->where('id', $id)->update($data);
        }

        $memberIds = $input['member_ids'] ?? [];
        $memberIds = is_array($memberIds) ? array_map('intval', $memberIds) : [];

        if ($head > 0) {
            $memberIds[] = $head;
        }

        $this->syncMembers($tenantId, $id, array_values(array_unique(array_filter($memberIds))));

        if (array_key_exists('project_ids', $input)) {
            $projectIds = $input['project_ids'];
            $projectIds = is_array($projectIds) ? array_map('intval', $projectIds) : [];
            $this->syncTeamProjects($tenantId, $id, array_values(array_unique(array_filter($projectIds))));
        }

        return $id;
    }

    public function deleteTeam(int $tenantId, int $id): void
    {
        $team = $this->db->table('teams')->where('tenant_id', $tenantId)->where('id', $id)->where('deleted_at', null)->get()->getRowArray();

        if ($team === null) {
            throw new RuntimeException('That team is not in this workspace.');
        }

        $this->db->table('teams')->where('id', $id)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->ensureTeamProjects();
        $this->db->table('team_projects')->where('tenant_id', $tenantId)->where('team_id', $id)->delete();
    }

    /**
     * @param list<int> $userIds
     */
    private function syncMembers(int $tenantId, int $teamId, array $userIds): void
    {
        $today = date('Y-m-d');
        $open  = $this->db->table('team_memberships')
            ->where('tenant_id', $tenantId)
            ->where('team_id', $teamId)
            ->where('to_on', null)
            ->get()
            ->getResultArray();
        $openIds = array_map(static fn (array $row): int => (int) $row['user_id'], $open);

        foreach ($open as $row) {
            if (! in_array((int) $row['user_id'], $userIds, true)) {
                $this->db->table('team_memberships')->where('id', (int) $row['id'])->update(['to_on' => $today]);
                $this->db->table('staff_profiles')->where('tenant_id', $tenantId)->where('user_id', (int) $row['user_id'])->where('team_id', $teamId)->update(['team_id' => null]);
            }
        }

        foreach ($userIds as $userId) {
            if (in_array($userId, $openIds, true)) {
                continue;
            }

            $this->db->table('team_memberships')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('to_on', null)
                ->update(['to_on' => $today]);
            $this->db->table('team_memberships')->insert([
                'tenant_id'  => $tenantId,
                'team_id'    => $teamId,
                'user_id'    => $userId,
                'from_on'    => $today,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->table('staff_profiles')->where('tenant_id', $tenantId)->where('user_id', $userId)->update(['team_id' => $teamId]);
        }
    }

    /**
     * @return list<int>
     */
    public function memberIds(int $tenantId, int $teamId): array
    {
        $rows = $this->db->table('team_memberships')
            ->select('user_id')
            ->where('tenant_id', $tenantId)
            ->where('team_id', $teamId)
            ->where('to_on', null)
            ->get()
            ->getResultArray();

        return array_map('intval', array_column($rows, 'user_id'));
    }

    /**
     * @param list<int> $teamIds
     * @return list<int>
     */
    public function projectIdsForTeams(int $tenantId, array $teamIds): array
    {
        $this->ensureTeamProjects();

        if ($teamIds === []) {
            return [];
        }

        $rows = $this->db->table('team_projects')
            ->select('project_id')
            ->where('tenant_id', $tenantId)
            ->whereIn('team_id', $teamIds)
            ->get()
            ->getResultArray();

        return array_values(array_unique(array_map(static fn (array $row): int => (int) $row['project_id'], $rows)));
    }

    /**
     * @param list<int> $projectIds
     */
    private function syncTeamProjects(int $tenantId, int $teamId, array $projectIds): void
    {
        $this->ensureTeamProjects();
        $valid = [];

        if ($projectIds !== []) {
            $rows = $this->db->table('projects')
                ->select('id')
                ->where('tenant_id', $tenantId)
                ->where('deleted_at', null)
                ->whereIn('id', $projectIds)
                ->get()
                ->getResultArray();
            $valid = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        }

        $this->db->table('team_projects')->where('tenant_id', $tenantId)->where('team_id', $teamId)->delete();

        foreach ($valid as $projectId) {
            $this->db->table('team_projects')->insert([
                'tenant_id'  => $tenantId,
                'team_id'    => $teamId,
                'project_id' => $projectId,
            ]);
        }
    }

    private bool $teamProjectsReady = false;

    private function ensureTeamProjects(): void
    {
        if ($this->teamProjectsReady) {
            return;
        }

        if (! $this->db->tableExists('team_projects')) {
            $forge = \Config\Database::forge();
            $forge->addField([
                'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
                'team_id'    => ['type' => 'INT', 'unsigned' => true],
                'project_id' => ['type' => 'INT', 'unsigned' => true],
            ]);
            $forge->addKey(['tenant_id', 'team_id', 'project_id'], true);
            $forge->createTable('team_projects');
            $this->db->resetDataCache();
        }

        $this->teamProjectsReady = true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attendance(int $tenantId, string $month): array
    {
        if (! $this->db->tableExists('attendance')) {
            return [];
        }

        $rows = $this->db->table('attendance a')
            ->select('a.*, u.name AS user_name')
            ->join('users u', 'u.id = a.user_id', 'left')
            ->where('a.tenant_id', $tenantId)
            ->like('a.work_date', $month, 'after')
            ->orderBy('a.work_date', 'DESC')
            ->get()
            ->getResultArray();

        return (new AttendanceLocationService($this->db))->attachSessionCounts($tenantId, $month, $rows);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function markAttendance(int $tenantId, int $userId, array $input, bool $correcting, int $actorId): void
    {
        $date = trim((string) ($input['work_date'] ?? date('Y-m-d')));
        $status = (string) ($input['status'] ?? 'present');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! in_array($status, ['present', 'leave', 'absent', 'late'], true)) {
            throw new RuntimeException('Choose a date and an attendance status.');
        }

        if ((new AttendanceLocationService($this->db))->handleMark($tenantId, $userId, $input, $correcting, $actorId, $date, $status)) {
            return;
        }

        if (! $correcting && in_array($status, ['present', 'late'], true)) {
            $this->requireAttendanceArea($tenantId, $input['lat'] ?? null, $input['lng'] ?? null);
        }

        $now = date('Y-m-d H:i:s');
        $existing = $this->db->table('attendance')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('work_date', $date)
            ->get()
            ->getRowArray();
        $data = [
            'status'     => $status,
            'note'       => trim((string) ($input['note'] ?? '')) ?: null,
            'updated_at' => $now,
        ];

        if (($input['action'] ?? '') === 'out') {
            $data['check_out'] = $now;
            $data['check_out_lat'] = $this->coord($input['lat'] ?? null);
            $data['check_out_lng'] = $this->coord($input['lng'] ?? null);
        } elseif ($status === 'present' || $status === 'late') {
            $data['check_in'] = $existing['check_in'] ?? $now;
            $data['check_in_lat'] = $existing['check_in_lat'] ?? $this->coord($input['lat'] ?? null);
            $data['check_in_lng'] = $existing['check_in_lng'] ?? $this->coord($input['lng'] ?? null);
        }

        if ($correcting) {
            $data['corrected_by'] = $actorId;
        }

        if ($existing === null) {
            $data['tenant_id']  = $tenantId;
            $data['user_id']    = $userId;
            $data['work_date']  = $date;
            $data['created_at'] = $now;
            $this->db->table('attendance')->insert($data);

            return;
        }

        $this->db->table('attendance')->where('id', (int) $existing['id'])->update($data);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function recordSale(int $tenantId, int $actorId, int $plotId, string $status, array $input): void
    {
        if (! in_array($status, SiteService::SALE_STATUSES, true)) {
            return;
        }

        $plot = $this->db->table('plots')->where('tenant_id', $tenantId)->where('id', $plotId)->where('deleted_at', null)->get()->getRowArray();
        $leadId = (int) ($input['lead_id'] ?? $input['leadId'] ?? 0);
        $staffId = (int) ($input['credited_user_id'] ?? $input['associateUserId'] ?? $input['associate_user_id'] ?? 0);

        if ($plot === null) {
            throw new RuntimeException('That plot is not in this workspace.');
        }

        if ($leadId < 1) {
            $phone = preg_replace('/\D+/', '', (string) ($input['customerPhone'] ?? $input['customer_phone'] ?? $plot['customer_phone'] ?? '')) ?? '';
            if ($phone !== '') {
                $matched = $this->db->table('leads')->select('id')->where('tenant_id', $tenantId)->where('deleted_at', null)->groupStart()->where('phone_number', $phone)->orWhere('phone', $phone)->groupEnd()->get()->getRowArray();
                $leadId = (int) ($matched['id'] ?? 0);
            }
        }

        $lead = $leadId > 0 ? $this->db->table('leads')->where('tenant_id', $tenantId)->where('id', $leadId)->where('deleted_at', null)->get()->getRowArray() : null;
        $already = $this->db->table('sales')->where('tenant_id', $tenantId)->where('plot_id', $plotId)->where('deleted_at', null)->where('status', 'active')->countAllResults();

        if ($already > 0) {
            return;
        }

        $ownerId = (int) ($lead['owner_id'] ?? 0);

        if ($ownerId > 0) {
            $staffId = $ownerId;
        }

        if ($lead === null || $staffId < 1) {
            throw new RuntimeException('A booked, sold, or registered plot needs the associate and the lead. The incentive is added at that moment.');
        }

        $amount = (int) ($plot['commission_minor'] ?? 0);

        if ($amount < 1) {
            throw new RuntimeException('Enter the commission for this plot.');
        }

        $value = trim((string) ($input['sale_value'] ?? ''));
        $minor = $value === '' ? (int) $plot['price_minor'] : BookService::rupeesToMinor($value);
        $now = date('Y-m-d H:i:s');
        $profile = $this->db->table('staff_profiles')->select('team_id, incentive_eligible')->where('tenant_id', $tenantId)->where('user_id', $staffId)->get()->getRowArray();
        $teamId = (int) ($profile['team_id'] ?? 0);
        $headId = (int) ($input['head_user_id'] ?? 0);
        $splitId = (int) ($input['split_user_id'] ?? 0);
        $splitMinor = BookService::rupeesToMinor((string) ($input['split_amount'] ?? ''));

        $this->db->table('sales')->insert([
            'tenant_id'        => $tenantId,
            'plot_id'          => $plotId,
            'project_id'       => (int) $plot['project_id'],
            'lead_id'          => $leadId,
            'credited_user_id' => $staffId,
            'head_user_id'     => $headId > 0 ? $headId : null,
            'team_id'          => $teamId > 0 ? $teamId : null,
            'sale_value_minor' => $minor,
            'sale_on'          => date('Y-m-d'),
            'source'           => $lead['lead_source'] ?? $lead['source'] ?? null,
            'plot_status'      => $status,
            'split_user_id'    => $splitId > 0 ? $splitId : null,
            'split_minor'      => $splitMinor,
            'created_by'       => $actorId,
            'created_at'       => $now,
        ]);
        $saleId = (int) $this->db->insertID();
        $eligible = (int) ($profile['incentive_eligible'] ?? 1);
        $this->db->table('incentives')->insert([
            'tenant_id'    => $tenantId,
            'sale_id'      => $saleId,
            'user_id'      => $staffId,
            'status'       => $eligible === 0 ? 'not_eligible' : 'pending',
            'amount_minor' => $amount,
            'rule_note'    => 'Commission entered on the plot',
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        if ($splitId > 0 && $splitMinor > 0) {
            $this->db->table('incentives')->insert([
                'tenant_id'    => $tenantId,
                'sale_id'      => $saleId,
                'user_id'      => $splitId,
                'status'       => 'pending',
                'amount_minor' => $splitMinor,
                'rule_note'    => 'Split credit',
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function incentives(int $tenantId): array
    {
        if (! $this->db->tableExists('incentives')) {
            return [];
        }

        return $this->db->table('incentives i')
            ->select('i.*, u.name AS user_name, s.plot_status, s.sale_value_minor, p.plot_no, pr.name AS project_name')
            ->join('sales s', 's.id = i.sale_id')
            ->join('users u', 'u.id = i.user_id', 'left')
            ->join('plots p', 'p.id = s.plot_id', 'left')
            ->join('projects pr', 'pr.id = s.project_id', 'left')
            ->where('i.tenant_id', $tenantId)
            ->where('s.deleted_at', null)
            ->orderBy('i.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function setIncentive(int $tenantId, int $id, string $status, int $actorId): void
    {
        if (! in_array($status, ['approved', 'paid', 'on_hold', 'cancelled'], true)) {
            throw new RuntimeException('Choose an incentive status.');
        }

        $row = $this->db->table('incentives')->where('tenant_id', $tenantId)->where('id', $id)->get()->getRowArray();

        if ($row === null) {
            throw new RuntimeException('That incentive is not in this workspace.');
        }

        $now = date('Y-m-d H:i:s');
        $data = ['status' => $status, 'updated_at' => $now, 'approver_id' => $actorId];

        if ($status === 'approved') {
            $data['approved_at'] = $now;
        }

        if ($status === 'paid') {
            $data['paid_at'] = $now;
            $data['approved_at'] = $row['approved_at'] ?? $now;
        }

        $this->db->table('incentives')->where('id', $id)->update($data);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function templates(int $tenantId): array
    {
        if (! $this->db->tableExists('whatsapp_templates')) {
            return [];
        }

        return $this->db->table('whatsapp_templates')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveTemplate(int $tenantId, ?int $id, array $input): void
    {
        $name = trim((string) ($input['name'] ?? ''));
        $body = trim((string) ($input['body'] ?? ''));

        if ($name === '' || $body === '') {
            throw new RuntimeException('A template needs a name and a message.');
        }

        $now = date('Y-m-d H:i:s');
        $data = [
            'name'       => $name,
            'language'   => trim((string) ($input['language'] ?? 'en')) ?: 'en',
            'body'       => $body,
            'active'     => empty($input['active']) ? 0 : 1,
            'updated_at' => $now,
        ];

        if ($id === null) {
            $data['tenant_id']  = $tenantId;
            $data['created_at'] = $now;
            $this->db->table('whatsapp_templates')->insert($data);

            return;
        }

        $this->db->table('whatsapp_templates')->where('tenant_id', $tenantId)->where('id', $id)->update($data);
    }

    public function queueMessage(int $tenantId, int $userId, int $leadId, int $templateId): void
    {
        $lead = $this->db->table('leads')->where('tenant_id', $tenantId)->where('id', $leadId)->where('deleted_at', null)->get()->getRowArray();
        $template = $this->db->table('whatsapp_templates')->where('tenant_id', $tenantId)->where('id', $templateId)->where('deleted_at', null)->get()->getRowArray();

        if ($lead === null || $template === null) {
            throw new RuntimeException('Choose a lead and a template.');
        }

        $phone = (string) ($lead['phone_number'] ?? $lead['phone'] ?? '');
        $body = strtr((string) $template['body'], [
            '{{name}}'  => (string) $lead['name'],
            '{{phone}}' => $phone,
        ]);
        $this->db->table('whatsapp_messages')->insert([
            'tenant_id'   => $tenantId,
            'lead_id'     => $leadId,
            'user_id'     => $userId,
            'direction'   => 'out',
            'template_id' => $templateId,
            'to_phone'    => $phone,
            'subject'     => (string) $template['name'],
            'body'        => $body,
            'status'      => 'failed',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messages(int $tenantId): array
    {
        if (! $this->db->tableExists('whatsapp_messages')) {
            return [];
        }

        $rows = $this->db->table('whatsapp_messages m')
            ->select('m.id, m.lead_id, m.to_phone, m.subject, m.body, m.status, m.created_at, l.name AS lead_name, IFNULL(l.phone_number, l.phone) AS lead_phone, t.name AS template_name, u.name AS user_name', false)
            ->join('leads l', 'l.id = m.lead_id', 'left')
            ->join('whatsapp_templates t', 't.id = m.template_id', 'left')
            ->join('users u', 'u.id = m.user_id', 'left')
            ->where('m.tenant_id', $tenantId)
            ->orderBy('m.id', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            if (trim((string) ($row['to_phone'] ?? '')) === '') {
                $row['to_phone'] = (string) ($row['lead_phone'] ?? '');
            }

            if (trim((string) ($row['subject'] ?? '')) === '') {
                $row['subject'] = (string) ($row['template_name'] ?? 'Message');
            }

            $row['status_label'] = match ((string) $row['status']) {
                'delivered' => 'Delivered',
                'read'      => 'Read',
                'sent'      => 'Sent',
                'queued'    => 'Queued',
                default     => 'Not delivered',
            };
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array{lat: string, lng: string, km: string}
     */
    public function attendanceArea(int $tenantId): array
    {
        return [
            'lat' => (string) ($this->setting($tenantId, 'attendance_lat') ?? ''),
            'lng' => (string) ($this->setting($tenantId, 'attendance_lng') ?? ''),
            'km'  => (string) ($this->setting($tenantId, 'attendance_km') ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveAttendanceArea(int $tenantId, array $input): void
    {
        $kmRaw = trim((string) ($input['attendanceKm'] ?? $input['attendance_km'] ?? ''));
        $latRaw = trim((string) ($input['attendanceLat'] ?? $input['attendance_lat'] ?? ''));
        $lngRaw = trim((string) ($input['attendanceLng'] ?? $input['attendance_lng'] ?? ''));

        if ($kmRaw !== '' && ! is_numeric($kmRaw)) {
            throw new RuntimeException('Enter the attendance area in kilometres, up to 100.');
        }

        if ($kmRaw === '' || (float) $kmRaw <= 0) {
            $this->saveSetting($tenantId, 'attendance_km', '0');
            $this->saveSetting($tenantId, 'attendance_lat', '');
            $this->saveSetting($tenantId, 'attendance_lng', '');

            return;
        }

        if ((float) $kmRaw > 100) {
            throw new RuntimeException('Enter the attendance area in kilometres, up to 100.');
        }

        if (! is_numeric($latRaw) || (float) $latRaw < -90 || (float) $latRaw > 90 || ! is_numeric($lngRaw) || (float) $lngRaw < -180 || (float) $lngRaw > 180) {
            throw new RuntimeException('Enter the company latitude and longitude for the attendance area.');
        }

        $this->saveSetting($tenantId, 'attendance_km', number_format((float) $kmRaw, 2, '.', ''));
        $this->saveSetting($tenantId, 'attendance_lat', number_format((float) $latRaw, 7, '.', ''));
        $this->saveSetting($tenantId, 'attendance_lng', number_format((float) $lngRaw, 7, '.', ''));
    }

    private function requireAttendanceArea(int $tenantId, mixed $lat, mixed $lng): void
    {
        $area = $this->attendanceArea($tenantId);
        $km = (float) $area['km'];

        if ($km <= 0 || ! is_numeric($area['lat']) || ! is_numeric($area['lng'])) {
            return;
        }

        $hereLat = $this->coord($lat);
        $hereLng = $this->coord($lng);

        if ($hereLat === null || $hereLng === null) {
            throw new RuntimeException('Allow location access. Attendance is accepted only inside the company area.');
        }

        if (! $this->withinKilometres((float) $area['lat'], (float) $area['lng'], (float) $hereLat, (float) $hereLng, $km)) {
            throw new RuntimeException('You are outside the attendance area. Check-in and check-out are allowed only within ' . rtrim(rtrim(number_format($km, 2, '.', ''), '0'), '.') . ' km.');
        }
    }

    private function withinKilometres(float $lat1, float $lng1, float $lat2, float $lng2, float $km): bool
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $distance = $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));

        return $distance <= $km;
    }

    public function setting(int $tenantId, string $key): ?string
    {
        $row = $this->db->table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', $key)
            ->get()
            ->getRowArray();

        return $row['setting_value'] ?? null;
    }

    public function saveSetting(int $tenantId, string $key, string $value): void
    {
        $existing = $this->setting($tenantId, $key);

        if ($existing === null && ! $this->db->table('tenant_settings')->where('tenant_id', $tenantId)->where('setting_key', $key)->countAllResults()) {
            $this->db->table('tenant_settings')->insert([
                'tenant_id'     => $tenantId,
                'setting_key'   => $key,
                'setting_value' => $value,
            ]);

            return;
        }

        $this->db->table('tenant_settings')->where('tenant_id', $tenantId)->where('setting_key', $key)->update([
            'setting_value' => $value,
        ]);
    }

    /**
     * Company for Owner, Admin and Viewer. A manager or team lead sees only the teams they head or belong to.
     *
     * @return array{level: string, label: string, user_ids: ?list<int>, team_ids: ?list<int>}
     */
    public function reportScope(int $tenantId, int $userId, string $roleName, bool $isOwner): array
    {
        if ($isOwner || in_array($roleName, ['Owner', 'Admin', 'Viewer'], true)) {
            return ['level' => 'company', 'label' => 'Company', 'user_ids' => null, 'team_ids' => null];
        }

        if ($roleName === 'Collaborator') {
            return ['level' => 'collaborator', 'label' => 'Assigned leads', 'user_ids' => [$userId], 'team_ids' => []];
        }

        if (in_array($roleName, ['Manager', 'Team Head', 'Team Lead'], true) && $this->db->tableExists('teams')) {
            $teams = $this->db->table('teams')
                ->select('id, name')
                ->where('tenant_id', $tenantId)
                ->where('head_user_id', $userId)
                ->where('deleted_at', null)
                ->get()
                ->getResultArray();
            $teamIds = array_map(static fn (array $row): int => (int) $row['id'], $teams);
            $profile = $this->db->table('staff_profiles')
                ->select('team_id')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('deleted_at', null)
                ->get()
                ->getRowArray();
            $ownTeam = (int) ($profile['team_id'] ?? 0);

            if ($ownTeam > 0 && ! in_array($ownTeam, $teamIds, true)) {
                $named = $this->db->table('teams')
                    ->select('id, name')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $ownTeam)
                    ->where('deleted_at', null)
                    ->get()
                    ->getRowArray();

                if ($named !== null) {
                    $teams[] = $named;
                    $teamIds[] = $ownTeam;
                }
            }

            if ($teamIds !== []) {
                $members = [$userId];
                $profiles = $this->db->table('staff_profiles')
                    ->select('user_id')
                    ->where('tenant_id', $tenantId)
                    ->where('deleted_at', null)
                    ->whereIn('team_id', $teamIds)
                    ->get()
                    ->getResultArray();

                foreach ($profiles as $person) {
                    $members[] = (int) $person['user_id'];
                }

                $label = count($teams) === 1 ? (string) $teams[0]['name'] : 'Your teams';

                return [
                    'level'    => 'team',
                    'label'    => $label,
                    'user_ids' => array_values(array_unique($members)),
                    'team_ids' => $teamIds,
                ];
            }

            return ['level' => 'team', 'label' => 'Your team', 'user_ids' => [$userId], 'team_ids' => []];
        }

        return ['level' => 'own', 'label' => 'Your work', 'user_ids' => [$userId], 'team_ids' => []];
    }

    /**
     * @param array{level?: string, label?: string, user_ids?: ?list<int>, team_ids?: ?list<int>}|null $scope
     * @return array<string, mixed>
     */
    public function dashboard(int $tenantId, ?array $scope = null): array
    {
        $userIds = $scope['user_ids'] ?? null;
        $now = date('Y-m-d H:i:s');
        $openFollowups = $this->countWhere('followups', $tenantId, $userIds, 'IFNULL(assigned_user_id, user_id)', 'done_at IS NULL');
        $plots = $this->labelRows($tenantId, 'plot_status', $this->grouped($tenantId, 'plots', 'status'), 'status');
        $sources = $this->labelRows($tenantId, 'lead_source', $this->grouped($tenantId, 'leads', 'source', 'owner_id', $userIds), 'source');
        $leadStatus = $this->labelRows($tenantId, 'lead_status', $this->grouped($tenantId, 'leads', 'status', 'owner_id', $userIds), 'status');
        $visitStatus = $this->grouped($tenantId, 'site_visits', 'status', 'staff_id', $userIds);
        $followSplit = $this->applyUsers($this->onlyLiveLeads($this->db->table('followups')
            ->select(
                "SUM(done_at IS NOT NULL) AS done, SUM(done_at IS NULL AND next_at IS NOT NULL AND next_at < " . $this->db->escape($now) . ") AS overdue, SUM(done_at IS NULL AND (next_at IS NULL OR next_at >= " . $this->db->escape($now) . ")) AS upcoming",
                false,
            )
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null), $tenantId), 'IFNULL(assigned_user_id, user_id)', $userIds)
            ->get()
            ->getRowArray();
        $start = date('Y-m-d', strtotime('-13 days'));
        $intakeRows = $this->applyUsers($this->db->table('leads')
            ->select('DATE(created_at) AS day, COUNT(*) AS total', false)
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->where('created_at >=', $start . ' 00:00:00'), 'owner_id', $userIds)
            ->groupBy('DATE(created_at)', false)
            ->get()
            ->getResultArray();
        $intakeMap = [];

        foreach ($intakeRows as $row) {
            $intakeMap[(string) $row['day']] = (int) $row['total'];
        }

        $intake = [];

        for ($i = 0; $i < 14; $i++) {
            $day = date('Y-m-d', strtotime($start . ' +' . $i . ' days'));
            $intake[] = ['day' => $day, 'total' => $intakeMap[$day] ?? 0];
        }

        $ownersBuilder = $this->db->table('leads l')
            ->select("l.owner_id, COALESCE(u.name, 'Unassigned') AS name, COUNT(*) AS total", false)
            ->join('users u', 'u.id = l.owner_id', 'left')
            ->where('l.tenant_id', $tenantId)
            ->where('l.deleted_at', null);
        $owners = $this->applyUsers($ownersBuilder, 'l.owner_id', $userIds)
            ->groupBy('l.owner_id')
            ->groupBy('u.name')
            ->orderBy('total', 'DESC')
            ->limit(6)
            ->get()
            ->getResultArray();
        $visitedBuilder = $this->onlyLiveLeads($this->db->table('site_visits')->select('COUNT(DISTINCT lead_id) AS total', false)->where('tenant_id', $tenantId)->where('deleted_at', null), $tenantId);
        $visited = (int) ($this->applyUsers($visitedBuilder, 'staff_id', $userIds)->get()->getRowArray()['total'] ?? 0);
        $booked = 0;

        if ($this->db->tableExists('sales')) {
            $bookedBuilder = $this->onlyLiveLeads($this->db->table('sales')->select('COUNT(DISTINCT lead_id) AS total', false)->where('tenant_id', $tenantId)->where('deleted_at', null), $tenantId);
            $booked = (int) ($this->applyUsers($bookedBuilder, 'credited_user_id', $userIds)->get()->getRowArray()['total'] ?? 0);
        }

        $attendance = [];

        if ($this->db->tableExists('attendance')) {
            $attendanceBuilder = $this->db->table('attendance')
                ->select('status, COUNT(*) AS total')
                ->where('tenant_id', $tenantId)
                ->where('work_date >=', date('Y-m-01'));
            $attendance = $this->applyUsers($attendanceBuilder, 'user_id', $userIds)
                ->groupBy('status')
                ->get()
                ->getResultArray();
        }

        $incentives = [];
        $pendingIncentive = ['total' => 0, 'amount_minor' => 0];

        if ($this->db->tableExists('incentives')) {
            $incentiveBuilder = $this->db->table('incentives')
                ->select('status, COUNT(*) AS total, SUM(amount_minor) AS amount_minor')
                ->where('tenant_id', $tenantId);
            $incentives = $this->applyUsers($incentiveBuilder, 'user_id', $userIds)
                ->groupBy('status')
                ->orderBy('amount_minor', 'DESC')
                ->get()
                ->getResultArray();

            foreach ($incentives as $row) {
                if ((string) $row['status'] === 'pending') {
                    $pendingIncentive = ['total' => (int) $row['total'], 'amount_minor' => (int) $row['amount_minor']];
                }
            }
        }

        $leadCount = $this->countWhere('leads', $tenantId, $userIds, 'owner_id');
        $dueBuilder = $this->db->table('followups f')->select('f.body, f.next_at, l.name AS lead_name')->join('leads l', 'l.id = f.lead_id')->where('f.tenant_id', $tenantId)->where('f.deleted_at', null)->where('l.deleted_at', null)->where('f.done_at', null)->where('f.next_at <=', date('Y-m-d 23:59:59'));
        $performance = $this->performance($tenantId, $userIds, is_array($scope) ? ($scope['team_ids'] ?? null) : null, $now);

        return [
            'leads'      => $leadCount,
            'followups'  => $openFollowups,
            'visits'     => $this->countWhere('site_visits', $tenantId, $userIds, 'staff_id', "status = 'scheduled'"),
            'available'  => $this->db->table('plots')->where('tenant_id', $tenantId)->where('deleted_at', null)->where('status', 'available')->countAllResults(),
            'plots'      => $plots,
            'sources'    => $sources,
            'statuses'   => $leadStatus,
            'visit_status' => $visitStatus,
            'follow_split' => [
                'done'     => (int) ($followSplit['done'] ?? 0),
                'overdue'  => (int) ($followSplit['overdue'] ?? 0),
                'upcoming' => (int) ($followSplit['upcoming'] ?? 0),
            ],
            'intake'     => $intake,
            'owners'     => $owners,
            'conversion' => [
                'leads'   => $leadCount,
                'visited' => $visited,
                'booked'  => $booked,
            ],
            'attendance' => $attendance,
            'incentives' => $incentives,
            'pending_incentive' => $pendingIncentive,
            'funnel'     => $this->statusFunnel($tenantId, $userIds),
            'staff'      => $performance['staff'],
            'teams'      => $performance['teams'],
            'projects'   => $performance['projects'],
            'due'        => $this->applyUsers($dueBuilder, 'IFNULL(f.assigned_user_id, f.user_id)', $userIds)->orderBy('f.next_at', 'ASC')->limit(6)->get()->getResultArray(),
        ];
    }

    /**
     * @param ?list<int> $userIds
     * @param ?list<int> $teamIds
     * @return array{staff: list<array<string, mixed>>, teams: list<array<string, mixed>>, projects: list<array<string, mixed>>}
     */
    private function performance(int $tenantId, ?array $userIds, ?array $teamIds, string $now): array
    {
        $people = $this->db->table('users u')
            ->select('u.id, u.name, r.name AS role_name')
            ->join('tenant_users tu', 'tu.user_id = u.id')
            ->join('roles r', 'r.id = tu.role_id', 'left')
            ->where('tu.tenant_id', $tenantId)
            ->where('u.deleted_at', null)
            ->orderBy('u.name', 'ASC');
        $people = $this->applyUsers($people, 'u.id', $userIds)->get()->getResultArray();
        $leads = $this->tally('leads', 'owner_id', $tenantId, $userIds, true);
        $followups = $this->tally('followups', 'IFNULL(assigned_user_id, user_id)', $tenantId, $userIds, true);
        $overdue = $this->tally('followups', 'IFNULL(assigned_user_id, user_id)', $tenantId, $userIds, true, 'done_at IS NULL AND next_at IS NOT NULL AND next_at < ' . $this->db->escape($now));
        $visits = $this->tally('site_visits', 'staff_id', $tenantId, $userIds, true);
        $bookings = $this->db->tableExists('sales') ? $this->tally('sales', 'credited_user_id', $tenantId, $userIds, true) : [];
        $staff = [];

        foreach ($people as $person) {
            $id = (int) $person['id'];
            $staff[] = [
                'name'      => (string) $person['name'],
                'role_name' => (string) ($person['role_name'] ?? ''),
                'leads'     => $leads[$id] ?? 0,
                'followups' => $followups[$id] ?? 0,
                'overdue'   => $overdue[$id] ?? 0,
                'visits'    => $visits[$id] ?? 0,
                'bookings'  => $bookings[$id] ?? 0,
            ];
        }

        usort($staff, static fn (array $a, array $b): int => [$b['bookings'], $b['leads'], $a['name']] <=> [$a['bookings'], $a['leads'], $b['name']]);

        return [
            'staff'    => $staff,
            'teams'    => $this->teamRows($tenantId, $teamIds, $leads, $followups, $visits, $bookings),
            'projects' => $this->projectRows($tenantId, $userIds),
        ];
    }

    /**
     * @param array<int, int> $leads
     * @param array<int, int> $followups
     * @param array<int, int> $visits
     * @param array<int, int> $bookings
     * @param ?list<int> $teamIds
     * @return list<array<string, mixed>>
     */
    private function teamRows(int $tenantId, ?array $teamIds, array $leads, array $followups, array $visits, array $bookings): array
    {
        if (! $this->db->tableExists('teams') || $teamIds === []) {
            return [];
        }

        $teams = $this->db->table('teams t')
            ->select('t.id, t.name, u.name AS head_name')
            ->join('users u', 'u.id = t.head_user_id', 'left')
            ->where('t.tenant_id', $tenantId)
            ->where('t.deleted_at', null)
            ->orderBy('t.name', 'ASC');

        if ($teamIds !== null) {
            $teams->whereIn('t.id', array_map('intval', $teamIds));
        }

        $rows = [];

        foreach ($teams->get()->getResultArray() as $team) {
            $members = $this->db->table('staff_profiles')
                ->select('user_id')
                ->where('tenant_id', $tenantId)
                ->where('team_id', (int) $team['id'])
                ->where('deleted_at', null)
                ->get()
                ->getResultArray();
            $ids = array_map(static fn (array $row): int => (int) $row['user_id'], $members);
            $head = $this->db->table('teams')->select('head_user_id')->where('id', (int) $team['id'])->get()->getRowArray();

            if ((int) ($head['head_user_id'] ?? 0) > 0) {
                $ids[] = (int) $head['head_user_id'];
            }

            $ids = array_values(array_unique($ids));
            $sum = static function (array $map) use ($ids): int {
                $total = 0;

                foreach ($ids as $id) {
                    $total += $map[$id] ?? 0;
                }

                return $total;
            };
            $rows[] = [
                'name'      => (string) $team['name'],
                'head_name' => (string) ($team['head_name'] ?? ''),
                'leads'     => $sum($leads),
                'followups' => $sum($followups),
                'visits'    => $sum($visits),
                'bookings'  => $sum($bookings),
            ];
        }

        return $rows;
    }

    /**
     * @param ?list<int> $userIds
     * @return list<array<string, mixed>>
     */
    private function projectRows(int $tenantId, ?array $userIds): array
    {
        $projects = $this->db->table('projects')->select('id, name')->where('tenant_id', $tenantId)->where('deleted_at', null)->orderBy('name', 'ASC')->get()->getResultArray();
        $plots = $this->db->table('plots')
            ->select('project_id, status, COUNT(*) AS total')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->groupBy('project_id')
            ->groupBy('status')
            ->get()
            ->getResultArray();
        $byProject = [];

        foreach ($plots as $row) {
            $byProject[(int) $row['project_id']][(string) $row['status']] = (int) $row['total'];
        }

        $sales = [];

        if ($this->db->tableExists('sales')) {
            $builder = $this->db->table('sales')
                ->select('project_id, COUNT(*) AS total, SUM(sale_value_minor) AS amount', false)
                ->where('tenant_id', $tenantId)
                ->where('deleted_at', null);
            $saleRows = $this->applyUsers($builder, 'credited_user_id', $userIds)->groupBy('project_id')->get()->getResultArray();

            foreach ($saleRows as $row) {
                $sales[(int) $row['project_id']] = ['total' => (int) $row['total'], 'amount' => (int) $row['amount']];
            }
        }

        $out = [];

        foreach ($projects as $project) {
            $id = (int) $project['id'];
            $counts = $byProject[$id] ?? [];
            $out[] = [
                'name'             => (string) $project['name'],
                'available'        => $counts['available'] ?? 0,
                'hold'             => $counts['hold'] ?? 0,
                'booked'           => $counts['booked'] ?? 0,
                'sold'             => $counts['sold'] ?? 0,
                'registered'       => $counts['registered'] ?? 0,
                'blocked'          => $counts['blocked'] ?? 0,
                'sales'            => $sales[$id]['total'] ?? 0,
                'sale_value_minor' => $sales[$id]['amount'] ?? 0,
            ];
        }

        return $out;
    }

    /**
     * Lead stages in the same order as the lead form.
     *
     * @param ?list<int> $userIds
     * @return list<array<string, mixed>>
     */
    private function statusFunnel(int $tenantId, ?array $userIds): array
    {
        $stages = ['New', 'Qualified', 'Follow Up', 'Proposal Sent', 'Site Visit Scheduled', 'Negotiation', 'Booked', 'Lost'];
        $alias = [
            'new' => 'New', 'contacted' => 'Qualified', 'qualified' => 'Qualified',
            'followup' => 'Follow Up', 'follow up' => 'Follow Up', 'follow-up' => 'Follow Up',
            'proposal sent' => 'Proposal Sent', 'site_visit' => 'Site Visit Scheduled',
            'site visit scheduled' => 'Site Visit Scheduled', 'site_visit_done' => 'Site Visit Scheduled',
            'negotiation' => 'Negotiation', 'booked' => 'Booked', 'lost' => 'Lost',
            'not_interested' => 'Lost', 'not interested' => 'Lost',
        ];
        $counts = [];

        foreach ($this->grouped($tenantId, 'leads', 'lead_stage', 'owner_id', $userIds) as $row) {
            $raw = strtolower(trim((string) ($row['lead_stage'] ?? '')));
            $label = $alias[$raw] ?? ($raw === '' ? 'New' : ucwords($raw));
            $counts[$label] = ($counts[$label] ?? 0) + (int) $row['total'];
        }

        $out = [];

        foreach ($stages as $stage) {
            $out[] = ['label' => $stage, 'total' => $counts[$stage] ?? 0];
            unset($counts[$stage]);
        }

        foreach ($counts as $label => $total) {
            if ($total > 0) {
                $out[] = ['label' => $label, 'total' => $total];
            }
        }

        return $out;
    }

    /**
     * @param ?list<int> $userIds
     */
    private function countWhere(string $table, int $tenantId, ?array $userIds, string $userColumn, ?string $extra = null): int
    {
        $builder = $this->db->table($table)->where('tenant_id', $tenantId);

        if (in_array($table, ['leads', 'followups', 'site_visits', 'sales', 'plots', 'lead_tasks'], true)) {
            $builder->where('deleted_at', null);
        }

        if (in_array($table, ['followups', 'site_visits', 'lead_tasks', 'sales'], true)) {
            $this->onlyLiveLeads($builder, $tenantId);
        }

        if ($extra !== null) {
            $builder->where($extra, null, false);
        }

        return $this->applyUsers($builder, $userColumn, $userIds)->countAllResults();
    }

    /**
     * @param ?list<int> $userIds
     * @return array<int, int>
     */
    private function tally(string $table, string $userColumn, int $tenantId, ?array $userIds, bool $soft, ?string $extra = null): array
    {
        $builder = $this->db->table($table)
            ->select($userColumn . ' AS user_id, COUNT(*) AS total', false)
            ->where('tenant_id', $tenantId);

        if ($soft) {
            $builder->where('deleted_at', null);
        }

        if (in_array($table, ['followups', 'site_visits', 'lead_tasks', 'sales'], true)) {
            $this->onlyLiveLeads($builder, $tenantId);
        }

        if ($extra !== null) {
            $builder->where($extra, null, false);
        }

        $map = [];

        foreach ($this->applyUsers($builder, $userColumn, $userIds)->groupBy($userColumn, false)->get()->getResultArray() as $row) {
            $map[(int) $row['user_id']] = (int) $row['total'];
        }

        return $map;
    }

    /**
     * @param ?list<int> $userIds
     */
    private function onlyLiveLeads(\CodeIgniter\Database\BaseBuilder $builder, int $tenantId, string $column = 'lead_id'): \CodeIgniter\Database\BaseBuilder
    {
        $leads = $this->db->prefixTable('leads');

        return $builder->where(
            $column . ' IN (SELECT id FROM ' . $leads . ' WHERE tenant_id = ' . (int) $tenantId . ' AND deleted_at IS NULL)',
            null,
            false,
        );
    }

    private function applyUsers(\CodeIgniter\Database\BaseBuilder $builder, string $column, ?array $userIds): \CodeIgniter\Database\BaseBuilder
    {
        if ($userIds === null) {
            return $builder;
        }

        $ids = array_values(array_map('intval', $userIds));

        if ($ids === []) {
            return $builder->where('1 = 0', null, false);
        }

        return $builder->whereIn($column, $ids, str_contains($column, '(') ? false : null);
    }

    /**
     * @param ?list<int> $userIds
     * @return list<array<string, mixed>>
     */
    private function grouped(int $tenantId, string $table, string $column, ?string $userColumn = null, ?array $userIds = null): array
    {
        $builder = $this->db->table($table)
            ->select($column . ', COUNT(*) AS total')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null);

        if (in_array($table, ['followups', 'site_visits', 'lead_tasks', 'sales'], true)) {
            $this->onlyLiveLeads($builder, $tenantId);
        }

        return $this->applyUsers($builder, $userColumn ?? $column, $userColumn === null ? null : $userIds)
            ->groupBy($column)
            ->get()
            ->getResultArray();
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function labelRows(int $tenantId, string $kind, array $rows, string $column): array
    {
        $labels = [];

        foreach ($this->options($tenantId, $kind) as $item) {
            $labels[(string) $item['code']] = (string) $item['label'];
        }

        $order = array_keys($labels);

        foreach ($rows as &$row) {
            $code = (string) ($row[$column] ?? '');
            $row['label'] = $labels[$code] ?? ucfirst(str_replace(['_', '-'], ' ', $code === '' ? 'None' : $code));
        }
        unset($row);

        usort($rows, static function (array $a, array $b) use ($order, $column): int {
            $left = array_search((string) ($a[$column] ?? ''), $order, true);
            $right = array_search((string) ($b[$column] ?? ''), $order, true);

            return ($left === false ? 999 : $left) <=> ($right === false ? 999 : $right);
        });

        return $rows;
    }

    /**
     * @param list<int> $leadIds
     */
    public function bulkTransfer(int $tenantId, array $leadIds, int $toUserId, int $byUserId, string $reason, SiteService $sites): int
    {
        $count = 0;

        foreach ($leadIds as $leadId) {
            $sites->transfer($tenantId, (int) $leadId, $toUserId, $byUserId, $reason);
            $count++;
        }

        return $count;
    }

    public function restoreLead(int $tenantId, int $id): void
    {
        $row = $this->db->table('leads')->where('tenant_id', $tenantId)->where('id', $id)->where('deleted_at !=', null)->get()->getRowArray();

        if ($row === null) {
            throw new RuntimeException('That lead is not in the removed list.');
        }

        $this->db->table('leads')->where('id', $id)->update(['deleted_at' => null, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    private function coord(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 7, '.', '');
    }
}
