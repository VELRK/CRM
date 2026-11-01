<?php

namespace App\Controllers;

use App\Libraries\ReminderService;

class CronController extends BaseController
{
    public function reminders()
    {
        $secret = trim((string) env('cron.secret', ''));
        $given = trim((string) ($this->request->getGet('token') ?: $this->request->getHeaderLine('X-Cron-Token')));

        if ($secret === '' || $given === '' || ! hash_equals($secret, $given)) {
            return $this->response->setStatusCode(403)->setJSON([
                'ok'    => false,
                'error' => 'Cron token required.',
            ]);
        }

        return $this->response->setJSON([
            'ok'   => true,
            'data' => (new ReminderService(db_connect()))->run(),
        ]);
    }
}
