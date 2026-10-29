<?php

namespace App\Controllers;

class AuditController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('team.view');

        if ($denied !== null) {
            return $denied;
        }

        $locked = $this->planLocked('audit', 'Audit log', 'audit');

        if ($locked !== null) {
            return $locked;
        }

        return $this->page('audit/index', [
            'active' => 'audit',
            'rows'   => $this->desk->auditLog($this->tenantId),
        ]);
    }
}
