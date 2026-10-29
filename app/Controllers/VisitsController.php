<?php

namespace App\Controllers;

class VisitsController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('visits.view');

        if ($denied !== null) {
            return $denied;
        }

        return $this->page('sites/visits', [
            'active'   => 'visits',
            'visits'   => $this->sites->visits($this->tenantId),
            'leads'    => $this->sites->leads($this->tenantId),
            'staff'    => $this->sites->assignable($this->tenantId),
            'projects' => $this->sites->projects($this->tenantId),
            'plots'    => $this->sites->plots($this->tenantId),
            'open'     => old('form') === 'visit' || $this->request->getGet('new') === '1',
            'markOpen' => old('form') === 'attendance',
        ]);
    }

    public function create()
    {
        $denied = $this->denyUnless('visits.create');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->sites->saveVisit($this->tenantId, (int) $this->request->getPost('lead_id'), [
                'staff_id'     => $this->request->getPost('staff_id'),
                'scheduled_at' => $this->request->getPost('scheduled_at'),
                'project_id'   => $this->request->getPost('project_id'),
                'plot_id'      => $this->request->getPost('plot_id'),
                'note'         => $this->request->getPost('note'),
                'pickup'       => $this->request->getPost('pickup'),
                'created_by'   => $this->userId,
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/visits')->with('message', 'Site visit scheduled.');
    }

    public function attendance(int $id)
    {
        $denied = $this->denyUnless('visits.attend');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->sites->attendance($this->tenantId, $id, $this->userId, [
                'status'        => $this->request->getPost('status'),
                'lead_attended' => $this->request->getPost('lead_attended'),
                'staff_present' => $this->request->getPost('staff_present'),
                'note'          => $this->request->getPost('note'),
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/visits')->with('message', 'Attendance recorded.');
    }
}
