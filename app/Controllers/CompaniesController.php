<?php

namespace App\Controllers;

class CompaniesController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('companies.view');

        if ($denied !== null) {
            return $denied;
        }

        $query     = trim((string) $this->request->getGet('q'));
        $companies = $this->book->companies($this->tenantId, $query);
        $editId    = (int) $this->request->getGet('edit');
        $editing   = null;

        foreach ($companies as $company) {
            if ((int) $company['id'] === $editId) {
                $editing = $company;
            }
        }

        if ($editId > 0 && $editing === null) {
            return redirect()->to('/app/companies')->with('error', 'That company is not in this workspace.');
        }

        return $this->page('people/companies', [
            'active'    => 'people',
            'query'     => $query,
            'companies' => $companies,
            'members'   => $this->roles->members($this->tenantId),
            'editing'   => $editing,
            'open'      => $editing !== null || $this->request->getGet('new') === '1' || old('form') === 'company',
        ]);
    }

    public function new()
    {
        $denied = $this->denyUnless('companies.create');

        if ($denied !== null) {
            return $denied;
        }

        return redirect()->to('/app/companies?new=1');
    }

    public function show(int $id)
    {
        $denied = $this->denyUnless('companies.view');

        if ($denied !== null) {
            return $denied;
        }

        $company = $this->book->company($this->tenantId, $id);

        if ($company === null) {
            return redirect()->to('/app/companies')->with('error', 'That company is not in this workspace.');
        }

        return redirect()->to($this->can('companies.update') ? '/app/companies?edit=' . $id : '/app/companies');
    }

    public function create()
    {
        $denied = $this->denyUnless('companies.create');

        if ($denied !== null) {
            return $denied;
        }

        return $this->store(null);
    }

    public function update(int $id)
    {
        $denied = $this->denyUnless('companies.update');

        if ($denied !== null) {
            return $denied;
        }

        return $this->store($id);
    }

    public function delete(int $id)
    {
        $denied = $this->denyUnless('companies.delete');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->book->deleteCompany($this->tenantId, $id);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/companies')->with('message', 'Company removed.');
    }

    private function form(?array $company)
    {
        $id = $company === null ? 0 : (int) $company['id'];

        return $this->page('people/company_form', array_merge([
            'active'   => 'people',
            'company'  => $company,
            'members'  => $this->roles->members($this->tenantId),
            'contacts' => $id > 0 ? $this->book->contactsForCompany($this->tenantId, $id) : [],
            'deals'    => $id > 0 ? $this->book->dealsFor($this->tenantId, 'company_id', $id) : [],
        ], $this->deskBundle('company', $id)));
    }

    private function store(?int $id)
    {
        $name = trim((string) $this->request->getPost('name'));

        if ($name === '') {
            return redirect()->back()->withInput()->with('error', 'A company needs a name.');
        }

        $ownerId = (int) $this->request->getPost('owner_id');

        if ($id !== null && $this->book->company($this->tenantId, $id) === null) {
            return redirect()->to('/app/companies')->with('error', 'That company is not in this workspace.');
        }

        if ($ownerId < 1) {
            $ownerId = $this->userId;
        }

        try {
            $saved = $this->book->saveCompany($this->tenantId, $id, [
                'name'     => $name,
                'phone'    => trim((string) $this->request->getPost('phone')) ?: null,
                'website'  => trim((string) $this->request->getPost('website')) ?: null,
                'owner_id' => $ownerId,
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/companies')->with('message', $id === null ? 'Company added.' : 'Company saved.');
    }
}
