<?php

namespace App\Controllers;

class ActivitiesController extends PortalController
{
    public function create()
    {
        $denied = $this->denyUnless('activities.create');

        if ($denied !== null) {
            return $denied;
        }

        $type = (string) $this->request->getPost('subject_type');
        $id   = (int) $this->request->getPost('subject_id');
        $link = match ($type) {
            'contact' => 'contact_id',
            'company' => 'company_id',
            'deal'    => 'deal_id',
            default   => '',
        };

        if ($link === '') {
            return redirect()->back()->with('error', 'Choose a record to log against.');
        }

        try {
            $this->desk->saveActivity($this->tenantId, $this->userId, [
                'kind'        => (string) $this->request->getPost('kind'),
                'body'        => (string) $this->request->getPost('body'),
                'happened_at' => (string) $this->request->getPost('happened_at'),
                $link         => $id,
            ]);
            $this->desk->audit($this->tenantId, $this->userId, (string) session()->get('plan_code'), 'activity.create', $type, $id, (string) $this->request->getPost('kind'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('message', 'Logged.');
    }
}
