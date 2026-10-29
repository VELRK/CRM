<?php

namespace App\Controllers;

use App\Libraries\BookService;

class PipelineController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('deals.view');

        if ($denied !== null) {
            return $denied;
        }

        $query   = trim((string) $this->request->getGet('q'));
        $board   = $this->book->board($this->tenantId, $query);
        $editId  = (int) $this->request->getGet('edit');
        $editing = $editId > 0 ? $this->book->deal($this->tenantId, $editId) : null;

        if ($editId > 0 && $editing === null) {
            return redirect()->to('/app/pipeline')->with('error', 'That deal is not in this workspace.');
        }

        return $this->page('pipeline/board', [
            'active'    => 'pipeline',
            'query'     => $query,
            'board'     => $board,
            'companies' => $this->book->companies($this->tenantId, ''),
            'contacts'  => $this->book->contacts($this->tenantId, ''),
            'members'   => $this->roles->members($this->tenantId),
            'editing'   => $editing,
            'open'      => ($editing !== null && $this->can('deals.update')) || $this->request->getGet('new') === '1' || old('form') === 'deal',
        ]);
    }

    public function new()
    {
        $denied = $this->denyUnless('deals.create');

        if ($denied !== null) {
            return $denied;
        }

        return redirect()->to('/app/pipeline?new=1');
    }

    public function show(int $id)
    {
        $denied = $this->denyUnless('deals.view');

        if ($denied !== null) {
            return $denied;
        }

        $deal = $this->book->deal($this->tenantId, $id);

        if ($deal === null) {
            return redirect()->to('/app/pipeline')->with('error', 'That deal is not in this workspace.');
        }

        return redirect()->to($this->can('deals.update') ? '/app/pipeline?edit=' . $id : '/app/pipeline');
    }

    public function create()
    {
        $denied = $this->denyUnless('deals.create');

        if ($denied !== null) {
            return $denied;
        }

        return $this->store(null);
    }

    public function update(int $id)
    {
        $denied = $this->denyUnless('deals.update');

        if ($denied !== null) {
            return $denied;
        }

        return $this->store($id);
    }

    public function move(int $id)
    {
        $denied = $this->denyUnless('deals.move_stage');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->book->moveDeal($this->tenantId, $id, (int) $this->request->getPost('stage_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $back = (string) $this->request->getPost('back');

        return redirect()->to($back === 'deal' ? '/app/pipeline/deals/' . $id : '/app/pipeline')
            ->with('message', 'Stage updated.');
    }

    public function delete(int $id)
    {
        $denied = $this->denyUnless('deals.delete');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->book->deleteDeal($this->tenantId, $id);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/pipeline')->with('message', 'Deal removed.');
    }

    private function form(?array $deal)
    {
        $pipelineId = $this->book->ensurePipeline($this->tenantId);

        return $this->page('pipeline/form', array_merge([
            'active'          => 'pipeline',
            'deal'            => $deal,
            'stages'          => $this->book->stages($this->tenantId, $pipelineId),
            'companies'       => $this->book->companies($this->tenantId, ''),
            'contacts'        => $this->book->contacts($this->tenantId, ''),
            'members'         => $this->roles->members($this->tenantId),
            'prefillCompany'  => (int) $this->request->getGet('company'),
            'prefillContact'  => (int) $this->request->getGet('contact'),
        ], $this->deskBundle('deal', $deal === null ? 0 : (int) $deal['id'])));
    }

    private function store(?int $id)
    {
        $title = trim((string) $this->request->getPost('title'));

        if ($title === '') {
            return redirect()->back()->withInput()->with('error', 'A deal needs a title.');
        }

        $existing = $id === null ? null : $this->book->deal($this->tenantId, $id);

        if ($id !== null && $existing === null) {
            return redirect()->to('/app/pipeline')->with('error', 'That deal is not in this workspace.');
        }

        try {
            $amount = BookService::rupeesToMinor((string) $this->request->getPost('amount'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $stageId = (int) $this->request->getPost('stage_id');

        if ($id !== null && ! $this->can('deals.move_stage')) {
            $stageId = (int) $existing['stage_id'];
        }

        $ownerId = (int) $this->request->getPost('owner_id');

        if ($ownerId < 1) {
            $ownerId = $this->userId;
        }

        try {
            $saved = $this->book->saveDeal($this->tenantId, $id, [
                'title'        => $title,
                'amount_minor' => $amount,
                'currency'     => 'INR',
                'stage_id'     => $stageId,
                'company_id'   => (int) $this->request->getPost('company_id'),
                'contact_id'   => (int) $this->request->getPost('contact_id'),
                'owner_id'     => $ownerId,
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/pipeline')->with('message', $id === null ? 'Deal added.' : 'Deal saved.');
    }
}
