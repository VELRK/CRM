<?php

namespace App\Controllers;

class LeadsController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('leads.view');

        if ($denied !== null) {
            return $denied;
        }

        $editId  = (int) $this->request->getGet('edit');
        $editing = $editId > 0 ? $this->sites->lead($this->tenantId, $editId) : null;

        if ($editId > 0 && $editing === null) {
            return redirect()->to('/app/leads')->with('error', 'That lead is not in this workspace.');
        }

        $query     = trim((string) $this->request->getGet('q'));
        $leads     = $this->sites->leads($this->tenantId, $query);
        $timelines = $this->sites->timelines($this->tenantId);
        $leads     = array_map(static function (array $lead) use ($timelines): array {
            $lead['history'] = $timelines[(int) $lead['id']] ?? [];

            return $lead;
        }, $leads);

        $open = (string) (old('form') ?: '');

        if ($open === '' && ($editing !== null || $this->request->getGet('new') === '1')) {
            $open = 'lead';
        }

        return $this->page('sites/leads', [
            'active'    => 'leads',
            'query'     => $query,
            'leads'     => $leads,
            'editing'   => $editing,
            'projects'  => $this->sites->projects($this->tenantId),
            'plots'     => $this->sites->plots($this->tenantId),
            'staff'     => $this->sites->assignable($this->tenantId),
            'roster'    => $this->sites->staff($this->tenantId),
            'teams'     => $this->estate->teams($this->tenantId),
            'sources'   => $this->estate->options($this->tenantId, 'lead_source'),
            'statuses'  => $this->estate->options($this->tenantId, 'lead_status'),
            'open'      => $open,
        ]);
    }

    public function save(?int $id = null)
    {
        $denied = $this->denyUnless($id === null ? 'leads.create' : 'leads.update');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->sites->saveLead($this->tenantId, $id, [
                'name'            => $this->request->getPost('name'),
                'phone'           => $this->request->getPost('phone'),
                'phone_secondary' => $this->request->getPost('phone_secondary'),
                'email'           => $this->request->getPost('email'),
                'location'        => $this->request->getPost('location'),
                'budget'          => $this->request->getPost('budget'),
                'source'          => $this->request->getPost('source'),
                'project_id'      => $this->request->getPost('project_id'),
                'plot_id'         => $this->request->getPost('plot_id'),
                'team_id'         => $this->request->getPost('team_id'),
                'owner_id'        => $this->request->getPost('owner_id'),
                'status'          => $this->request->getPost('status'),
                'notes'           => $this->request->getPost('notes'),
                'remarks'         => $this->request->getPost('remarks'),
                'by_user_id'      => $this->userId,
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/leads')->with('message', $id === null ? 'Lead added.' : 'Lead saved.');
    }

    public function delete(int $id)
    {
        $denied = $this->denyUnless('leads.delete');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->sites->deleteLead($this->tenantId, $id);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/leads')->with('message', 'Lead removed.');
    }

    public function transfer(int $id)
    {
        $denied = $this->denyUnless('leads.transfer');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->sites->transfer(
                $this->tenantId,
                $id,
                (int) $this->request->getPost('to_user_id'),
                $this->userId,
                (string) $this->request->getPost('note'),
            );
            $this->desk->audit($this->tenantId, $this->userId, (string) session()->get('plan_code'), 'leads.transfer', 'lead', $id, 'Lead transferred');
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/leads')->with('message', 'Lead transferred.');
    }

    public function followup(int $id)
    {
        $denied = $this->denyUnless('followups.create');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->sites->saveFollowup($this->tenantId, $this->userId, $id, [
                'body'             => $this->request->getPost('body'),
                'next_at'          => $this->request->getPost('next_at'),
                'followup_type'    => $this->request->getPost('followup_type'),
                'status'           => $this->request->getPost('status'),
                'outcome'          => $this->request->getPost('outcome'),
                'assigned_user_id' => $this->request->getPost('assigned_user_id'),
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/leads')->with('message', 'Follow-up logged.');
    }

    public function visit(int $id)
    {
        $denied = $this->denyUnless('visits.create');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->sites->saveVisit($this->tenantId, $id, [
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

        return redirect()->to('/app/leads')->with('message', 'Site visit scheduled.');
    }
}
