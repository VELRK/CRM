<?php

namespace App\Controllers;

class FollowupsController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('followups.view');

        if ($denied !== null) {
            return $denied;
        }

        return $this->page('sites/followups', [
            'active'    => 'followups',
            'followups' => $this->sites->followups($this->tenantId),
            'leads'     => $this->sites->leads($this->tenantId),
            'open'      => old('form') === 'followup' || $this->request->getGet('new') === '1',
        ]);
    }

    public function create()
    {
        $denied = $this->denyUnless('followups.create');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->sites->saveFollowup($this->tenantId, $this->userId, (int) $this->request->getPost('lead_id'), [
                'body'             => $this->request->getPost('body'),
                'next_at'          => $this->request->getPost('next_at'),
                'followup_type'    => $this->request->getPost('followup_type'),
                'status'           => $this->request->getPost('status'),
                'outcome'          => $this->request->getPost('outcome'),
                'assigned_user_id' => $this->userId,
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/followups')->with('message', 'Follow-up logged.');
    }

    public function done(int $id)
    {
        $denied = $this->denyUnless('followups.create');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->sites->completeFollowup($this->tenantId, $id);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('message', 'Follow-up marked done.');
    }
}
