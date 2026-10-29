<?php

namespace App\Controllers;

class StaffController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('staff.view');

        if ($denied !== null) {
            return $denied;
        }

        return $this->page('sites/staff', [
            'active' => 'staff',
            'staff'  => $this->sites->staff($this->tenantId),
            'teams'  => $this->estate->teams($this->tenantId),
            'open'   => old('form') === 'staff',
        ]);
    }

    public function save(int $userId)
    {
        $denied = $this->denyUnless('staff.update');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->sites->saveStaff($this->tenantId, $userId, [
                'designation'           => $this->request->getPost('designation'),
                'phone'                 => $this->request->getPost('phone'),
                'status'                => $this->request->getPost('status'),
                'employee_code'         => $this->request->getPost('employee_code'),
                'joining_on'            => $this->request->getPost('joining_on'),
                'address'               => $this->request->getPost('address'),
                'relieved_on'           => $this->request->getPost('relieved_on'),
                'team_id'               => $this->request->getPost('team_id'),
                'attendance_applicable' => $this->request->getPost('attendance_applicable'),
                'lead_eligible'         => $this->request->getPost('lead_eligible'),
                'incentive_eligible'    => $this->request->getPost('incentive_eligible'),
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/staff')->with('message', 'Staff profile saved.');
    }
}
