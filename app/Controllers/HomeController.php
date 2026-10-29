<?php

namespace App\Controllers;

class HomeController extends PortalController
{
    public function index()
    {
        $scope = $this->estate->reportScope($this->tenantId, $this->userId, (string) session()->get('role_name'), $this->isOwner);

        return $this->page('home/index', [
            'active' => 'home',
            'scope'  => $scope,
            'board'  => $this->estate->dashboard($this->tenantId, $scope),
        ]);
    }

    public function soon(string $which)
    {
        $pages = [
            'work' => ['title' => 'Work', 'icon' => 'square-check', 'permission' => 'tasks.view', 'active' => 'work'],
        ];

        if (! isset($pages[$which])) {
            return redirect()->to('/app');
        }

        $page = $pages[$which];
        $denied = $this->denyUnless($page['permission']);

        if ($denied !== null) {
            return $denied;
        }

        return $this->page('home/soon', $page);
    }
}
