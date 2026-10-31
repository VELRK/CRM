<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\AccessCatalog;
use App\Libraries\ApiCatalog;
use App\Libraries\ApiContext;
use App\Libraries\AttendanceLocationService;
use App\Libraries\DeskService;
use App\Libraries\ReportFile;
use App\Libraries\EstateService;
use App\Libraries\PushService;
use App\Libraries\RoleService;
use App\Libraries\SiteService;
use App\Libraries\WorkspaceService;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class DeskApi extends BaseController
{
    private WorkspaceService $workspaces;
    private SiteService $sites;
    private EstateService $estate;
    private DeskService $desk;
    private RoleService $roles;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger,
    ): void {
        parent::initController($request, $response, $logger);
        $db = db_connect();
        $this->workspaces = new WorkspaceService($db);
        $this->sites      = new SiteService($db);
        $this->estate     = new EstateService($db);
        $this->desk       = new DeskService($db);
        $this->roles      = new RoleService($db, $this->workspaces);
        $this->response->setHeader('Access-Control-Allow-Origin', '*');
        $this->response->setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type');
        $this->response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    }

    public function options(): ResponseInterface
    {
        return $this->response->setStatusCode(204);
    }

    public function login(): ResponseInterface
    {
        $input = $this->input();
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $user = $this->workspaces->userByEmail($email);

        if ($user === null || ! password_verify($password, (string) $user['password_hash'])) {
            return $this->fail('Email or password is wrong.', 401);
        }

        $member = $this->workspaces->membership((int) $user['id']);

        if ($member === null) {
            return $this->fail('This account is not in a workspace yet.', 403);
        }

        if (! db_connect()->tableExists('api_tokens')) {
            return $this->fail('API tokens are not installed yet.', 500);
        }

        $raw = bin2hex(random_bytes(32));
        db_connect()->table('api_tokens')->insert([
            'tenant_id'  => (int) $member['tenant_id'],
            'user_id'    => (int) $user['id'],
            'name'       => mb_substr(trim((string) ($input['device'] ?? 'API')) ?: 'API', 0, 80),
            'token_hash' => hash('sha256', $raw),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->desk->audit((int) $member['tenant_id'], (int) $user['id'], (string) $member['plan_code'], 'login', 'user', (int) $user['id'], 'API sign-in');

        $isOwner = (int) ($member['is_owner'] ?? 0) === 1;
        $permissions = $this->workspaces->permissions((int) $member['role_id']);
        $scope = $this->estate->reportScope((int) $member['tenant_id'], (int) $user['id'], (string) $member['role_name'], $isOwner);

        return $this->ok([
            'token'       => $raw,
            'user'        => [
                'id'        => (int) $user['id'],
                'name'      => (string) $member['user_name'],
                'email'     => $email,
                'role'      => (string) $member['role_name'],
                'workspace' => (string) $member['workspace_name'],
                'plan'      => (string) $member['plan_code'],
            ],
            'permissions' => $isOwner ? AccessCatalog::keys() : array_values($permissions),
            'modules'     => AccessCatalog::modules($permissions, $isOwner),
            'scope'       => $scope,
        ]);
    }

    public function logout(): ResponseInterface
    {
        $actor = $this->actor();
        db_connect()->table('api_tokens')->where('id', (int) $actor['token_id'])->update(['revoked_at' => date('Y-m-d H:i:s')]);
        $token = trim((string) ($this->input()['token'] ?? $this->input()['device_token'] ?? ''));

        if ($token !== '') {
            $this->alerts()->deactivateDevice((int) $actor['tenant_id'], (int) $actor['user_id'], $token);
        }

        return $this->ok(['revoked' => true]);
    }

    public function company(): ResponseInterface
    {
        return $this->run(fn () => $this->companyRow());
    }

    public function saveCompany(): ResponseInterface
    {
        if ($denied = $this->guard('workspace.settings')) {
            return $denied;
        }

        return $this->run(function () {
            $this->ensureCompanyColumns();
            $input = $this->input();
            $name = trim((string) ($input['companyName'] ?? $input['name'] ?? ''));
            $adminName = trim((string) ($input['companyAdminName'] ?? ''));
            $adminEmail = strtolower(trim((string) ($input['companyAdminEmail'] ?? '')));

            if ($name === '' || $adminName === '' || $adminEmail === '') {
                throw new RuntimeException('A company needs a name, an admin name, and an admin email.');
            }

            if (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid admin email.');
            }

            $db = db_connect();
            $owner = $this->companyOwner();

            if ($owner === null) {
                throw new RuntimeException('This company has no owner yet.');
            }

            $taken = $db->table('users')->where('email', $adminEmail)->where('id !=', (int) $owner['id'])->countAllResults();

            if ($taken > 0) {
                throw new RuntimeException('That admin email is already used.');
            }

            $phone = preg_replace('/\D+/', '', (string) ($input['phone'] ?? '')) ?? '';
            $address = trim((string) ($input['address'] ?? ''));

            if ($phone !== '' && strlen($phone) !== 10) {
                throw new RuntimeException('Enter a 10-digit company phone, or leave it empty.');
            }

            if (strlen($address) > 255) {
                throw new RuntimeException('The address is too long.');
            }

            $now = date('Y-m-d H:i:s');
            $db->table('tenants')->where('id', $this->tenant())->update([
                'name'       => $name,
                'phone'      => $phone === '' ? null : $phone,
                'address'    => $address === '' ? null : $address,
                'updated_at' => $now,
            ]);
            $db->table('users')->where('id', (int) $owner['id'])->update([
                'name'       => $adminName,
                'email'      => $adminEmail,
                'updated_at' => $now,
            ]);
            $this->estate->saveAttendanceArea($this->tenant(), $input);

            return $this->ok($this->companyRow());
        });
    }

    public function companyLogo(): ResponseInterface
    {
        $path = $this->logoPath();

        if ($path === null) {
            return $this->fail('This company has no logo yet.', 404);
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return $this->response->setHeader('Content-Type', $mime)->setHeader('Cache-Control', 'private, max-age=60')->setBody((string) file_get_contents($path));
    }

    public function saveLogo(): ResponseInterface
    {
        if ($denied = $this->guard('workspace.settings')) {
            return $denied;
        }

        return $this->run(function () {
            $this->ensureCompanyColumns();
            $file = $this->request->getFile('logo');

            if ($file === null || ! $file->isValid()) {
                throw new RuntimeException('Choose a logo image.');
            }

            $ext = strtolower($file->getExtension() ?: '');
            $ext = $ext === 'jpeg' ? 'jpg' : $ext;

            if (! in_array($ext, ['png', 'jpg', 'webp'], true)) {
                throw new RuntimeException('Use a PNG, JPG, or WebP logo.');
            }

            if ((int) $file->getSize() < 1 || (int) $file->getSize() > 2 * 1024 * 1024) {
                throw new RuntimeException('Use a logo up to 2 MB.');
            }

            $dir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . $this->tenant();

            if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                throw new RuntimeException('The file store is not available.');
            }

            foreach (['png', 'jpg', 'webp'] as $old) {
                $previous = $dir . DIRECTORY_SEPARATOR . 'company-logo.' . $old;

                if (is_file($previous)) {
                    unlink($previous);
                }
            }

            $stored = 'company-logo.' . $ext;
            $file->move($dir, $stored, true);
            db_connect()->table('tenants')->where('id', $this->tenant())->update([
                'logo'       => $stored,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return $this->companyRow();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function companyRow(): array
    {
        $this->ensureCompanyColumns();
        $tenant = db_connect()->table('tenants')->where('id', $this->tenant())->where('deleted_at', null)->get()->getRowArray();

        if ($tenant === null) {
            throw new RuntimeException('This company is not available.');
        }

        $owner = $this->companyOwner();
        $area = $this->estate->attendanceArea($this->tenant());

        return [
            'companyName'       => (string) ($tenant['name'] ?? ''),
            'companyAdminName'  => (string) ($owner['name'] ?? ''),
            'companyAdminEmail' => (string) ($owner['email'] ?? ''),
            'phone'             => (string) ($tenant['phone'] ?? ''),
            'address'           => (string) ($tenant['address'] ?? ''),
            'logo'              => $this->logoPath() !== null,
            'updatedDate'       => $tenant['updated_at'] ?? null,
            'attendanceLat'     => $area['lat'],
            'attendanceLng'     => $area['lng'],
            'attendanceKm'      => $area['km'] === '0' ? '' : $area['km'],
        ];
    }

    private bool $companyColumnsReady = false;

    private function ensureCompanyColumns(): void
    {
        if ($this->companyColumnsReady) {
            return;
        }

        $db = db_connect();
        $columns = [];

        if (! $db->fieldExists('phone', 'tenants')) {
            $columns['phone'] = ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true];
        }

        if (! $db->fieldExists('address', 'tenants')) {
            $columns['address'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
        }

        if (! $db->fieldExists('logo', 'tenants')) {
            $columns['logo'] = ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true];
        }

        if ($columns !== []) {
            \Config\Database::forge()->addColumn('tenants', $columns);
            $db->resetDataCache();
        }

        $this->companyColumnsReady = true;
    }

    private function logoPath(): ?string
    {
        $this->ensureCompanyColumns();
        $tenant = db_connect()->table('tenants')->select('logo')->where('id', $this->tenant())->get()->getRowArray();
        $name = (string) ($tenant['logo'] ?? '');

        if (! preg_match('/^company-logo\.(png|jpg|webp)$/', $name)) {
            return null;
        }

        $path = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . $this->tenant() . DIRECTORY_SEPARATOR . $name;

        return is_file($path) ? $path : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function companyOwner(): ?array
    {
        $row = db_connect()->table('tenant_users tu')
            ->select('u.id, u.name, u.email')
            ->join('users u', 'u.id = tu.user_id')
            ->join('roles r', 'r.id = tu.role_id')
            ->where('tu.tenant_id', $this->tenant())
            ->where('r.is_owner', 1)
            ->where('u.deleted_at', null)
            ->get()
            ->getRowArray();

        return $row === null ? null : $row;
    }

    public function me(): ResponseInterface
    {
        $actor = $this->actor();
        $isOwner = (bool) $actor['is_owner'];

        return $this->ok([
            'id'          => $actor['user_id'],
            'name'        => $actor['user_name'],
            'email'       => $actor['email'],
            'role'        => $actor['role_name'],
            'workspace'   => $actor['workspace'],
            'plan'        => $actor['plan_code'],
            'permissions' => $isOwner ? AccessCatalog::keys() : array_values($actor['permissions']),
            'modules'     => AccessCatalog::modules($actor['permissions'], $isOwner),
            'scope'       => $this->viewScope(),
        ]);
    }

    public function catalog(): ResponseInterface
    {
        return $this->ok([
            'base'    => rtrim(base_url(), '/'),
            'note'    => 'Sign in with POST /api/v1/auth/login, then send Authorization: Bearer and that token on every other call, including GET. The workspace comes from the token. Do not send companyId. Automatic lead assignment is not in this API. WhatsApp messages are stored and are not delivered until a provider is connected.',
            'entries' => ApiCatalog::entries(),
        ]);
    }

    public function postman(): ResponseInterface
    {
        return $this->response
            ->setHeader('Content-Disposition', 'attachment; filename="atrium-api.postman_collection.json"')
            ->setJSON(ApiCatalog::postman(rtrim(base_url(), '/')));
    }

    public function staff(): ResponseInterface
    {
        if ($denied = $this->guard('staff.view')) {
            return $denied;
        }

        return $this->pageList($this->teamRows($this->sites->staff($this->tenant()), ['user_id']));
    }

    public function saveStaff(int $id): ResponseInterface
    {
        if ($denied = $this->guard('staff.update')) {
            return $denied;
        }

        return $this->run(function () use ($id) {
            $this->sites->saveStaff($this->tenant(), $id, $this->input());

            return $this->ok(['user_id' => $id]);
        });
    }

    public function openWork(int $id): ResponseInterface
    {
        if ($denied = $this->guard('staff.view')) {
            return $denied;
        }

        $db = db_connect();
        $leads = $db->table('leads')->where('tenant_id', $this->tenant())->where('owner_id', $id)->where('deleted_at', null)->whereNotIn('status', ['lost', 'not_interested', 'won'])->get()->getResultArray();
        $followups = $db->table('followups')->where('tenant_id', $this->tenant())->where('deleted_at', null)->where('done_at', null)->groupStart()->where('assigned_user_id', $id)->orWhere('user_id', $id)->groupEnd()->get()->getResultArray();

        return $this->ok(['leads' => $leads, 'followups' => $followups]);
    }

    public function teams(): ResponseInterface
    {
        if ($denied = $this->guard('teams.view')) {
            return $denied;
        }

        $teams = $this->estate->teams($this->tenant());
        $scope = $this->viewScope();

        if (($scope['level'] ?? '') === 'team' && is_array($scope['team_ids'])) {
            $allowedTeams = array_flip($scope['team_ids']);
            $teams = array_values(array_filter($teams, static fn (array $team): bool => isset($allowedTeams[(int) $team['id']])));
        }
        $names = [];

        foreach (db_connect()->table('users u')->select('u.id, u.name')->join('tenant_users tu', 'tu.user_id = u.id')->where('tu.tenant_id', $this->tenant())->get()->getResultArray() as $person) {
            $names[(int) $person['id']] = (string) $person['name'];
        }

        foreach ($teams as &$team) {
            $ids = $this->estate->memberIds($this->tenant(), (int) $team['id']);
            $team['member_ids'] = $ids;
            $team['members'] = implode(', ', array_values(array_filter(array_map(static fn (int $id): string => $names[$id] ?? '', $ids))));
        }

        return $this->ok($teams);
    }

    public function saveTeam(?int $id = null): ResponseInterface
    {
        if ($denied = $this->guard('teams.manage')) {
            return $denied;
        }

        return $this->run(function () use ($id) {
            return $this->ok(['id' => $this->estate->saveTeam($this->tenant(), $id, $this->input())]);
        });
    }

    public function deleteTeam(int $id): ResponseInterface
    {
        if ($denied = $this->guard('teams.manage')) {
            return $denied;
        }

        return $this->run(function () use ($id) {
            $this->estate->deleteTeam($this->tenant(), $id);

            return $this->ok(['id' => $id, 'deleted' => true]);
        });
    }

    public function teamHistory(int $id): ResponseInterface
    {
        if ($denied = $this->guard('teams.view')) {
            return $denied;
        }

        $rows = db_connect()->table('team_memberships m')
            ->select('m.user_id, m.from_on, m.to_on, u.name')
            ->join('users u', 'u.id = m.user_id', 'left')
            ->where('m.tenant_id', $this->tenant())
            ->where('m.team_id', $id)
            ->orderBy('m.from_on', 'DESC')
            ->get()
            ->getResultArray();

        return $this->ok($rows);
    }

    public function projects(): ResponseInterface
    {
        if ($denied = $this->guard('projects.view')) {
            return $denied;
        }

        return $this->pageList($this->places()->withProjectLocations($this->tenant(), $this->onlyTeamProjects($this->sites->projects($this->tenant()), 'id')));
    }

    public function saveProject(?int $id = null): ResponseInterface
    {
        if ($denied = $this->guard($id === null ? 'projects.create' : 'projects.update')) {
            return $denied;
        }

        return $this->run(function () use ($id) {
            if ($id !== null && ! $this->projectAllowed($id)) {
                return $this->fail('That project is not on your team.', 404);
            }

            $input = $this->input();
            $projectId = $this->sites->saveProject($this->tenant(), $id, $input);
            $this->places()->saveProjectLocation($this->tenant(), $projectId, $input);

            return $this->ok(['id' => $projectId]);
        });
    }

    public function deleteProject(int $id): ResponseInterface
    {
        if ($denied = $this->guard('projects.delete')) {
            return $denied;
        }

        return $this->run(function () use ($id) {
            if (! $this->projectAllowed($id)) {
                return $this->fail('That project is not on your team.', 404);
            }

            $this->sites->deleteProject($this->tenant(), $id);

            return $this->ok(['id' => $id, 'deleted' => true]);
        });
    }

    public function projectDashboard(int $id): ResponseInterface
    {
        if ($denied = $this->guard('projects.view')) {
            return $denied;
        }

        $project = $this->sites->project($this->tenant(), $id);

        if ($project === null) {
            return $this->fail('That project is not in this workspace.', 404);
        }

        $project = $this->places()->withProjectLocations($this->tenant(), [$project])[0];

        if (! $this->projectAllowed($id)) {
            return $this->fail('That project is not on your team.', 404);
        }

        $plots = ['available' => 0, 'hold' => 0, 'booked' => 0, 'sold' => 0, 'registered' => 0, 'blocked' => 0];

        foreach ($this->sites->plots($this->tenant(), $id) as $plot) {
            $status = (string) $plot['status'];
            $plots[$status] = ($plots[$status] ?? 0) + 1;
        }

        $leads = 0;

        foreach ($this->sites->leads($this->tenant()) as $lead) {
            if ((int) ($lead['project_id'] ?? 0) === $id) {
                $leads++;
            }
        }

        $visits = 0;

        foreach ($this->sites->visits($this->tenant()) as $visit) {
            if ((int) ($visit['project_id'] ?? 0) === $id) {
                $visits++;
            }
        }

        return $this->ok([
            'project'  => $project,
            'plots'    => $plots,
            'leads'    => $leads,
            'visits'   => $visits,
            'bookings' => ($plots['booked'] ?? 0) + ($plots['sold'] ?? 0) + ($plots['registered'] ?? 0),
        ]);
    }

    public function plots(): ResponseInterface
    {
        if ($denied = $this->guard('plots.view')) {
            return $denied;
        }

        $rows = $this->sites->plots($this->tenant(), (int) $this->request->getGet('project'));
        $status = trim((string) $this->request->getGet('status'));
        $facing = strtolower(trim((string) $this->request->getGet('facing')));
        $min = (int) $this->request->getGet('min_price');
        $max = (int) $this->request->getGet('max_price');
        $rows = array_values(array_filter($rows, static function (array $row) use ($status, $facing, $min, $max): bool {
            if ($status !== '' && (string) $row['status'] !== $status) {
                return false;
            }
            if ($facing !== '' && ! str_contains(strtolower((string) ($row['facing'] ?? '')), $facing)) {
                return false;
            }
            $price = (int) $row['price_minor'];
            if ($min > 0 && $price < $min * 100) {
                return false;
            }
            if ($max > 0 && $price > $max * 100) {
                return false;
            }

            return true;
        }));

        return $this->pageList($this->onlyTeamProjects($rows, 'project_id'));
    }

    public function savePlot(?int $id = null): ResponseInterface
    {
        if ($denied = $this->guard($id === null ? 'plots.create' : 'plots.update')) {
            return $denied;
        }

        return $this->run(function () use ($id) {
            $input = $this->input();
            $status = (string) ($input['status'] ?? 'available');
            $before = $id === null ? null : $this->sites->plot($this->tenant(), $id);

            if ($before !== null && ! $this->projectAllowed((int) $before['project_id'])) {
                return $this->fail('That project is not on your team.', 404);
            }

            if (! $this->projectAllowed((int) ($input['project_id'] ?? 0))) {
                return $this->fail('That project is not on your team.', 404);
            }
            $db = db_connect();
            $db->transException(true)->transStart();
            $input['by_user_id'] = $this->user();
            $saved = $this->sites->savePlot($this->tenant(), $id, $input);

            if ($before === null || (string) $before['status'] !== $status) {
                $this->estate->recordSale($this->tenant(), $this->user(), $saved, $status, $input);
            }

            $db->transComplete();
            $this->desk->audit($this->tenant(), $this->user(), $this->plan(), 'plot.status', 'plot', $saved, $status);

            return $this->ok(['id' => $saved, 'status' => $status]);
        });
    }

    public function plotBooking(int $id): ResponseInterface
    {
        if ($denied = $this->guard('plots.update')) {
            return $denied;
        }

        return $this->run(function () use ($id) {
            $input = $this->input();
            $before = $this->sites->plot($this->tenant(), $id);

            if ($before === null) {
                return $this->fail('That plot is not in this workspace.', 404);
            }

            if (! $this->projectAllowed((int) $before['project_id'])) {
                return $this->fail('That project is not on your team.', 404);
            }

            $input['by_user_id'] = $this->user();
            $status = (string) ($input['status'] ?? $before['status']);
            $db = db_connect();
            $db->transException(true)->transStart();
            $this->sites->saveBooking($this->tenant(), $id, $input);

            if (! in_array((string) $before['status'], SiteService::SALE_STATUSES, true) && in_array($status, SiteService::SALE_STATUSES, true)) {
                $input['credited_user_id'] = (int) ($input['associateUserId'] ?? $input['associate_user_id'] ?? 0);
                $input['head_user_id'] = (int) ($input['headUserId'] ?? $input['head_user_id'] ?? 0);
                $input['lead_id'] = (int) ($input['leadId'] ?? $input['lead_id'] ?? 0);
                $input['sale_value'] = (string) ($input['bookingAmount'] ?? $input['booking_amount'] ?? '');
                $this->estate->recordSale($this->tenant(), $this->user(), $id, $status, $input);
            }

            $db->transComplete();
            $rows = array_values(array_filter($this->sites->plots($this->tenant()), static fn (array $row): bool => (int) $row['id'] === $id));

            return $this->ok($rows[0] ?? ['id' => $id]);
        });
    }

    public function deletePlot(int $id): ResponseInterface
    {
        if ($denied = $this->guard('plots.delete')) {
            return $denied;
        }

        return $this->run(function () use ($id) {
            $plot = $this->sites->plot($this->tenant(), $id);

            if ($plot === null || ! $this->projectAllowed((int) $plot['project_id'])) {
                return $this->fail('That project is not on your team.', 404);
            }

            $this->sites->deletePlot($this->tenant(), $id);

            return $this->ok(['id' => $id, 'deleted' => true]);
        });
    }

    public function plotHistory(int $id): ResponseInterface
    {
        if ($denied = $this->guard('plots.view')) {
            return $denied;
        }

        $plot = $this->sites->plot($this->tenant(), $id);

        if ($plot === null || ! $this->projectAllowed((int) $plot['project_id'])) {
            return $this->fail('That project is not on your team.', 404);
        }

        $rows = db_connect()->table('plot_status_history')->where('tenant_id', $this->tenant())->where('plot_id', $id)->orderBy('id', 'DESC')->get()->getResultArray();

        return $this->ok($rows);
    }

    public function exportPlots(): ResponseInterface
    {
        if ($denied = $this->guard('exports.download')) {
            return $denied;
        }

        $rows = $this->onlyTeamProjects($this->sites->plots($this->tenant(), (int) $this->request->getGet('project')), 'project_id');

        return $this->csv('plots.csv', ['Project', 'Plot', 'Block', 'Area', 'Facing', 'Price', 'Commission', 'Status'], array_map(static fn (array $row): array => [
            $row['project_name'] ?? '', $row['plot_no'], $row['block'], $row['area_sqft'], $row['facing'], ((int) $row['price_minor']) / 100, ((int) ($row['commission_minor'] ?? 0)) / 100, $row['status'],
        ], $rows));
    }

    public function mobileDashboard(): ResponseInterface
    {
        if ($denied = $this->guardAny(['leads.view', 'followups.view', 'reports.view'])) {
            return $denied;
        }

        $actor = $this->actor();
        $scope = $this->estate->reportScope($this->tenant(), $this->user(), (string) $actor['role_name'], (bool) $actor['is_owner']);

        return $this->ok($this->estate->mobileCounts($this->tenant(), $scope));
    }

    public function catalogList(string $kind): ResponseInterface
    {
        if ($denied = $this->guard('leads.view')) {
            return $denied;
        }

        $active = $this->request->getGet('active');
        $only = $active === null || $active === '' ? null : in_array((string) $active, ['1', 'true', 'True'], true);

        return $this->ok($this->estate->catalogItems($this->tenant(), $kind, $only));
    }

    public function catalogSave(string $kind, ?int $id = null): ResponseInterface
    {
        if ($denied = $this->guard('leads.update')) {
            return $denied;
        }

        return $this->run(function () use ($kind, $id) {
            return $this->ok($this->estate->saveCatalog($this->tenant(), $kind, $id, $this->input()));
        });
    }

    public function catalogDelete(string $kind, int $id): ResponseInterface
    {
        if ($denied = $this->guard('leads.update')) {
            return $denied;
        }

        return $this->run(function () use ($kind, $id) {
            $this->estate->deleteCatalog($this->tenant(), $kind, $id);

            return $this->ok(['id' => $id, 'deleted' => true]);
        });
    }

    public function filterLeads(): ResponseInterface
    {
        if ($denied = $this->guard('leads.view')) {
            return $denied;
        }

        return $this->filterRows($this->teamRows($this->sites->leads($this->tenant(), ''), ['owner_id']), 'lead');
    }

    public function leads(): ResponseInterface
    {
        if ($denied = $this->guard('leads.view')) {
            return $denied;
        }

        return $this->pageList($this->teamRows($this->sites->leads($this->tenant(), trim((string) $this->request->getGet('q'))), ['owner_id']));
    }

    public function lead(int $id): ResponseInterface
    {
        if ($denied = $this->guard('leads.view')) {
            return $denied;
        }

        $lead = $this->sites->lead($this->tenant(), $id);

        if ($lead === null || $this->outsideTeam($lead, ['owner_id'])) {
            return $this->fail('That lead is not in this workspace.', 404);
        }

        return $this->ok($lead);
    }

    public function checkLead(): ResponseInterface
    {
        if ($denied = $this->guard('leads.view')) {
            return $denied;
        }

        $input = $this->input();
        $phone = preg_replace('/\D+/', '', (string) ($input['phoneNumber'] ?? $input['phone'] ?? '')) ?? '';
        $row = db_connect()->table('leads')->where('tenant_id', $this->tenant())->where('phone_key', $phone)->where('deleted_at', null)->get()->getRowArray();

        if ($row === null) {
            return $this->ok(['duplicate' => false, 'lead' => null]);
        }

        $lead = $this->sites->lead($this->tenant(), (int) $row['id']);

        if ($lead === null || $this->outsideTeam($lead, ['owner_id'])) {
            return $this->ok(['duplicate' => false, 'lead' => null]);
        }

        return $this->ok([
            'duplicate' => true,
            'lead'      => $lead,
            'timeline'  => $this->sites->leadActivity($this->tenant(), (int) $row['id']),
        ]);
    }

    public function saveLead(?int $id = null): ResponseInterface
    {
        if ($denied = $this->guard($id === null ? 'leads.create' : 'leads.update')) {
            return $denied;
        }

        if ($id !== null && ($blocked = $this->leadGate($id))) {
            return $blocked;
        }

        return $this->run(function () use ($id) {
            $input = $this->input();
            $input['by_user_id'] = $this->user();

            try {
                $saved = $this->sites->saveLead($this->tenant(), $id, $input);
            } catch (RuntimeException $e) {
                if (str_contains($e->getMessage(), 'already belongs')) {
                    return $this->fail($e->getMessage(), 409);
                }

                throw $e;
            }

            $this->desk->audit($this->tenant(), $this->user(), $this->plan(), $id === null ? 'lead.create' : 'lead.update', 'lead', $saved, (string) ($input['status'] ?? ''));

            if ($id === null) {
                $this->alertLead($saved);
            }

            return $this->ok(['id' => $saved]);
        });
    }

    public function deleteLead(int $id): ResponseInterface
    {
        if ($denied = $this->guard('leads.delete')) {
            return $denied;
        }

        if ($blocked = $this->leadGate($id)) {
            return $blocked;
        }

        return $this->run(function () use ($id) {
            $this->sites->deleteLead($this->tenant(), $id);

            return $this->ok(['id' => $id, 'deleted' => true]);
        });
    }

    public function leadStage(int $id): ResponseInterface
    {
        if ($denied = $this->guard('leads.update')) {
            return $denied;
        }

        if ($blocked = $this->leadGate($id)) {
            return $blocked;
        }

        return $this->run(function () use ($id) {
            $input = $this->input();
            $this->sites->setLeadStage($this->tenant(), $id, (string) ($input['leadStage'] ?? $input['lead_stage'] ?? ''), $this->user());

            return $this->ok($this->sites->lead($this->tenant(), $id));
        });
    }

    public function leadProjects(int $id): ResponseInterface
    {
        if ($denied = $this->guard('leads.update')) {
            return $denied;
        }

        if ($blocked = $this->leadGate($id)) {
            return $blocked;
        }

        return $this->run(function () use ($id) {
            $input = $this->input();
            $this->sites->setInterestedProjects($this->tenant(), $id, $input['intrestedProjects'] ?? $input['interestedProjects'] ?? $input['interested_projects'] ?? []);

            return $this->ok($this->sites->lead($this->tenant(), $id));
        });
    }

    public function leadNotes(int $id): ResponseInterface
    {
        if ($denied = $this->guard('leads.view')) {
            return $denied;
        }

        if ($blocked = $this->leadGate($id)) {
            return $blocked;
        }

        return $this->run(fn () => $this->ok($this->sites->leadNotes($this->tenant(), $id)));
    }

    public function saveNote(int $id): ResponseInterface
    {
        if ($denied = $this->guard('leads.update')) {
            return $denied;
        }

        if ($blocked = $this->leadGate($id)) {
            return $blocked;
        }

        return $this->run(function () use ($id) {
            $saved = $this->sites->saveNote($this->tenant(), $id, $this->user(), $this->input());

            return $this->ok(['id' => $saved]);
        });
    }

    public function deleteNote(int $id, int $noteId): ResponseInterface
    {
        if ($denied = $this->guard('leads.update')) {
            return $denied;
        }

        if ($blocked = $this->leadGate($id)) {
            return $blocked;
        }

        return $this->run(function () use ($id, $noteId) {
            $this->sites->deleteNote($this->tenant(), $id, $noteId);

            return $this->ok(['id' => $noteId, 'deleted' => true]);
        });
    }

    public function leadDeals(int $id): ResponseInterface
    {
        if ($denied = $this->guard('leads.view')) {
            return $denied;
        }

        if ($blocked = $this->leadGate($id)) {
            return $blocked;
        }

        return $this->run(fn () => $this->ok($this->sites->leadDeals($this->tenant(), $id)));
    }

    public function saveDeal(int $id): ResponseInterface
    {
        if ($denied = $this->guard('leads.update')) {
            return $denied;
        }

        if ($blocked = $this->leadGate($id)) {
            return $blocked;
        }

        return $this->run(function () use ($id) {
            $saved = $this->sites->saveDeal($this->tenant(), $id, $this->user(), $this->input());

            return $this->ok(['id' => $saved]);
        });
    }

    public function timeline(int $id): ResponseInterface
    {
        if ($denied = $this->guard('leads.view')) {
            return $denied;
        }

        $lead = $this->sites->lead($this->tenant(), $id);

        if ($lead === null || $this->outsideTeam($lead, ['owner_id'])) {
            return $this->fail('That lead is not in this workspace.', 404);
        }

        return $this->ok($this->sites->leadActivity($this->tenant(), $id));
    }

    public function transfer(int $id): ResponseInterface
    {
        if ($denied = $this->guard('leads.transfer')) {
            return $denied;
        }

        if ($blocked = $this->leadGate($id)) {
            return $blocked;
        }

        return $this->run(function () use ($id) {
            $input = $this->input();
            $this->sites->transfer($this->tenant(), $id, (int) ($input['assignedTo'] ?? $input['assigned_to'] ?? $input['to_user_id'] ?? 0), $this->user(), trim((string) ($input['reason'] ?? $input['note'] ?? '')));
            $this->desk->audit($this->tenant(), $this->user(), $this->plan(), 'lead.transfer', 'lead', $id, (string) ($input['reason'] ?? ''));
            $this->alertLead($id);

            return $this->ok(['id' => $id]);
        });
    }

    public function bulkTransfer(): ResponseInterface
    {
        if ($denied = $this->guard('leads.transfer')) {
            return $denied;
        }

        return $this->run(function () {
            $input = $this->input();
            $ids = array_map('intval', is_array($input['lead_ids'] ?? null) ? $input['lead_ids'] : []);

            foreach ($ids as $leadId) {
                if ($blocked = $this->leadGate((int) $leadId)) {
                    return $blocked;
                }
            }

            $reason = trim((string) ($input['reason'] ?? ''));
            $moved = $this->estate->bulkTransfer($this->tenant(), $ids, (int) ($input['assignedTo'] ?? $input['to_user_id'] ?? 0), $this->user(), $reason, $this->sites);

            foreach ($ids as $leadId) {
                $this->desk->audit($this->tenant(), $this->user(), $this->plan(), 'lead.transfer', 'lead', (int) $leadId, $reason);
                $this->alertLead((int) $leadId);
            }

            return $this->ok(['moved' => $moved]);
        });
    }

    public function exportLeads(): ResponseInterface
    {
        if ($denied = $this->guard('exports.download')) {
            return $denied;
        }

        return $this->csv('leads.csv', ['Name', 'Phone', 'Email', 'Source', 'Status', 'Owner'], array_map(static fn (array $row): array => [
            $row['name'], $row['phone'], $row['email'], $row['source'], $row['status'], $row['owner_name'] ?? '',
        ], $this->sites->leads($this->tenant(), trim((string) $this->request->getGet('q')))));
    }

    public function followups(): ResponseInterface
    {
        if ($denied = $this->guard('followups.view')) {
            return $denied;
        }

        $view = (string) $this->request->getGet('view');
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $rows = array_values(array_filter($this->sites->followups($this->tenant()), static function (array $row) use ($view, $now, $today): bool {
            if ($view === '' || $view === 'all') {
                return true;
            }
            if ($row['done_at'] !== null) {
                return false;
            }
            $next = (string) ($row['next_at'] ?? '');
            if ($view === 'today') {
                return str_starts_with($next, $today);
            }
            if ($view === 'overdue') {
                return $next !== '' && $next < $now;
            }
            if ($view === 'upcoming') {
                return $next === '' || $next >= $today . ' 23:59:59';
            }

            return true;
        }));

        return $this->pageList($this->teamRows($rows, ['assigned_to', 'user_id']));
    }

    public function filterFollowups(): ResponseInterface
    {
        if ($denied = $this->guard('followups.view')) {
            return $denied;
        }

        return $this->filterRows($this->teamRows($this->sites->followups($this->tenant()), ['assigned_to', 'user_id']), 'followup');
    }

    public function saveFollowup(): ResponseInterface
    {
        if ($denied = $this->guard('followups.create')) {
            return $denied;
        }

        return $this->run(function () {
            $input = $this->input();
            $leadId = (int) ($input['leadId'] ?? $input['lead_id'] ?? 0);

            if ($blocked = $this->leadGate($leadId)) {
                return $blocked;
            }

            $id = $this->sites->saveFollowup($this->tenant(), $this->user(), $leadId, $input);
            $this->desk->audit($this->tenant(), $this->user(), $this->plan(), 'followup.create', 'lead', $leadId, (string) ($input['body'] ?? ''));
            $assigned = (int) ($input['assignedTo'] ?? $input['assigned_to'] ?? $this->user());
            $this->alert('followup.created', [
                'subject_user_id' => $assigned,
                'subject_type'    => 'followup',
                'subject_id'      => $id,
                'lead'            => (string) ($this->sites->lead($this->tenant(), $leadId)['name'] ?? ''),
                'name'            => $this->personName($assigned),
            ]);

            return $this->ok(['id' => $id]);
        });
    }

    public function doneFollowup(int $id): ResponseInterface
    {
        if ($denied = $this->guard('followups.create')) {
            return $denied;
        }

        if ($blocked = $this->teamRecord('followups', $id, ['assigned_to', 'user_id', 'assigned_user_id'])) {
            return $blocked;
        }

        return $this->run(function () use ($id) {
            $this->sites->completeFollowup($this->tenant(), $id);

            return $this->ok(['id' => $id, 'status' => 'COMPLETED']);
        });
    }

    public function rescheduleFollowup(int $id): ResponseInterface
    {
        if ($denied = $this->guard('followups.create')) {
            return $denied;
        }

        if ($blocked = $this->teamRecord('followups', $id, ['assigned_to', 'user_id', 'assigned_user_id'])) {
            return $blocked;
        }

        return $this->run(function () use ($id) {
            $input = $this->input();
            $this->sites->rescheduleFollowup($this->tenant(), $id, (string) ($input['callScheduledDateTime'] ?? $input['scheduledDateTime'] ?? ''));

            return $this->ok(['id' => $id, 'status' => 'SCHEDULED']);
        });
    }

    public function visits(): ResponseInterface
    {
        if ($denied = $this->guard('visits.view')) {
            return $denied;
        }

        return $this->pageList($this->teamRows($this->sites->visits($this->tenant()), ['staff_id']));
    }

    public function filterVisits(): ResponseInterface
    {
        if ($denied = $this->guard('visits.view')) {
            return $denied;
        }

        return $this->filterRows($this->teamRows($this->sites->visits($this->tenant()), ['staff_id']), 'visit');
    }

    public function saveVisit(): ResponseInterface
    {
        if ($denied = $this->guard('visits.create')) {
            return $denied;
        }

        return $this->run(function () {
            $input = $this->input();
            $leadId = (int) ($input['leadId'] ?? $input['lead_id'] ?? 0);

            if ($blocked = $this->leadGate($leadId)) {
                return $blocked;
            }

            $input['created_by'] = $this->user();
            $id = $this->sites->saveVisit($this->tenant(), $leadId, $input);
            $staffId = (int) ($input['assignedTo'] ?? $input['assigned_to'] ?? $input['staff_id'] ?? 0);
            $this->alert('visit.scheduled', [
                'subject_user_id' => $staffId,
                'subject_type'    => 'visit',
                'subject_id'      => $id,
                'lead'            => (string) ($this->sites->lead($this->tenant(), $leadId)['name'] ?? ''),
                'name'            => $this->personName($staffId),
            ]);

            return $this->ok(['id' => $id]);
        });
    }

    public function updateVisit(int $id): ResponseInterface
    {
        if ($denied = $this->guard('visits.attend')) {
            return $denied;
        }

        if ($blocked = $this->teamRecord('site_visits', $id, ['staff_id'])) {
            return $blocked;
        }

        return $this->run(function () use ($id) {
            $this->sites->attendance($this->tenant(), $id, $this->user(), $this->input());

            return $this->ok(['id' => $id]);
        });
    }

    public function doneVisit(int $id): ResponseInterface
    {
        if ($denied = $this->guard('visits.attend')) {
            return $denied;
        }

        if ($blocked = $this->teamRecord('site_visits', $id, ['staff_id'])) {
            return $blocked;
        }

        return $this->run(function () use ($id) {
            $this->sites->completeVisit($this->tenant(), $id);

            return $this->ok(['id' => $id, 'status' => 'COMPLETED']);
        });
    }

    public function rescheduleVisit(int $id): ResponseInterface
    {
        if ($denied = $this->guard('visits.create')) {
            return $denied;
        }

        if ($blocked = $this->teamRecord('site_visits', $id, ['staff_id'])) {
            return $blocked;
        }

        return $this->run(function () use ($id) {
            $input = $this->input();
            $this->sites->rescheduleVisit($this->tenant(), $id, (string) ($input['scheduledDateTime'] ?? ''));

            return $this->ok(['id' => $id, 'status' => 'SCHEDULED']);
        });
    }

    public function tasks(): ResponseInterface
    {
        if ($denied = $this->guard('followups.view')) {
            return $denied;
        }

        return $this->pageList($this->teamRows($this->sites->tasks($this->tenant()), ['assigned_to']));
    }

    public function filterTasks(): ResponseInterface
    {
        if ($denied = $this->guard('followups.view')) {
            return $denied;
        }

        return $this->filterRows($this->teamRows($this->sites->tasks($this->tenant()), ['assigned_to']), 'task');
    }

    public function saveTask(): ResponseInterface
    {
        if ($denied = $this->guard('followups.create')) {
            return $denied;
        }

        return $this->run(function () {
            $input = $this->input();
            $leadId = (int) ($input['leadId'] ?? $input['lead_id'] ?? 0);

            if ($blocked = $this->leadGate($leadId)) {
                return $blocked;
            }

            $id = $this->sites->saveTask($this->tenant(), $this->user(), $input);
            $assigned = (int) ($input['assignedTo'] ?? $input['assigned_to'] ?? $this->user());
            $this->alert('task.assigned', [
                'subject_user_id' => $assigned,
                'subject_type'    => 'task',
                'subject_id'      => $id,
                'task'            => (string) ($input['subject'] ?? ''),
                'name'            => $this->personName($assigned),
            ]);

            return $this->ok(['id' => $id]);
        });
    }

    public function doneTask(int $id): ResponseInterface
    {
        if ($denied = $this->guard('followups.create')) {
            return $denied;
        }

        if ($blocked = $this->teamRecord('lead_tasks', $id, ['assigned_to'])) {
            return $blocked;
        }

        return $this->run(function () use ($id) {
            $task = db_connect()->table('lead_tasks')->select('assigned_to, subject')->where('tenant_id', $this->tenant())->where('id', $id)->get()->getRowArray();
            $this->sites->completeTask($this->tenant(), $id);
            $assigned = (int) ($task['assigned_to'] ?? 0);
            $this->alert('task.completed', [
                'subject_user_id' => $assigned,
                'subject_type'    => 'task',
                'subject_id'      => $id,
                'task'            => (string) ($task['subject'] ?? ''),
                'name'            => $this->personName($assigned),
            ]);

            return $this->ok(['id' => $id, 'status' => 'COMPLETED']);
        });
    }

    public function rescheduleTask(int $id): ResponseInterface
    {
        if ($denied = $this->guard('followups.create')) {
            return $denied;
        }

        if ($blocked = $this->teamRecord('lead_tasks', $id, ['assigned_to'])) {
            return $blocked;
        }

        return $this->run(function () use ($id) {
            $input = $this->input();
            $this->sites->rescheduleTask($this->tenant(), $id, (string) ($input['scheduledDateTime'] ?? ''));

            return $this->ok(['id' => $id, 'status' => 'SCHEDULED']);
        });
    }

    public function attendanceLocations(): ResponseInterface
    {
        if ($denied = $this->guardAny(['attendance.view', 'attendance.mark', 'attendance.correct', 'projects.view'])) {
            return $denied;
        }

        return $this->ok($this->places()->locations($this->tenant(), $this->allows('attendance.correct')));
    }

    public function saveAttendanceLocation(?int $id = null): ResponseInterface
    {
        if ($denied = $this->guard('attendance.correct')) {
            return $denied;
        }

        return $this->run(function () use ($id) {
            return $this->ok(['id' => $this->places()->saveLocation($this->tenant(), $id, $this->input())]);
        });
    }

    public function attendancePlaces(): ResponseInterface
    {
        if ($denied = $this->guardAny(['attendance.view', 'attendance.mark', 'attendance.correct'])) {
            return $denied;
        }

        $requested = (int) $this->request->getGet('user_id');
        $userId = $this->user();

        if ($requested > 0 && $requested !== $userId) {
            if (! $this->allows('attendance.correct')) {
                return $this->fail('Your role does not include that access.', 403);
            }

            $userId = $requested;
        }

        $lat = $this->request->getGet('lat');
        $lng = $this->request->getGet('lng');

        return $this->ok($this->places()->places(
            $this->tenant(),
            $userId,
            is_string($lat) ? $lat : null,
            is_string($lng) ? $lng : null,
        ));
    }

    public function attendanceSessions(): ResponseInterface
    {
        if ($denied = $this->guard('attendance.view')) {
            return $denied;
        }

        $window = $this->attendanceWindow(false);

        if ($window instanceof ResponseInterface) {
            return $window;
        }

        return $this->ok($this->teamRows($this->places()->sessions($this->tenant(), $window[0], $window[1]), ['user_id']));
    }

    public function attendance(): ResponseInterface
    {
        if ($denied = $this->guard('attendance.view')) {
            return $denied;
        }

        $month = trim((string) $this->request->getGet('month'));

        return $this->ok($this->teamRows($this->estate->attendance($this->tenant(), $month !== '' ? $month : date('Y-m')), ['user_id']));
    }

    public function markAttendance(): ResponseInterface
    {
        $input = $this->input();
        $target = (int) ($input['user_id'] ?? 0);
        $correcting = $target > 0 && $target !== $this->user();
        $permission = $correcting ? 'attendance.correct' : 'attendance.mark';

        if ($denied = $this->guard($permission)) {
            return $denied;
        }

        return $this->run(function () use ($input, $target, $correcting) {
            $userId = $correcting ? $target : $this->user();
            $this->estate->markAttendance($this->tenant(), $userId, $input, $correcting, $this->user());
            $this->desk->audit($this->tenant(), $this->user(), $this->plan(), $correcting ? 'attendance.correct' : 'attendance.mark', 'user', $userId, (string) ($input['status'] ?? ''));
            $this->alert('attendance.marked', [
                'subject_user_id' => $userId,
                'subject_type'    => 'attendance',
                'subject_id'      => (int) date('Ymd'),
                'place'           => (string) ($input['location_name'] ?? $input['place'] ?? ''),
                'name'            => $this->personName($userId),
            ]);

            return $this->ok(['saved' => true]);
        });
    }

    public function incentives(): ResponseInterface
    {
        if ($denied = $this->guard('incentives.view')) {
            return $denied;
        }

        return $this->ok($this->teamRows($this->estate->incentives($this->tenant()), ['user_id']));
    }

    public function incentiveStatus(int $id): ResponseInterface
    {
        if ($denied = $this->guard('incentives.approve')) {
            return $denied;
        }

        return $this->run(function () use ($id) {
            $status = (string) ($this->input()['status'] ?? '');
            $this->estate->setIncentive($this->tenant(), $id, $status, $this->user());
            $this->desk->audit($this->tenant(), $this->user(), $this->plan(), 'incentive.update', 'incentive', $id, $status);

            return $this->ok(['id' => $id, 'status' => $status]);
        });
    }

    public function incentiveRule(): ResponseInterface
    {
        if ($denied = $this->guard('incentives.approve')) {
            return $denied;
        }

        $percent = (float) ($this->input()['percent'] ?? -1);

        if ($percent < 0 || $percent > 100) {
            return $this->fail('Use a percent from 0 to 100.');
        }

        $this->estate->saveSetting($this->tenant(), 'incentive_percent', (string) $percent);

        return $this->ok(['percent' => $percent]);
    }

    public function reportAttendance(): ResponseInterface
    {
        if ($denied = $this->guardAny(['reports.view', 'attendance.view'])) {
            return $denied;
        }

        $window = $this->attendanceWindow(true);

        if ($window instanceof ResponseInterface) {
            return $window;
        }

        return $this->ok($this->teamRows($this->places()->sessions($this->tenant(), $window[0], $window[1]), ['user_id']));
    }

    public function reports(): ResponseInterface
    {
        if ($denied = $this->guard('reports.view')) {
            return $denied;
        }

        $actor = $this->actor();
        $scope = $this->estate->reportScope($this->tenant(), $this->user(), (string) $actor['role_name'], (bool) $actor['is_owner']);
        $board = $this->estate->dashboard($this->tenant(), $scope);
        $board['scope'] = $scope;
        $allowed = $this->allowedProjectIds();

        if ($allowed !== null) {
            $names = [];

            foreach ($this->sites->projects($this->tenant()) as $project) {
                if (in_array((int) $project['id'], $allowed, true)) {
                    $names[(string) $project['name']] = true;
                }
            }

            $board['projects'] = array_values(array_filter($board['projects'] ?? [], static fn (array $row): bool => isset($names[(string) ($row['name'] ?? '')])));
        }

        return $this->ok($board);
    }

    public function exportExcel(): ResponseInterface
    {
        $pack = $this->reportPack();

        if ($pack instanceof ResponseInterface) {
            return $pack;
        }

        $this->desk->audit($this->tenant(), $this->user(), $this->plan(), 'export', 'file', null, 'reports.xls');

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.ms-excel')
            ->setHeader('Content-Disposition', 'attachment; filename="atrium-reports.xls"')
            ->setBody(ReportFile::spreadsheet(ReportFile::sheets($pack['board'])));
    }

    public function exportPdf(): ResponseInterface
    {
        $pack = $this->reportPack();

        if ($pack instanceof ResponseInterface) {
            return $pack;
        }

        $this->desk->audit($this->tenant(), $this->user(), $this->plan(), 'export', 'file', null, 'reports.pdf');

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="atrium-reports.pdf"')
            ->setBody(ReportFile::pdf('Atrium reports', (string) $pack['scope']['label'] . ' view', ReportFile::sections($pack['board'])));
    }

    public function activity(): ResponseInterface
    {
        if ($denied = $this->guard('team.view')) {
            return $denied;
        }

        return $this->ok($this->desk->auditLog($this->tenant()));
    }

    public function notifications(): ResponseInterface
    {
        return $this->ok($this->desk->notices($this->tenant(), $this->user()));
    }

    public function readNotifications(): ResponseInterface
    {
        $this->desk->markAllNotices($this->tenant(), $this->user());

        return $this->ok(['read' => true]);
    }

    public function readNotification(int $id): ResponseInterface
    {
        $this->desk->markNotice($this->tenant(), $this->user(), $id);
        $this->alerts()->markRead($this->tenant(), $this->user(), $id);

        return $this->ok(['id' => $id]);
    }

    public function registerDevice(): ResponseInterface
    {
        return $this->run(function () {
            $this->alerts()->registerDevice($this->tenant(), $this->user(), $this->input());

            return $this->ok(['saved' => true]);
        });
    }

    public function deactivateDevice(): ResponseInterface
    {
        $token = trim((string) ($this->input()['token'] ?? $this->input()['device_token'] ?? ''));
        $this->alerts()->deactivateDevice($this->tenant(), $this->user(), $token);

        return $this->ok(['saved' => true]);
    }

    public function notificationSummary(): ResponseInterface
    {
        if ($denied = $this->guard('notifications.manage')) {
            return $denied;
        }

        return $this->ok($this->alerts()->summary($this->tenant()));
    }

    public function notificationOptions(): ResponseInterface
    {
        if ($denied = $this->guard('notifications.manage')) {
            return $denied;
        }

        return $this->ok($this->alerts()->options($this->tenant()));
    }

    public function notificationRules(): ResponseInterface
    {
        if ($denied = $this->guard('notifications.manage')) {
            return $denied;
        }

        return $this->ok($this->alerts()->rules($this->tenant()));
    }

    public function saveNotificationRule(?int $id = null): ResponseInterface
    {
        if ($denied = $this->guard('notifications.manage')) {
            return $denied;
        }

        return $this->run(fn () => $this->ok(['id' => $this->alerts()->saveRule($this->tenant(), $this->user(), $id, $this->input())]));
    }

    public function deleteNotificationRule(int $id): ResponseInterface
    {
        if ($denied = $this->guard('notifications.manage')) {
            return $denied;
        }

        return $this->run(function () use ($id) {
            $this->alerts()->deleteRule($this->tenant(), $id);

            return $this->ok(['id' => $id, 'deleted' => true]);
        });
    }

    public function notificationHistory(): ResponseInterface
    {
        if ($denied = $this->guard('notifications.manage')) {
            return $denied;
        }

        return $this->ok($this->alerts()->history($this->tenant()));
    }

    public function notificationRecipients(int $id): ResponseInterface
    {
        if ($denied = $this->guard('notifications.manage')) {
            return $denied;
        }

        return $this->run(fn () => $this->ok($this->alerts()->recipients($this->tenant(), $id)));
    }

    public function sendNotification(): ResponseInterface
    {
        if ($denied = $this->guard('notifications.manage')) {
            return $denied;
        }

        return $this->run(function () {
            $input = $this->input();

            return $this->ok($this->alerts()->sendManual(
                $this->tenant(),
                $this->user(),
                (string) ($input['title'] ?? ''),
                (string) ($input['message'] ?? ''),
                is_array($input['recipients'] ?? null) ? $input['recipients'] : [],
                is_array($input['conditions'] ?? null) ? $input['conditions'] : [],
            ));
        });
    }

    public function messages(): ResponseInterface
    {
        if ($denied = $this->guard('leads.view')) {
            return $denied;
        }

        return $this->ok($this->estate->messages($this->tenant()));
    }

    public function templates(): ResponseInterface
    {
        if ($denied = $this->guard('whatsapp.manage')) {
            return $denied;
        }

        return $this->ok($this->estate->templates($this->tenant()));
    }

    public function saveTemplate(): ResponseInterface
    {
        if ($denied = $this->guard('whatsapp.manage')) {
            return $denied;
        }

        return $this->run(function () {
            $input = $this->input();

            if (! array_key_exists('active', $input)) {
                $input['active'] = 1;
            }

            $this->estate->saveTemplate($this->tenant(), isset($input['id']) ? (int) $input['id'] : null, $input);

            return $this->ok(['saved' => true]);
        });
    }

    public function sendTemplate(int $id): ResponseInterface
    {
        if ($denied = $this->guard('whatsapp.manage')) {
            return $denied;
        }

        return $this->run(function () use ($id) {
            $this->estate->queueMessage($this->tenant(), $this->user(), $id, (int) ($this->input()['template_id'] ?? 0));

            return $this->ok(['status' => 'failed', 'detail' => 'Stored on the lead. Not delivered because WhatsApp is not connected.']);
        });
    }

    public function roles(): ResponseInterface
    {
        if ($denied = $this->guard('team.view')) {
            return $denied;
        }

        return $this->ok($this->roles->listForTenant($this->tenant()));
    }

    public function createRole(): ResponseInterface
    {
        if ($denied = $this->guard('roles.manage')) {
            return $denied;
        }

        return $this->run(function () {
            $input = $this->input();
            $id = $this->roles->create($this->tenant(), $this->plan(), trim((string) ($input['name'] ?? $input['role'] ?? '')));
            $keys = $input['keys'] ?? $input['permissions'] ?? null;

            if (is_array($keys)) {
                $this->roles->allocate($this->tenant(), $id, $keys);
            }

            return $this->ok(['id' => $id]);
        });
    }

    public function role(int $id): ResponseInterface
    {
        if ($denied = $this->guard('team.view')) {
            return $denied;
        }

        $role = $this->roles->find($this->tenant(), $id);

        if ($role === null) {
            return $this->fail('That role is not in this workspace.', 404);
        }

        $role['keys'] = $this->roles->keysFor($id);

        return $this->ok($role);
    }

    public function deleteRole(int $id): ResponseInterface
    {
        if ($denied = $this->guard('roles.manage')) {
            return $denied;
        }

        return $this->run(function () use ($id) {
            $this->roles->delete($this->tenant(), $id);

            return $this->ok(['id' => $id, 'deleted' => true]);
        });
    }

    public function saveAccess(int $id): ResponseInterface
    {
        if ($denied = $this->guard('roles.manage')) {
            return $denied;
        }

        return $this->run(function () use ($id) {
            $input = $this->input();
            $keys = $input['keys'] ?? $input['permissions'] ?? [];
            $this->roles->allocate($this->tenant(), $id, is_array($keys) ? $keys : []);

            return $this->ok(['id' => $id]);
        });
    }

    public function accessCatalog(): ResponseInterface
    {
        if ($denied = $this->guard('roles.manage')) {
            return $denied;
        }

        return $this->ok(AccessCatalog::groups());
    }

    public function createUser(): ResponseInterface
    {
        if ($denied = $this->guard('team.invite')) {
            return $denied;
        }

        return $this->run(function () {
            $input = $this->input();
            $email = strtolower(trim((string) ($input['email'] ?? '')));
            $existing = $this->workspaces->userByEmail($email);

            if ($existing === null && strlen((string) ($input['password'] ?? '')) < 8) {
                $input['password'] = $this->roles->temporaryPassword();
            }

            $id = $this->roles->createMember($this->tenant(), $this->plan(), $input, (bool) $this->actor()['is_owner'], $this->user());
            $token = $this->roles->issuePasswordToken($id);
            $password = $existing === null ? (string) $input['password'] : '';

            return $this->ok([
                'id'                 => $id,
                'email'              => $email,
                'password'           => $password,
                'temporary_password' => $password,
                'setup_path'         => '/desk/?setup=' . $token,
                'login'              => 'POST /api/v1/auth/login with this email and password. No other restriction.',
            ]);
        });
    }

    public function setPassword(): ResponseInterface
    {
        return $this->run(function () {
            $input = $this->input();
            $this->roles->applyPasswordToken((string) ($input['token'] ?? ''), (string) ($input['password'] ?? ''));

            return $this->ok(['saved' => true]);
        });
    }

    public function changePassword(): ResponseInterface
    {
        return $this->run(function () {
            $input = $this->input();
            $this->roles->changePassword($this->user(), (string) ($input['current_password'] ?? ''), (string) ($input['password'] ?? ''));

            return $this->ok(['saved' => true]);
        });
    }

    public function invite(): ResponseInterface
    {
        if ($denied = $this->guard('team.invite')) {
            return $denied;
        }

        return $this->run(function () {
            $input = $this->input();
            $token = $this->roles->invite($this->tenant(), strtolower(trim((string) ($input['email'] ?? ''))), (int) ($input['role_id'] ?? 0), (bool) $this->actor()['is_owner']);

            return $this->ok(['invite_path' => '/join/' . $token]);
        });
    }

    public function lookups(): ResponseInterface
    {
        if ($denied = $this->guard('workspace.settings')) {
            return $denied;
        }

        $kind = trim((string) $this->request->getGet('kind'));

        return $this->ok($kind === '' ? $this->estate->allOptions($this->tenant()) : $this->estate->options($this->tenant(), $kind));
    }

    public function files(): ResponseInterface
    {
        if ($denied = $this->guard('files.view')) {
            return $denied;
        }

        return $this->ok($this->desk->filesFor($this->tenant(), trim((string) $this->request->getGet('type')), (int) $this->request->getGet('id')));
    }

    public function upload(): ResponseInterface
    {
        if ($denied = $this->guard('files.attach')) {
            return $denied;
        }

        return $this->run(function () {
            $file = $this->request->getFile('file');

            if ($file === null) {
                return $this->fail('Send the file as multipart field file.');
            }

            $id = $this->desk->saveFile($this->tenant(), $this->user(), $this->plan(), (string) $this->request->getPost('type'), (int) $this->request->getPost('subject_id'), $file);

            return $this->ok(['id' => $id]);
        });
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function pageList(array $rows): ResponseInterface
    {
        $sort = trim((string) $this->request->getGet('sort'));
        $dir = strtolower((string) $this->request->getGet('dir')) === 'desc' ? -1 : 1;

        if ($sort !== '' && $rows !== [] && array_key_exists($sort, $rows[0])) {
            usort($rows, static function (array $a, array $b) use ($sort, $dir): int {
                return $dir * ((string) ($a[$sort] ?? '') <=> (string) ($b[$sort] ?? ''));
            });
        }

        $per = (int) $this->request->getGet('per_page');

        if ($per < 1) {
            return $this->ok(array_values($rows));
        }

        $per = min(100, $per);
        $page = max(1, (int) $this->request->getGet('page'));
        $slice = array_slice(array_values($rows), ($page - 1) * $per, $per);

        return $this->response->setJSON([
            'ok'   => true,
            'data' => $slice,
            'meta' => ['page' => $page, 'per_page' => $per, 'total' => count($rows)],
        ]);
    }

    /**
     * @param list<string> $header
     * @param list<list<string|int|float|null>> $rows
     */
    private function csv(string $filename, array $header, array $rows): ResponseInterface
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $header);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $body = stream_get_contents($handle) ?: '';
        fclose($handle);
        $this->desk->audit($this->tenant(), $this->user(), $this->plan(), 'export', 'file', null, $filename);

        return $this->response->setHeader('Content-Type', 'text/csv')->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')->setBody($body);
    }

    /**
     * @return array{level: string, label: string, user_ids: ?list<int>, team_ids: ?list<int>}
     */
    /**
     * Null means every project. A list means the projects assigned to this person's teams.
     *
     * @return ?list<int>
     */
    private function allowedProjectIds(): ?array
    {
        if (($this->viewScope()['level'] ?? '') === 'company') {
            return null;
        }

        $teamIds = [];
        $scope = $this->viewScope();

        if (is_array($scope['team_ids'] ?? null)) {
            $teamIds = $scope['team_ids'];
        }

        $profile = db_connect()->table('staff_profiles')
            ->select('team_id')
            ->where('tenant_id', $this->tenant())
            ->where('user_id', $this->user())
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();
        $teamId = (int) ($profile['team_id'] ?? 0);

        if ($teamId > 0) {
            $teamIds[] = $teamId;
        }

        $memberships = db_connect()->table('team_memberships')
            ->select('team_id')
            ->where('tenant_id', $this->tenant())
            ->where('user_id', $this->user())
            ->where('to_on', null)
            ->get()
            ->getResultArray();

        foreach ($memberships as $membership) {
            $teamIds[] = (int) $membership['team_id'];
        }

        $teamIds = array_values(array_unique(array_filter($teamIds)));
        $projectIds = $this->estate->projectIdsForTeams($this->tenant(), $teamIds);

        return $projectIds === [] ? null : $projectIds;
    }

    private function projectAllowed(int $projectId): bool
    {
        $allowed = $this->allowedProjectIds();

        return $allowed === null || in_array($projectId, $allowed, true);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function onlyTeamProjects(array $rows, string $key): array
    {
        $allowed = $this->allowedProjectIds();

        if ($allowed === null) {
            return $rows;
        }

        $ids = array_flip($allowed);

        return array_values(array_filter($rows, static fn (array $row): bool => isset($ids[(int) ($row[$key] ?? 0)])));
    }

    private function viewScope(): array
    {
        $actor = $this->actor();

        return $this->estate->reportScope($this->tenant(), $this->user(), (string) $actor['role_name'], (bool) $actor['is_owner']);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function filterRows(array $rows, string $kind): ResponseInterface
    {
        $bag = $this->filterInput();
        $bounds = $this->filterBounds($bag);

        if ($bounds instanceof ResponseInterface) {
            return $bounds;
        }

        $needle = strtolower(trim((string) ($bag['q'] ?? '')));
        $stage = strtolower(trim((string) ($bag['stage'] ?? $bag['leadStage'] ?? $bag['lead_stage'] ?? '')));
        $source = strtolower(trim((string) ($bag['source'] ?? $bag['leadSource'] ?? $bag['lead_source'] ?? '')));
        $campaign = strtolower(trim((string) ($bag['campaign'] ?? $bag['campaignSource'] ?? $bag['campaign_source'] ?? '')));
        $project = strtolower(trim((string) ($bag['project'] ?? $bag['project_name'] ?? '')));
        $projectId = (int) ($bag['projectId'] ?? $bag['project_id'] ?? 0);
        $assignee = strtolower(trim((string) ($bag['assignee'] ?? '')));
        $assignedTo = (int) ($bag['assignedTo'] ?? $bag['assigned_to'] ?? 0);
        $status = strtoupper(trim((string) ($bag['status'] ?? '')));
        $matched = [];

        foreach ($rows as $row) {
            if ($stage !== '' && strtolower((string) ($row['lead_stage'] ?? '')) !== $stage) {
                continue;
            }

            if ($source !== '' && strtolower((string) ($row['lead_source'] ?? $row['source'] ?? '')) !== $source) {
                continue;
            }

            if ($campaign !== '' && strtolower((string) ($row['campaign_source'] ?? '')) !== $campaign) {
                continue;
            }

            if ($project !== '' && strtolower((string) ($row['project_name'] ?? '')) !== $project) {
                continue;
            }

            if ($projectId > 0 && (int) ($row['project_id'] ?? 0) !== $projectId) {
                continue;
            }

            if ($assignee !== '') {
                $names = [
                    strtolower(trim((string) ($row['assigned_name'] ?? ''))),
                    strtolower(trim((string) ($row['owner_name'] ?? ''))),
                    strtolower(trim((string) ($row['staff_name'] ?? ''))),
                ];

                if (! in_array($assignee, $names, true)) {
                    continue;
                }
            }

            if ($assignedTo > 0) {
                $ids = [(int) ($row['owner_id'] ?? 0), (int) ($row['assigned_to'] ?? 0), (int) ($row['staff_id'] ?? 0), (int) ($row['user_id'] ?? 0)];

                if (! in_array($assignedTo, $ids, true)) {
                    continue;
                }
            }

            if ($status !== '' && strtoupper((string) ($row['display_status'] ?? $row['status'] ?? '')) !== $status) {
                continue;
            }

            if ($bounds !== null) {
                $when = match ($kind) {
                    'followup' => (string) ($row['call_scheduled_at'] ?? $row['next_at'] ?? ''),
                    'visit'    => (string) ($row['scheduled_date_time'] ?? $row['scheduled_at'] ?? ''),
                    'task'     => (string) ($row['scheduled_at'] ?? ''),
                    default    => (string) ($row['created_at'] ?? ''),
                };
                $day = substr($when, 0, 10);

                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || $day < $bounds[0] || $day > $bounds[1]) {
                    continue;
                }
            }

            if ($needle !== '') {
                $blob = strtolower(implode(' ', array_filter($row, static fn ($value): bool => is_scalar($value))));

                if (! str_contains($blob, $needle)) {
                    continue;
                }
            }

            $matched[] = $row;
        }

        $total = count($matched);
        $per = (int) ($bag['per_page'] ?? 0);

        if ($per < 1) {
            return $this->response->setJSON(['ok' => true, 'data' => $matched, 'meta' => ['total' => $total]]);
        }

        $per = min(100, $per);
        $page = max(1, (int) ($bag['page'] ?? 1));

        return $this->response->setJSON([
            'ok'   => true,
            'data' => array_slice($matched, ($page - 1) * $per, $per),
            'meta' => ['page' => $page, 'per_page' => $per, 'total' => $total],
        ]);
    }

    /**
     * @param array<string, mixed> $bag
     * @return array{0: string, 1: string}|null|ResponseInterface
     */
    private function filterBounds(array $bag): array|ResponseInterface|null
    {
        $range = strtolower(trim((string) ($bag['range'] ?? 'all')));

        if ($range === '' || $range === 'all') {
            return null;
        }

        $today = date('Y-m-d');
        $stamp = static fn (string $day, int $shift): string => date('Y-m-d', strtotime($day . ' ' . $shift . ' days'));

        if ($range === 'today') {
            return [$today, $today];
        }

        if ($range === 'yesterday') {
            $day = $stamp($today, -1);

            return [$day, $day];
        }

        if ($range === 'this_week' || $range === 'last_week') {
            $mondayShift = -((int) date('N') - 1);

            if ($range === 'last_week') {
                $mondayShift -= 7;
            }

            return [$stamp($today, $mondayShift), $stamp($today, $mondayShift + 6)];
        }

        if ($range === 'this_month') {
            return [date('Y-m-01'), date('Y-m-t')];
        }

        if ($range === 'last_month') {
            return [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))];
        }

        if ($range === 'last_6_months') {
            return [date('Y-m-d', strtotime($today . ' -6 months')), $today];
        }

        if ($range === 'this_year') {
            return [date('Y-01-01'), date('Y-12-31')];
        }

        if ($range === 'custom') {
            $from = substr((string) ($bag['from'] ?? ''), 0, 10);
            $to = substr((string) ($bag['to'] ?? ''), 0, 10);

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                return $this->fail('Use a start date and an end date like 2026-10-01.', 422);
            }

            if ($to < $from) {
                return $this->fail('End date must be after the start date.', 422);
            }

            return [$from, $to];
        }

        return $this->fail('Use a range like today, this_month, or custom.', 422);
    }

    /**
     * @return array<string, mixed>
     */
    private function filterInput(): array
    {
        $bag = [];

        foreach ((array) $this->request->getGet() as $key => $value) {
            if (is_string($key) && ! in_array($key, ['companyId', 'company_id'], true)) {
                $bag[$key] = $value;
            }
        }

        foreach ($this->input() as $key => $value) {
            $bag[$key] = $value;
        }

        return $bag;
    }

    /**
     * A manager or team lead only sees people on their team. Company roles see everyone.
     *
     * @param list<array<string, mixed>> $rows
     * @param list<string> $keys
     * @return list<array<string, mixed>>
     */
    private function alerts(): PushService
    {
        return new PushService(db_connect());
    }

    /**
     * @param array<string, mixed> $context
     */
    private function alert(string $event, array $context): void
    {
        try {
            $context['actor'] = $context['actor'] ?? $this->personName($this->user());
            $this->alerts()->trigger($this->tenant(), $this->user(), $event, $context);
        } catch (Throwable $e) {
        }
    }

    private function alertLead(int $id): void
    {
        $lead = $this->sites->lead($this->tenant(), $id);

        if ($lead === null) {
            return;
        }

        $ownerId = (int) ($lead['owner_id'] ?? 0);
        $this->alert('lead.assigned', [
            'subject_user_id' => $ownerId,
            'subject_type'    => 'lead',
            'subject_id'      => $id,
            'lead'            => (string) ($lead['name'] ?? ''),
            'name'            => (string) ($lead['owner_name'] ?? ''),
        ]);
    }

    private function personName(int $userId): string
    {
        if ($userId < 1) {
            return '';
        }

        $row = db_connect()->table('users')->select('name')->where('id', $userId)->get()->getRowArray();

        return trim((string) ($row['name'] ?? ''));
    }

    private function leadGate(int $id): ?ResponseInterface
    {
        $lead = $this->sites->lead($this->tenant(), $id);

        if ($lead === null || $this->outsideTeam($lead, ['owner_id'])) {
            return $this->fail('That lead is not in this workspace.', 404);
        }

        return null;
    }

    private function outsideTeam(array $row, array $keys): bool
    {
        $scope = $this->viewScope();

        if (($scope['level'] ?? '') !== 'team') {
            return false;
        }

        $allowed = array_flip($scope['user_ids'] ?? []);

        foreach ($keys as $key) {
            $id = (int) ($row[$key] ?? 0);

            if ($id > 0 && isset($allowed[$id])) {
                return false;
            }
        }

        return true;
    }

    private function teamRecord(string $table, int $id, array $keys): ?ResponseInterface
    {
        $row = db_connect()->table($table)
            ->where('tenant_id', $this->tenant())
            ->where('id', $id)
            ->get()
            ->getRowArray();

        if ($row === null || $this->outsideTeam($row, $keys)) {
            return $this->fail('That record is not in this workspace.', 404);
        }

        return null;
    }

    private function teamRows(array $rows, array $keys): array
    {
        $scope = $this->viewScope();

        if (($scope['level'] ?? '') !== 'team') {
            return $rows;
        }

        $ids = $scope['user_ids'] ?? [];

        $allowed = array_flip($ids);

        return array_values(array_filter($rows, static function (array $row) use ($allowed, $keys): bool {
            foreach ($keys as $key) {
                $id = (int) ($row[$key] ?? 0);

                if ($id > 0 && isset($allowed[$id])) {
                    return true;
                }
            }

            return false;
        }));
    }

    private function guard(?string $permission): ?ResponseInterface
    {
        if ($permission === null || $this->allows($permission)) {
            return null;
        }

        return $this->fail('Your role does not include that access.', 403);
    }

    /**
     * @param list<string> $keys
     */
    private function guardAny(array $keys): ?ResponseInterface
    {
        foreach ($keys as $key) {
            if ($this->allows($key)) {
                return null;
            }
        }

        return $this->fail('Your role does not include that access.', 403);
    }

    private function places(): AttendanceLocationService
    {
        return new AttendanceLocationService(db_connect());
    }

    /**
     * @return array{0: string, 1: string}|ResponseInterface
     */
    private function attendanceWindow(bool $monthByDefault): array|ResponseInterface
    {
        $month = trim((string) $this->request->getGet('month'));
        $date = trim((string) $this->request->getGet('date'));

        if ($month !== '' && ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $this->fail('Use a month like 2026-10.', 422);
        }

        if ($date !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->fail('Use a date like 2026-10-30.', 422);
        }

        if ($monthByDefault && $month === '' && $date === '') {
            $month = date('Y-m');
        }

        return [$month, $date];
    }

    private function allows(string $key): bool
    {
        $actor = $this->actor();

        return (bool) $actor['is_owner'] || in_array($key, $actor['permissions'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function input(): array
    {
        $json = $this->request->getJSON(true);
        $input = is_array($json) ? $json : (array) $this->request->getPost();
        unset($input['companyId'], $input['company_id']);

        return $input;
    }

    /**
     * @return array<string, mixed>
     */
    private function actor(): array
    {
        $actor = ApiContext::get();

        if ($actor === null) {
            throw new RuntimeException('Not signed in.');
        }

        return $actor;
    }

    private function tenant(): int
    {
        return (int) $this->actor()['tenant_id'];
    }

    private function user(): int
    {
        return (int) $this->actor()['user_id'];
    }

    private function plan(): string
    {
        return (string) $this->actor()['plan_code'];
    }

    /**
     * @return array{scope: array<string, mixed>, board: array<string, mixed>}|ResponseInterface
     */
    private function reportPack(): array|ResponseInterface
    {
        if ($denied = $this->guard('reports.view')) {
            return $denied;
        }

        if ($denied = $this->guard('exports.download')) {
            return $denied;
        }

        if (! AccessCatalog::allows($this->plan(), 'reports')) {
            return $this->fail('Reports are not included in this plan.', 403);
        }

        $actor = $this->actor();
        $scope = $this->estate->reportScope($this->tenant(), $this->user(), (string) $actor['role_name'], (bool) $actor['is_owner']);

        return [
            'scope' => $scope,
            'board' => $this->estate->dashboard($this->tenant(), $scope),
        ];
    }

    private function ok(mixed $data, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['ok' => true, 'data' => $data]);
    }

    private function fail(string $message, int $status = 422): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['ok' => false, 'error' => $message]);
    }

    private function run(callable $work): ResponseInterface
    {
        try {
            $result = $work();

            return $result instanceof ResponseInterface ? $result : $this->ok($result);
        } catch (RuntimeException $e) {
            db_connect()->transRollback();

            return $this->fail($e->getMessage());
        } catch (Throwable $e) {
            db_connect()->transRollback();

            return $this->fail(ENVIRONMENT === 'development' ? $e->getMessage() : 'The request could not be completed.', 500);
        }
    }
}
