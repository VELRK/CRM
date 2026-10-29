<?php

namespace App\Controllers;

class ProjectsController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('projects.view');

        if ($denied !== null) {
            return $denied;
        }

        $editId  = (int) $this->request->getGet('edit');
        $editing = $editId > 0 ? $this->sites->project($this->tenantId, $editId) : null;

        if ($editId > 0 && $editing === null) {
            return redirect()->to('/app/projects')->with('error', 'That project is not in this workspace.');
        }

        return $this->page('sites/projects', [
            'active'   => 'projects',
            'projects' => $this->sites->projects($this->tenantId),
            'editing'  => $editing,
            'open'     => $editing !== null || $this->request->getGet('new') === '1' || old('form') === 'project',
        ]);
    }

    public function save(?int $id = null)
    {
        $denied = $this->denyUnless($id === null ? 'projects.create' : 'projects.update');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->sites->saveProject($this->tenantId, $id, [
                'name'         => $this->request->getPost('name'),
                'location'     => $this->request->getPost('location'),
                'status'       => $this->request->getPost('status'),
                'notes'        => $this->request->getPost('notes'),
                'project_type' => $this->request->getPost('project_type'),
                'total_plots'  => $this->request->getPost('total_plots'),
                'launch_on'    => $this->request->getPost('launch_on'),
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/projects')->with('message', $id === null ? 'Project added.' : 'Project saved.');
    }

    public function delete(int $id)
    {
        $denied = $this->denyUnless('projects.delete');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->sites->deleteProject($this->tenantId, $id);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/projects')->with('message', 'Project removed.');
    }
}
