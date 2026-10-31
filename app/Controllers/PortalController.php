<?php

namespace App\Controllers;

use App\Libraries\AccessCatalog;
use App\Libraries\BookService;
use App\Libraries\DeskService;
use App\Libraries\EstateService;
use App\Libraries\RoleService;
use App\Libraries\SiteService;
use App\Libraries\WorkspaceService;

abstract class PortalController extends BaseController
{
    protected int $userId;
    protected int $tenantId;
    protected int $roleId;
    protected bool $isOwner = false;

    /** @var list<string> */
    protected array $permissions = [];

    protected WorkspaceService $workspaces;
    protected RoleService $roles;
    protected BookService $book;
    protected DeskService $desk;
    protected SiteService $sites;
    protected EstateService $estate;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger,
    ): void {
        parent::initController($request, $response, $logger);

        $db = db_connect();
        $this->workspaces = new WorkspaceService($db);
        $this->roles      = new RoleService($db, $this->workspaces);
        $this->book       = new BookService($db);
        $this->desk       = new DeskService($db);
        $this->sites      = new SiteService($db);
        $this->estate     = new EstateService($db);

        $this->userId   = (int) session()->get('user_id');
        $this->tenantId = (int) session()->get('tenant_id');

        $member = $this->workspaces->membership($this->userId, $this->tenantId);

        if ($member === null) {
            session()->destroy();
            redirect()->to('/login')->send();
            exit;
        }

        $this->roleId      = (int) $member['role_id'];
        $this->isOwner     = (int) $member['is_owner'] === 1;
        $this->permissions = $this->workspaces->permissions($this->roleId);

        if (! $this->isOwner && $db->tableExists('staff_profiles')) {
            $profile = $db->table('staff_profiles')
                ->select('status')
                ->where('tenant_id', $this->tenantId)
                ->where('user_id', $this->userId)
                ->where('deleted_at', null)
                ->get()
                ->getRowArray();

            if (in_array((string) ($profile['status'] ?? ''), ['inactive', 'relieved'], true)) {
                session()->destroy();
                redirect()->to('/login?stopped=1')->send();
                exit;
            }
        }

        $this->desk->sweepReminders($this->tenantId, $this->userId);

        session()->set([
            'role_id'        => $this->roleId,
            'user_name'      => $member['user_name'],
            'workspace_name' => $member['workspace_name'],
            'plan_code'      => $member['plan_code'],
            'role_name'      => $member['role_name'],
        ]);
    }

    protected function can(string $key): bool
    {
        return $this->isOwner || in_array($key, $this->permissions, true);
    }

    protected function denyUnless(string $key): ?\CodeIgniter\HTTP\RedirectResponse
    {
        if ($this->can($key)) {
            return null;
        }

        return redirect()->to('/app')->with('error', 'Your role does not include that access.');
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function page(string $view, array $data = []): string
    {
        $labels = $this->estate->designationLabels($this->tenantId);
        $byUser = [];

        foreach ($this->sites->staff($this->tenantId) as $person) {
            $code = (string) ($person['designation'] ?? '');
            $byUser[(int) $person['user_id']] = $code === '' ? '' : (string) ($labels[$code] ?? $code);
        }

        return view($view, array_merge($data, [
            'can'                => $this->isOwner ? AccessCatalog::keys() : $this->permissions,
            'isOwner'            => $this->isOwner,
            'active'             => $data['active'] ?? '',
            'unread'             => $this->desk->unreadCount($this->tenantId, $this->userId),
            'planCode'           => (string) session()->get('plan_code'),
            'designations'       => $this->estate->options($this->tenantId, 'designation'),
            'designationByUser'  => $byUser,
        ]));
    }

    protected function planLocked(string $feature, string $module, string $active): ?string
    {
        if (AccessCatalog::allows((string) session()->get('plan_code'), $feature)) {
            return null;
        }

        return $this->page('billing/locked', [
            'active' => $active,
            'module' => $module,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function deskBundle(string $type, int $id): array
    {
        if ($id < 1) {
            return [
                'recordType'  => $type,
                'recordId'    => 0,
                'activities'  => [],
                'recordFiles' => [],
                'recordTasks' => [],
            ];
        }

        return [
            'recordType'  => $type,
            'recordId'    => $id,
            'activities'  => $this->desk->activitiesFor($this->tenantId, $type, $id),
            'recordFiles' => $this->can('files.view') ? $this->desk->filesFor($this->tenantId, $type, $id) : [],
            'recordTasks' => $this->can('tasks.view') ? $this->desk->tasksFor($this->tenantId, $type, $id) : [],
        ];
    }
}
