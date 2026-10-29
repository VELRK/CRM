<?php

namespace App\Controllers;

class NoticesController extends PortalController
{
    public function index()
    {
        return $this->page('notices/index', [
            'active'  => 'home',
            'notices' => $this->desk->notices($this->tenantId, $this->userId),
        ]);
    }

    public function read(int $id)
    {
        $this->desk->markNotice($this->tenantId, $this->userId, $id);
        $notice = null;

        foreach ($this->desk->notices($this->tenantId, $this->userId) as $row) {
            if ((int) $row['id'] === $id) {
                $notice = $row;
                break;
            }
        }

        $href = is_array($notice) ? (string) ($notice['href'] ?? '') : '';

        if ($href !== '' && str_starts_with($href, '/app')) {
            return redirect()->to($href);
        }

        return redirect()->to('/app/notices');
    }

    public function readAll()
    {
        $this->desk->markAllNotices($this->tenantId, $this->userId);

        return redirect()->to('/app/notices')->with('message', 'Notices marked read.');
    }
}
