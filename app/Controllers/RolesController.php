<?php

namespace App\Controllers;

use App\Libraries\AccessCatalog;

class RolesController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('team.view');

        if ($denied !== null) {
            return $denied;
        }

        $roles      = $this->roles->listForTenant($this->tenantId);
        $accessId   = (int) $this->request->getGet('access');
        $selected   = [];
        $accessRole = null;

        foreach ($roles as &$role) {
            $keys = $this->workspaces->permissions((int) $role['id']);
            $role['keys'] = implode(',', $keys);

            if ((int) $role['id'] === $accessId && (int) $role['is_owner'] !== 1) {
                $selected   = $keys;
                $accessRole = $role;
            }
        }
        unset($role);

        return $this->page('roles/index', [
            'active'    => 'roles',
            'roles'     => $roles,
            'cap'       => AccessCatalog::PLAN_CAPS[session()->get('plan_code')] ?? 8,
            'groups'    => AccessCatalog::groups(),
            'selected'  => $selected,
            'accessId'  => $accessRole === null ? 0 : $accessId,
            'openNew'   => $this->request->getGet('new') === '1' || old('name') !== null,
        ]);
    }

    public function new()
    {
        $denied = $this->denyUnless('roles.manage');

        if ($denied !== null) {
            return $denied;
        }

        return redirect()->to('/app/roles?new=1');
    }

    public function create()
    {
        $denied = $this->denyUnless('roles.manage');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $id = $this->roles->create(
                $this->tenantId,
                (string) session()->get('plan_code'),
                (string) $this->request->getPost('name'),
            );
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/roles?access=' . $id)->with('message', 'Role added. Allocate the access it should have.');
    }

    public function access(int $id)
    {
        $denied = $this->denyUnless('team.view');

        if ($denied !== null) {
            return $denied;
        }

        $role = $this->roles->find($this->tenantId, $id);

        if ($role === null) {
            return redirect()->to('/app/roles')->with('error', 'That role is not in this workspace.');
        }

        return redirect()->to('/app/roles?access=' . $id);
    }

    public function saveAccess(int $id)
    {
        $denied = $this->denyUnless('roles.manage');

        if ($denied !== null) {
            return $denied;
        }

        $keys = $this->request->getPost('permissions');
        $keys = is_array($keys) ? $keys : [];

        try {
            $this->roles->allocate($this->tenantId, $id, $keys);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/roles')->with('message', 'Access saved. People on this role get it on their next page.');
    }

    public function delete(int $id)
    {
        $denied = $this->denyUnless('roles.manage');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->roles->delete($this->tenantId, $id);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/roles')->with('message', 'Role removed.');
    }
}
