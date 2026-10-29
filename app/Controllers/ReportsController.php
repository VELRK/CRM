<?php

namespace App\Controllers;

use App\Libraries\AccessCatalog;
use App\Libraries\ReportFile;

class ReportsController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('reports.view');

        if ($denied !== null) {
            return $denied;
        }

        $locked = $this->planLocked('reports', 'Reports', 'reports');

        if ($locked !== null) {
            return $locked;
        }

        $pack = $this->pack();

        return $this->page('reports/index', [
            'active' => 'reports',
            'scope'  => $pack['scope'],
            'board'  => $pack['board'],
        ]);
    }

    public function excel()
    {
        $pack = $this->exportPack();

        if (! is_array($pack)) {
            return $pack;
        }

        $sheets = ReportFile::sheets($pack['board']);
        $this->desk->audit($this->tenantId, $this->userId, (string) session()->get('plan_code'), 'export', 'file', null, 'reports.xls');

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.ms-excel')
            ->setHeader('Content-Disposition', 'attachment; filename="atrium-reports.xls"')
            ->setBody(ReportFile::spreadsheet($sheets));
    }

    public function pdf()
    {
        $pack = $this->exportPack();

        if (! is_array($pack)) {
            return $pack;
        }

        $sections = ReportFile::sections($pack['board']);
        $this->desk->audit($this->tenantId, $this->userId, (string) session()->get('plan_code'), 'export', 'file', null, 'reports.pdf');

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="atrium-reports.pdf"')
            ->setBody(ReportFile::pdf('Atrium reports', (string) $pack['scope']['label'] . ' view', $sections));
    }

    /**
     * @return array{scope: array<string, mixed>, board: array<string, mixed>}| \CodeIgniter\HTTP\RedirectResponse
     */
    private function exportPack(): array|\CodeIgniter\HTTP\RedirectResponse
    {
        $denied = $this->denyUnless('reports.view');

        if ($denied !== null) {
            return $denied;
        }

        if (! $this->can('exports.download')) {
            return redirect()->to('/app/reports')->with('error', 'Your role does not include downloads.');
        }

        if (! AccessCatalog::allows((string) session()->get('plan_code'), 'reports')) {
            return redirect()->to('/app/reports');
        }

        return $this->pack();
    }

    /**
     * @return array{scope: array<string, mixed>, board: array<string, mixed>}
     */
    private function pack(): array
    {
        $scope = $this->estate->reportScope($this->tenantId, $this->userId, (string) session()->get('role_name'), $this->isOwner);

        return [
            'scope' => $scope,
            'board' => $this->estate->dashboard($this->tenantId, $scope),
        ];
    }
}
