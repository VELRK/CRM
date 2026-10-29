<?php

namespace App\Controllers;

class QuotesController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('quotes.view');

        if ($denied !== null) {
            return $denied;
        }

        $locked = $this->planLocked('money', 'Quotes', 'quotes');

        if ($locked !== null) {
            return $locked;
        }

        $editId = (int) $this->request->getGet('edit');
        $quote  = $editId > 0 ? $this->desk->quote($this->tenantId, $editId) : null;

        if ($editId > 0 && $quote === null) {
            return redirect()->to('/app/quotes')->with('error', 'That quote is not in this workspace.');
        }

        return $this->page('quotes/index', array_merge($this->editorData(), [
            'active' => 'quotes',
            'quotes' => $this->desk->quotes($this->tenantId),
            'lineMap' => $this->desk->groupedItems($this->tenantId, 'quote_items', 'quote_id'),
            'quote'  => $quote,
            'items'  => $quote ? $this->desk->items('quote_items', 'quote_id', (int) $quote['id']) : [],
            'open'        => $quote !== null || $this->request->getGet('new') === '1',
            'recordFiles' => $quote && $this->can('files.view') ? $this->desk->filesFor($this->tenantId, 'quote', (int) $quote['id']) : [],
        ]));
    }

    public function create()
    {
        return $this->store(null, 'quotes.create');
    }

    public function update(int $id)
    {
        return $this->store($id, 'quotes.update');
    }

    public function send(int $id)
    {
        $denied = $this->denyUnless('quotes.send');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $quote = $this->desk->sendQuote($this->tenantId, $id);
            $this->desk->audit($this->tenantId, $this->userId, (string) session()->get('plan_code'), 'quote.send', 'quote', $id, (string) $quote['number']);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/quotes')->with('message', $quote['number'] . ' marked sent. Mail is not configured on this server, so no email was delivered.');
    }

    public function invoice(int $id)
    {
        $denied = $this->denyUnless('invoices.create');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $invoiceId = $this->desk->invoiceFromQuote($this->tenantId, $this->userId, $id);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/invoices?edit=' . $invoiceId)->with('message', 'Invoice created from the quote.');
    }

    public function delete(int $id)
    {
        $denied = $this->denyUnless('quotes.delete');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->desk->deleteQuote($this->tenantId, $id);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/quotes')->with('message', 'Quote removed.');
    }

    private function store(?int $id, string $permission)
    {
        $denied = $this->denyUnless($permission);

        if ($denied !== null) {
            return $denied;
        }

        $back = '/app/quotes' . ($id ? '?edit=' . $id : '?new=1');

        try {
            $lines = $this->desk->parseLines((array) $this->request->getPost('lines'));
            $saved = $this->desk->saveQuote($this->tenantId, $this->userId, $id, $this->header(), $lines);
        } catch (\Throwable $e) {
            return redirect()->to($back)->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/quotes?edit=' . $saved)->with('message', $id === null ? 'Quote added.' : 'Quote saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function header(): array
    {
        return [
            'contact_id' => (int) $this->request->getPost('contact_id'),
            'company_id' => (int) $this->request->getPost('company_id'),
            'deal_id'    => (int) $this->request->getPost('deal_id'),
            'notes'      => (string) $this->request->getPost('notes'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function editorData(): array
    {
        return [
            'contacts'  => $this->book->contacts($this->tenantId, ''),
            'companies' => $this->book->companies($this->tenantId, ''),
            'deals'     => $this->desk->dealOptions($this->tenantId),
            'products'  => $this->desk->products($this->tenantId),
            'currency'  => $this->desk->currency($this->tenantId),
        ];
    }
}
