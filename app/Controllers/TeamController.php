<?php

namespace App\Controllers;

class TeamController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('team.view');

        if ($denied !== null) {
            return $denied;
        }

        return $this->page('team/index', [
            'active'  => 'team',
            'members' => $this->roles->members($this->tenantId),
            'roles'   => $this->roles->listForTenant($this->tenantId),
            'invite'  => session()->getFlashdata('invite_url'),
        ]);
    }

    public function assign(int $memberId)
    {
        $denied = $this->denyUnless('roles.manage');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->roles->assign(
                $this->tenantId,
                $memberId,
                (int) $this->request->getPost('role_id'),
                $this->isOwner,
            );
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/team')->with('message', 'Role updated.');
    }

    public function invite()
    {
        $denied = $this->denyUnless('team.invite');

        if ($denied !== null) {
            return $denied;
        }

        $email = strtolower(trim((string) $this->request->getPost('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->withInput()->with('error', 'Enter a valid email.');
        }

        $plan    = (string) session()->get('plan_code');
        $cap     = \App\Libraries\AccessCatalog::USER_CAPS[$plan] ?? 3;
        $members = db_connect()->table('tenant_users')->where('tenant_id', $this->tenantId)->countAllResults();
        $pending = db_connect()->table('invites')->where('tenant_id', $this->tenantId)->where('accepted_at', null)->where('expires_at >', date('Y-m-d H:i:s'))->countAllResults();

        if ($members + $pending >= $cap) {
            return redirect()->back()->withInput()->with('error', 'This plan allows ' . $cap . ' users.');
        }

        try {
            $token = $this->roles->invite(
                $this->tenantId,
                $email,
                (int) $this->request->getPost('role_id'),
                $this->isOwner,
            );
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $url = site_url('join/' . $token);

        return redirect()->to('/app/team')->with('message', 'Invite created.')->with('invite_url', $url);
    }
}
