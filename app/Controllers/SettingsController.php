<?php

namespace App\Controllers;

use App\Libraries\PushService;

class SettingsController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('workspace.settings');

        if ($denied !== null) {
            return $denied;
        }

        $tenant = db_connect()->table('tenants')->where('id', $this->tenantId)->get()->getRowArray();

        return $this->page('settings/index', [
            'active'       => 'settings',
            'name'         => (string) session()->get('workspace_name'),
            'currency'     => (string) ($tenant['currency'] ?? 'INR'),
            'fiscalMonth'  => (int) ($tenant['fiscal_month'] ?? 4),
            'whatsapp'     => $this->estate->whatsappSettings($this->tenantId),
            'firebase'     => (new PushService(db_connect()))->firebaseStatus($this->tenantId),
        ]);
    }

    public function save()
    {
        $denied = $this->denyUnless('workspace.settings');

        if ($denied !== null) {
            return $denied;
        }

        $name = trim((string) $this->request->getPost('name'));

        if ($name === '') {
            return redirect()->back()->with('error', 'The workspace needs a name.');
        }

        $db     = db_connect();
        $update = [
            'name'       => $name,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($db->fieldExists('currency', 'tenants')) {
            $currency = strtoupper(trim((string) $this->request->getPost('currency')));
            $month    = (int) $this->request->getPost('fiscal_month');
            $update['currency']     = $currency === 'USD' ? 'USD' : 'INR';
            $update['fiscal_month'] = $month >= 1 && $month <= 12 ? $month : 4;
        }

        $db->table('tenants')->where('id', $this->tenantId)->update($update);

        session()->set('workspace_name', $name);
        $this->estate->saveWhatsappSettings($this->tenantId, $this->request->getPost() ?? []);
        $file = $this->request->getFile('account');

        if ($file !== null && $file->isValid() && $file->getSize() > 0) {
            if ((int) $file->getSize() > 20 * 1024) {
                return redirect()->back()->with('error', 'Use a Firebase service account file up to 20 KB.');
            }

            try {
                (new PushService(db_connect()))->saveFirebase($this->tenantId, (string) file_get_contents($file->getTempName()));
            } catch (\Throwable $e) {
                return redirect()->back()->with('error', $e->getMessage());
            }
        }

        return redirect()->to('/app/settings')->with('message', 'Settings saved.');
    }
}
