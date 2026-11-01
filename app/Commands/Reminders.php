<?php

namespace App\Commands;

use App\Libraries\ReminderService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class Reminders extends BaseCommand
{
    protected $group = 'Atrium';

    protected $name = 'reminders';

    protected $description = 'Send follow-up, visit, and task reminders by push and AskEva.';

    protected $usage = 'reminders';

    public function run(array $params)
    {
        $summary = (new ReminderService(db_connect()))->run();
        CLI::write(json_encode($summary));
    }
}
