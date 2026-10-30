<?php

namespace App\Controllers;

class PlotsController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('plots.view');

        if ($denied !== null) {
            return $denied;
        }

        $projectId = (int) $this->request->getGet('project');
        $editId    = (int) $this->request->getGet('edit');
        $editing   = $editId > 0 ? $this->sites->plot($this->tenantId, $editId) : null;

        if ($editId > 0 && $editing === null) {
            return redirect()->to('/app/plots')->with('error', 'That plot is not in this workspace.');
        }

        return $this->page('sites/plots', [
            'active'    => 'plots',
            'projects'  => $this->sites->projects($this->tenantId),
            'plots'     => $this->sites->plots($this->tenantId, $projectId),
            'leads'     => $this->sites->leads($this->tenantId),
            'staff'     => $this->sites->assignable($this->tenantId),
            'statuses'  => $this->estate->options($this->tenantId, 'plot_status'),
            'projectId' => $projectId,
            'editing'   => $editing,
            'open'      => $editing !== null || $this->request->getGet('new') === '1' || old('form') === 'plot',
        ]);
    }

    public function save(?int $id = null)
    {
        $denied = $this->denyUnless($id === null ? 'plots.create' : 'plots.update');

        if ($denied !== null) {
            return $denied;
        }

        $status = (string) $this->request->getPost('status');
        $before = $id === null ? null : $this->sites->plot($this->tenantId, $id);

        try {
            $db = db_connect();
            $db->transException(true)->transStart();
            $saved = $this->sites->savePlot($this->tenantId, $id, [
                'project_id'      => $this->request->getPost('project_id'),
                'plot_no'         => $this->request->getPost('plot_no'),
                'block'           => $this->request->getPost('block'),
                'area_sqft'       => $this->request->getPost('area_sqft'),
                'facing'          => $this->request->getPost('facing'),
                'unit'            => $this->request->getPost('unit'),
                'price_per_unit'  => $this->request->getPost('price_per_unit'),
                'price'           => $this->request->getPost('price'),
                'commission'      => $this->request->getPost('commission'),
                'status'          => $status,
                'status_remark'   => $this->request->getPost('status_remark'),
                'by_user_id'      => $this->userId,
            ]);

            if ($before === null || (string) $before['status'] !== $status) {
                $this->estate->recordSale($this->tenantId, $this->userId, $saved, $status, [
                    'lead_id'           => $this->request->getPost('lead_id'),
                    'credited_user_id'  => $this->request->getPost('credited_user_id'),
                    'head_user_id'      => $this->request->getPost('head_user_id'),
                    'sale_value'        => $this->request->getPost('sale_value'),
                    'split_user_id'     => $this->request->getPost('split_user_id'),
                    'split_amount'      => $this->request->getPost('split_amount'),
                ]);
            }

            $db->transComplete();
            $this->desk->audit($this->tenantId, $this->userId, (string) session()->get('plan_code'), 'plot.status', 'plot', $saved, $status);
        } catch (\Throwable $e) {
            db_connect()->transRollback();

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/plots')->with('message', $id === null ? 'Plot added.' : 'Plot saved.');
    }

    public function delete(int $id)
    {
        $denied = $this->denyUnless('plots.delete');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->sites->deletePlot($this->tenantId, $id);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/plots')->with('message', 'Plot removed.');
    }
}
