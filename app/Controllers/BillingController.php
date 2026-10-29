<?php

namespace App\Controllers;

class BillingController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('billing.manage');

        if ($denied !== null) {
            return $denied;
        }

        return $this->page('billing/index', [
            'active' => 'billing',
            'plan'   => (string) session()->get('plan_code'),
        ]);
    }

    public function save()
    {
        $denied = $this->denyUnless('billing.manage');

        if ($denied !== null) {
            return $denied;
        }

        $plan = (string) $this->request->getPost('plan_code');

        if (! in_array($plan, ['starter', 'growth', 'enterprise'], true)) {
            return redirect()->back()->with('error', 'Choose Starter, Growth, or Enterprise.');
        }

        db_connect()->table('tenants')->where('id', $this->tenantId)->update([
            'plan_code'  => $plan,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        session()->set('plan_code', $plan);
        $this->desk->audit($this->tenantId, $this->userId, $plan, 'plan.change', 'tenant', $this->tenantId, 'Plan set to ' . $plan);

        return redirect()->to('/app/billing')->with('message', 'Plan set to ' . ucfirst($plan) . '. Card checkout is not connected, so this did not charge a card.');
    }
}
