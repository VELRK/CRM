<?php

namespace App\Controllers;

class EstateController extends PortalController
{
    public function designations()
    {
        $denied = $this->denyUnless('workspace.settings');

        if ($denied !== null) {
            return $denied;
        }

        return $this->page('sites/designations', [
            'active' => 'designations',
            'items'  => $this->estate->designations($this->tenantId),
            'open'   => old('form') === 'designation' || $this->request->getGet('new') === '1',
        ]);
    }

    public function saveDesignation(?int $id = null)
    {
        $denied = $this->denyUnless('workspace.settings');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->estate->saveOption($this->tenantId, $id, [
                'kind'       => 'designation',
                'code'       => $this->request->getPost('code'),
                'label'      => $this->request->getPost('label'),
                'sort_order' => $this->request->getPost('sort_order'),
                'active'     => $this->request->getPost('active'),
            ]);
        } catch (\Throwable $e) {
            $message = str_contains($e->getMessage(), 'Duplicate') ? 'That designation code is already in use.' : $e->getMessage();

            return redirect()->back()->withInput()->with('error', $message);
        }

        return redirect()->to('/app/designations')->with('message', 'Designation saved.');
    }

    public function deleteDesignation(int $id)
    {
        $denied = $this->denyUnless('workspace.settings');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->estate->deleteDesignation($this->tenantId, $id);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/designations')->with('message', 'Designation removed. Staff who had it keep the record.');
    }

    public function teams()
    {
        $denied = $this->denyUnless('teams.view');

        if ($denied !== null) {
            return $denied;
        }

        $teams = $this->estate->teams($this->tenantId);

        foreach ($teams as &$team) {
            $team['member_ids'] = implode(',', $this->estate->memberIds($this->tenantId, (int) $team['id']));
        }
        unset($team);

        return $this->page('sites/teams', [
            'active' => 'teams',
            'teams'  => $teams,
            'staff'  => $this->sites->staff($this->tenantId),
            'open'   => old('form') === 'team' || $this->request->getGet('new') === '1',
        ]);
    }

    public function saveTeam(?int $id = null)
    {
        $denied = $this->denyUnless('teams.manage');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->estate->saveTeam($this->tenantId, $id, [
                'name'         => $this->request->getPost('name'),
                'head_user_id' => $this->request->getPost('head_user_id'),
                'member_ids'   => $this->request->getPost('member_ids'),
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/teams')->with('message', 'Team saved.');
    }

    public function attendance()
    {
        $denied = $this->denyUnless('attendance.view');

        if ($denied !== null) {
            return $denied;
        }

        $year = (string) $this->request->getGet('year');
        $monthNum = (string) $this->request->getGet('month');

        if (preg_match('/^\d{4}-\d{2}$/', $monthNum)) {
            [$year, $monthNum] = explode('-', $monthNum);
        }

        if (! preg_match('/^\d{4}$/', $year)) {
            $year = date('Y');
        }

        $monthNum = (int) $monthNum;

        if ($monthNum < 1 || $monthNum > 12) {
            $monthNum = (int) date('n');
        }

        $month = sprintf('%04d-%02d', (int) $year, $monthNum);
        $rows = $this->estate->attendance($this->tenantId, $month);
        $today = date('Y-m-d');
        $todaySource = substr($today, 0, 7) === $month ? $rows : $this->estate->attendance($this->tenantId, substr($today, 0, 7));
        $todayByUser = [];

        foreach ($todaySource as $row) {
            if ((string) $row['work_date'] === $today) {
                $todayByUser[(int) $row['user_id']] = $row;
            }
        }

        return $this->page('sites/attendance', [
            'active'      => 'attendance',
            'year'        => (int) $year,
            'monthNum'    => $monthNum,
            'month'       => $month,
            'rows'        => $rows,
            'staff'       => $this->sites->staff($this->tenantId),
            'todayByUser' => $todayByUser,
            'open'        => old('form') === 'attendance',
        ]);
    }

    public function markAttendance()
    {
        $correcting = $this->request->getPost('correct') === '1';
        $denied = $this->denyUnless($correcting ? 'attendance.correct' : 'attendance.mark');

        if ($denied !== null) {
            return $denied;
        }

        $userId = (int) $this->request->getPost('user_id');

        if (! $correcting) {
            $userId = $this->userId;
        }

        try {
            $this->estate->markAttendance($this->tenantId, $userId, [
                'work_date' => $this->request->getPost('work_date'),
                'status'    => $this->request->getPost('status'),
                'note'      => $this->request->getPost('note'),
                'action'    => $this->request->getPost('action'),
                'lat'       => $this->request->getPost('lat'),
                'lng'       => $this->request->getPost('lng'),
            ], $correcting, $this->userId);
            $this->desk->audit($this->tenantId, $this->userId, (string) session()->get('plan_code'), 'attendance.mark', 'user', $userId, (string) $this->request->getPost('status'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/attendance')->with('message', 'Attendance saved.');
    }

    public function incentives()
    {
        $denied = $this->denyUnless('incentives.view');

        if ($denied !== null) {
            return $denied;
        }

        return $this->page('sites/incentives', [
            'active'     => 'incentives',
            'incentives' => $this->estate->incentives($this->tenantId),
            'percent'    => $this->estate->setting($this->tenantId, 'incentive_percent') ?? '2',
        ]);
    }

    public function incentiveStatus(int $id)
    {
        $denied = $this->denyUnless('incentives.approve');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->estate->setIncentive($this->tenantId, $id, (string) $this->request->getPost('status'), $this->userId);
            $this->desk->audit($this->tenantId, $this->userId, (string) session()->get('plan_code'), 'incentive.status', 'incentive', $id, (string) $this->request->getPost('status'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/incentives')->with('message', 'Incentive updated.');
    }

    public function saveIncentiveRule()
    {
        $denied = $this->denyUnless('incentives.approve');

        if ($denied !== null) {
            return $denied;
        }

        $percent = trim((string) $this->request->getPost('incentive_percent'));

        if (! is_numeric($percent) || (float) $percent < 0 || (float) $percent > 100) {
            return redirect()->back()->with('error', 'Enter a percentage from 0 to 100.');
        }

        $this->estate->saveSetting($this->tenantId, 'incentive_percent', $percent);

        return redirect()->to('/app/incentives')->with('message', 'Incentive percentage saved. It applies to the next sale.');
    }

    public function lists()
    {
        $denied = $this->denyUnless('workspace.settings');

        if ($denied !== null) {
            return $denied;
        }

        return $this->page('sites/lists', [
            'active' => 'lists',
            'items'  => $this->estate->allOptions($this->tenantId),
            'open'   => old('form') === 'lookup' || $this->request->getGet('new') === '1',
        ]);
    }

    public function saveList(?int $id = null)
    {
        $denied = $this->denyUnless('workspace.settings');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->estate->saveOption($this->tenantId, $id, [
                'kind'       => $this->request->getPost('kind'),
                'code'       => $this->request->getPost('code'),
                'label'      => $this->request->getPost('label'),
                'sort_order' => $this->request->getPost('sort_order'),
                'active'     => $this->request->getPost('active'),
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/lists')->with('message', 'List item saved.');
    }

    public function whatsapp()
    {
        $denied = $this->denyUnless('whatsapp.manage');

        if ($denied !== null) {
            return $denied;
        }

        return $this->page('sites/whatsapp', [
            'active'    => 'whatsapp',
            'templates' => $this->estate->templates($this->tenantId),
            'phone'     => $this->estate->setting($this->tenantId, 'whatsapp_phone') ?? '',
            'open'      => old('form') === 'template',
        ]);
    }

    public function saveWhatsapp()
    {
        $denied = $this->denyUnless('whatsapp.manage');

        if ($denied !== null) {
            return $denied;
        }

        $this->estate->saveSetting($this->tenantId, 'whatsapp_phone', trim((string) $this->request->getPost('whatsapp_phone')));
        $token = trim((string) $this->request->getPost('whatsapp_token'));

        if ($token !== '') {
            $this->estate->saveSetting($this->tenantId, 'whatsapp_token', $token);
        }

        return redirect()->to('/app/whatsapp')->with('message', 'WhatsApp settings saved. Sending stays off until a provider is connected.');
    }

    public function saveTemplate(?int $id = null)
    {
        $denied = $this->denyUnless('whatsapp.manage');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->estate->saveTemplate($this->tenantId, $id, [
                'name'     => $this->request->getPost('name'),
                'language' => $this->request->getPost('language'),
                'body'     => $this->request->getPost('body'),
                'active'   => $this->request->getPost('active'),
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/whatsapp')->with('message', 'Template saved.');
    }

    public function sendTemplate(int $leadId)
    {
        $denied = $this->denyUnless('whatsapp.manage');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->estate->queueMessage($this->tenantId, $this->userId, $leadId, (int) $this->request->getPost('template_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/leads')->with('message', 'The message was saved on the lead. It was not delivered because WhatsApp is not connected.');
    }

    public function exportPlots()
    {
        return $this->csv('plots.view', 'plots.csv', ['Project', 'Plot', 'Block', 'Area', 'Facing', 'Price', 'Status'], array_map(static function (array $row): array {
            return [
                $row['project_name'],
                $row['plot_no'],
                $row['block'],
                $row['area_sqft'],
                $row['facing'],
                ((int) $row['price_minor']) / 100,
                $row['status'],
            ];
        }, $this->sites->plots($this->tenantId, (int) $this->request->getGet('project'))));
    }

    public function exportLeads()
    {
        return $this->csv('leads.view', 'leads.csv', ['Name', 'Phone', 'Email', 'Source', 'Status', 'Owner'], array_map(static function (array $row): array {
            return [$row['name'], $row['phone'], $row['email'], $row['source'], $row['status'], $row['owner_name']];
        }, $this->sites->leads($this->tenantId, trim((string) $this->request->getGet('q')))));
    }

    /**
     * @param list<string> $header
     * @param list<list<string|int|float|null>> $rows
     */
    private function csv(string $permission, string $filename, array $header, array $rows)
    {
        $denied = $this->denyUnless('exports.download');

        if ($denied !== null) {
            return $denied;
        }

        if (! $this->can($permission)) {
            return redirect()->to('/app')->with('error', 'Your role does not include that access.');
        }

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $header);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $body = stream_get_contents($handle) ?: '';
        fclose($handle);
        $this->desk->audit($this->tenantId, $this->userId, (string) session()->get('plan_code'), 'export', 'file', null, $filename);

        return $this->response
            ->setHeader('Content-Type', 'text/csv')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($body);
    }
}
