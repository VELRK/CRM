<?php

namespace App\Controllers;

use App\Libraries\AccessCatalog;

class SearchController extends PortalController
{
    public function index()
    {
        $query = trim((string) $this->request->getGet('q'));
        $money = AccessCatalog::allows((string) session()->get('plan_code'), 'money');

        $results = $this->desk->search($this->tenantId, $query, $money);
        $results['leads'] = $this->can('leads.view') ? $this->sites->searchLeads($this->tenantId, $query) : [];

        return $this->page('search/index', [
            'active'  => 'home',
            'query'   => $query,
            'results' => $results,
        ]);
    }
}
