<?php

namespace App\Controllers;

use App\Libraries\BookService;

class InvoicesController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('invoices.view');

        if ($denied !== null) {
            return $denied;
        }

        $locked = $this->planLocked('money', 'Invoices', 'invoices');

        if ($locked !== null) {
            return $locked;
        }

        $editId  = (int) $this->request->getGet('edit');
        $invoice = $editId > 0 ? $this->desk->invoice($this->tenantId, $editId) : null;

        if ($editId > 0 && $invoice === null) {
            return redirect()->to('/app/invoices')->with('error', 'That invoice is not in this workspace.');
        }

        return $this->page('invoices/index', [
            'active'    => 'invoices',
            'invoices'  => $this->desk->invoices($this->tenantId),
            'lineMap'   => $this->desk->groupedItems($this->tenantId, 'invoice_items', 'invoice_id'),
            'invoice'   => $invoice,
            'items'     => $invoice ? $this->desk->items('invoice_items', 'invoice_id', (int) $invoice['id']) : [],
            'open'      => $invoice !== null || $this->request->getGet('new') === '1',
            'contacts'  => $this->book->contacts($this->tenantId, ''),
            'companies' => $this->book->companies($this->tenantId, ''),
            'deals'     => $this->desk->dealOptions($this->tenantId),
            'products'  => $this->desk->products($this->tenantId),
            'currency'    => $this->desk->currency($this->tenantId),
            'recordFiles' => $invoice && $this->can('files.view') ? $this->desk->filesFor($this->tenantId, 'invoice', (int) $invoice['id']) : [],
        ]);
    }

    public function create()
    {
        return $this->store(null, 'invoices.create');
    }

    public function update(int $id)
    {
        return $this->store($id, 'invoices.update');
    }

    public function send(int $id)
    {
        $denied = $this->denyUnless('invoices.send');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $invoice = $this->desk->sendInvoice($this->tenantId, $id);
            $this->desk->audit($this->tenantId, $this->userId, (string) session()->get('plan_code'), 'invoice.send', 'invoice', $id, (string) $invoice['number']);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/invoices')->with('message', $invoice['number'] . ' marked sent. Mail is not configured on this server, so no email was delivered.');
    }

    public function payment(int $id)
    {
        $denied = $this->denyUnless('payments.record');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $amount = BookService::rupeesToMinor((string) $this->request->getPost('amount'));
            $status = $this->desk->recordPayment(
                $this->tenantId,
                $this->userId,
                $id,
                $amount,
                (string) $this->request->getPost('paid_on'),
                trim((string) $this->request->getPost('note')) ?: null,
            );
            $this->desk->audit($this->tenantId, $this->userId, (string) session()->get('plan_code'), 'payment.record', 'invoice', $id, $status);
        } catch (\Throwable $e) {
            return redirect()->to('/app/invoices?edit=' . $id)->with('error', $e->getMessage());
        }

        return redirect()->to('/app/invoices?edit=' . $id)->with('message', $status === 'paid' ? 'Payment recorded. Invoice is paid.' : 'Payment recorded.');
    }

    public function delete(int $id)
    {
        $denied = $this->denyUnless('invoices.delete');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->desk->deleteInvoice($this->tenantId, $id);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/invoices')->with('message', 'Invoice removed.');
    }

    private function store(?int $id, string $permission)
    {
        $denied = $this->denyUnless($permission);

        if ($denied !== null) {
            return $denied;
        }

        $back = '/app/invoices' . ($id ? '?edit=' . $id : '?new=1');

        try {
            $lines = $this->desk->parseLines((array) $this->request->getPost('lines'));
            $saved = $this->desk->saveInvoice($this->tenantId, $this->userId, $id, [
                'contact_id' => (int) $this->request->getPost('contact_id'),
                'company_id' => (int) $this->request->getPost('company_id'),
                'deal_id'    => (int) $this->request->getPost('deal_id'),
                'notes'      => (string) $this->request->getPost('notes'),
                'due_on'     => (string) $this->request->getPost('due_on'),
            ], $lines);
        } catch (\Throwable $e) {
            return redirect()->to($back)->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/invoices?edit=' . $saved)->with('message', $id === null ? 'Invoice added.' : 'Invoice saved.');
    }
}
