<?php

namespace App\Controllers;

class WorkController extends PortalController
{
    public function index()
    {
        $denied = $this->denyUnless('tasks.view');

        if ($denied !== null) {
            return $denied;
        }

        return $this->page('work/tasks', [
            'active'  => 'work',
            'tasks'   => $this->desk->tasks($this->tenantId),
            'members' => $this->roles->members($this->tenantId),
            'open'    => session()->getFlashdata('open_modal') === 'task' || $this->request->getGet('new') === '1',
        ]);
    }

    public function calendar()
    {
        $denied = $this->denyUnless('tasks.view');

        if ($denied !== null) {
            return $denied;
        }

        $month = (string) $this->request->getGet('month');

        return $this->page('work/calendar', [
            'active'   => 'work',
            'calendar' => $this->desk->calendar($this->tenantId, $month),
            'contacts' => $this->book->contacts($this->tenantId, ''),
            'open'     => session()->getFlashdata('open_modal') === 'meeting',
        ]);
    }

    public function createTask()
    {
        $denied = $this->denyUnless('tasks.create');

        if ($denied !== null) {
            return $denied;
        }

        return $this->storeTask(null);
    }

    public function updateTask(int $id)
    {
        $denied = $this->denyUnless('tasks.update');

        if ($denied !== null) {
            return $denied;
        }

        return $this->storeTask($id);
    }

    public function done(int $id)
    {
        $denied = $this->denyUnless('tasks.update');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->desk->toggleTask($this->tenantId, $id);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('message', 'Task updated.');
    }

    public function deleteTask(int $id)
    {
        $denied = $this->denyUnless('tasks.delete');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->desk->deleteTask($this->tenantId, $id);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/app/work')->with('message', 'Task removed.');
    }

    public function meeting()
    {
        $denied = $this->denyUnless('activities.create');

        if ($denied !== null) {
            return $denied;
        }

        try {
            $this->desk->saveActivity($this->tenantId, $this->userId, [
                'kind'        => 'meeting',
                'body'        => (string) $this->request->getPost('body'),
                'happened_at' => (string) $this->request->getPost('happened_at'),
                'contact_id'  => (int) $this->request->getPost('contact_id'),
            ]);
            $this->desk->audit($this->tenantId, $this->userId, (string) session()->get('plan_code'), 'meeting.create', 'activity', null, 'Meeting logged');
        } catch (\Throwable $e) {
            return redirect()->to('/app/work/calendar')->withInput()->with('error', $e->getMessage())->with('open_modal', 'meeting');
        }

        return redirect()->to('/app/work/calendar')->with('message', 'Meeting added.');
    }

    private function storeTask(?int $id)
    {
        $back = (string) $this->request->getPost('return') === 'back';

        try {
            $saved = $this->desk->saveTask($this->tenantId, $id, [
                'title'      => (string) $this->request->getPost('title'),
                'notes'      => (string) $this->request->getPost('notes'),
                'due_at'     => (string) $this->request->getPost('due_at'),
                'remind_at'  => (string) $this->request->getPost('remind_at'),
                'owner_id'   => (int) $this->request->getPost('owner_id'),
                'contact_id' => (int) $this->request->getPost('contact_id'),
                'company_id' => (int) $this->request->getPost('company_id'),
                'deal_id'    => (int) $this->request->getPost('deal_id'),
            ], $this->userId);
            $this->desk->audit($this->tenantId, $this->userId, (string) session()->get('plan_code'), $id === null ? 'task.create' : 'task.update', 'task', $saved, (string) $this->request->getPost('title'));
        } catch (\Throwable $e) {
            $target = $back ? redirect()->back() : redirect()->to('/app/work');

            return $target->withInput()->with('error', $e->getMessage())->with('open_modal', 'task');
        }

        $target = $back ? redirect()->back() : redirect()->to('/app/work');

        return $target->with('message', $id === null ? 'Task added.' : 'Task saved.');
    }
}
