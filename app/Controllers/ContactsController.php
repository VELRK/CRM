<?php

namespace App\Controllers;

class ContactsController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('contacts.view');

        if ($denied !== null) {
            return $denied;
        }

        $query    = trim((string) $this->request->getGet('q'));
        $contacts = $this->book->contacts($this->tenantId, $query);
        $editId   = (int) $this->request->getGet('edit');
        $editing  = null;

        foreach ($contacts as $contact) {
            if ((int) $contact['id'] === $editId) {
                $editing = $contact;
            }
        }

        if ($editId > 0 && $editing === null) {
            return redirect()->to('/app/people')->with('error', 'That contact is not in this workspace.');
        }

        return $this->page('people/contacts', [
            'active'     => 'people',
            'query'      => $query,
            'contacts'   => $contacts,
            'companies'  => $this->book->companies($this->tenantId, ''),
            'members'    => $this->roles->members($this->tenantId),
            'companyMap' => $this->book->companyMap($this->tenantId),
            'editing'    => $editing,
            'open'       => $editing !== null || $this->request->getGet('new') === '1' || old('form') === 'contact',
        ]);
    }

    public function new()
    {
        $denied = $this->denyUnless('contacts.create');

        if ($denied !== null) {
            return $denied;
        }

        return redirect()->to('/app/people?new=1');
    }

    public function show(int $id)
    {
        $denied = $this->denyUnless('contacts.view');

        if ($denied !== null) {
            return $denied;
        }

        $contact = $this->book->contact($this->tenantId, $id);

        if ($contact === null) {
            return redirect()->to('/app/people')->with('error', 'That contact is not in this workspace.');
        }

        return redirect()->to($this->can('contacts.update') ? '/app/people?edit=' . $id : '/app/people');
    }

    public function create()
    {
        $denied = $this->denyUnless('contacts.create');

        if ($denied !== null) {
            return $denied;
        }

        return $this->store(null);
    }

    public function update(int $id)
    {
        $denied = $this->denyUnless('contacts.update');

        if ($denied !== null) {
            return $denied;
        }

        return $this->store($id);
    }

    public function delete(int $id)
    {
        $denied = $this->denyUnless('contacts.delete');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->book->deleteContact($this->tenantId, $id);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/people')->with('message', 'Contact removed.');
    }

    private function form(?array $contact)
    {
        $id = $contact === null ? 0 : (int) $contact['id'];

        return $this->page('people/contact_form', array_merge([
            'active'     => 'people',
            'contact'    => $contact,
            'members'    => $this->roles->members($this->tenantId),
            'companies'  => $this->book->companies($this->tenantId, ''),
            'linked'     => $id > 0 ? $this->book->companyIdsForContact($this->tenantId, $id) : [],
            'deals'      => $id > 0 ? $this->book->dealsFor($this->tenantId, 'contact_id', $id) : [],
        ], $this->deskBundle('contact', $id)));
    }

    private function store(?int $id)
    {
        $name = trim((string) $this->request->getPost('name'));

        if ($name === '') {
            return redirect()->back()->withInput()->with('error', 'A contact needs a name.');
        }

        $email = strtolower(trim((string) $this->request->getPost('email')));

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->withInput()->with('error', 'Enter a valid email or leave it blank.');
        }

        $ownerId = (int) $this->request->getPost('owner_id');

        if (! $this->can('contacts.assign')) {
            $ownerId = $id === null
                ? $this->userId
                : (int) ($this->book->contact($this->tenantId, $id)['owner_id'] ?? $this->userId);
        }

        $companyIds = $this->request->getPost('company_ids');
        $companyIds = is_array($companyIds) ? $companyIds : [];

        try {
            $saved = $this->book->saveContact($this->tenantId, $id, [
                'name'     => $name,
                'email'    => $email !== '' ? $email : null,
                'phone'    => trim((string) $this->request->getPost('phone')) ?: null,
                'tags'     => trim((string) $this->request->getPost('tags')) ?: null,
                'owner_id' => $ownerId > 0 ? $ownerId : null,
            ], $companyIds, (string) session()->get('plan_code'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/people')->with('message', $id === null ? 'Contact added.' : 'Contact saved.');
    }
}
