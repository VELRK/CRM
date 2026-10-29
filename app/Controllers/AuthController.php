<?php

namespace App\Controllers;

use App\Libraries\RoleService;
use App\Libraries\WorkspaceService;

class AuthController extends BaseController
{
    private WorkspaceService $workspaces;
    private RoleService $roles;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger,
    ): void {
        parent::initController($request, $response, $logger);

        $db = db_connect();
        $this->workspaces = new WorkspaceService($db);
        $this->roles      = new RoleService($db, $this->workspaces);
    }

    public function home()
    {
        return redirect()->to('/desk/');
    }

    public function loginForm()
    {
        if (session()->get('user_id')) {
            return redirect()->to('/app');
        }

        return view('auth/login');
    }

    public function login()
    {
        $email    = strtolower(trim((string) $this->request->getPost('email')));
        $password = (string) $this->request->getPost('password');
        $user     = $this->workspaces->userByEmail($email);

        if ($user === null || ! password_verify($password, $user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'Email or password is wrong.');
        }

        $member = $this->workspaces->membership((int) $user['id']);

        if ($member === null) {
            return redirect()->back()->with('error', 'This account is not in a workspace yet.');
        }

        $this->startSession((int) $user['id'], (int) $member['tenant_id']);

        return redirect()->to('/app');
    }

    public function registerForm()
    {
        if (session()->get('user_id')) {
            return redirect()->to('/app');
        }

        return view('auth/register');
    }

    public function register()
    {
        $name      = trim((string) $this->request->getPost('name'));
        $email     = strtolower(trim((string) $this->request->getPost('email')));
        $password  = (string) $this->request->getPost('password');
        $workspace = trim((string) $this->request->getPost('workspace'));

        if ($name === '' || $workspace === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->withInput()->with('error', 'Name, workspace, and a valid email are required.');
        }

        if (strlen($password) < 8) {
            return redirect()->back()->withInput()->with('error', 'Use a password of at least 8 characters.');
        }

        if ($this->workspaces->userByEmail($email) !== null) {
            return redirect()->back()->withInput()->with('error', 'That email already has an account. Sign in instead.');
        }

        try {
            $created = $this->workspaces->register($name, $email, $password, $workspace);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'Could not create the workspace.');
        }

        $this->startSession($created['user_id'], $created['tenant_id']);

        return redirect()->to('/app/roles')->with('message', 'Workspace created. Owner, Admin, Manager, Member, and Viewer are ready. Add a role when you need a different set of access.');
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to('/login');
    }

    public function joinForm(string $token)
    {
        return view('auth/join', ['token' => $token]);
    }

    public function join(string $token)
    {
        $name     = trim((string) $this->request->getPost('name'));
        $password = (string) $this->request->getPost('password');

        if ($name === '' || strlen($password) < 8) {
            return redirect()->back()->with('error', 'Name and a password of at least 8 characters are required.');
        }

        try {
            $joined = $this->roles->acceptInvite($token, $name, $password);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $this->startSession($joined['user_id'], $joined['tenant_id']);

        return redirect()->to('/app');
    }

    private function startSession(int $userId, int $tenantId): void
    {
        session()->regenerate(true);
        session()->set([
            'user_id'   => $userId,
            'tenant_id' => $tenantId,
        ]);
    }
}
