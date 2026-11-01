<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Sends the follow-up, visit, and task reminder that is due in the current
 * 15-minute slot. Push goes to the assigned person. WhatsApp goes through AskEva
 * to that person's phone. Each schedule is reminded once.
 */
class ReminderService
{
    private AskEva $askeva;

    public function __construct(private BaseConnection $db)
    {
        $this->askeva = new AskEva();
    }

    /**
     * @return array{followups: int, visits: int, tasks: int}
     */
    public function run(): array
    {
        $this->ensureColumns();
        $now = time();
        $slot = 15 * 60;
        $start = intdiv($now, $slot) * $slot;
        $from = date('Y-m-d H:i:s', $start - $slot);
        $to = date('Y-m-d H:i:s', $start + $slot);
        $counts = ['followups' => 0, 'visits' => 0, 'tasks' => 0];
        $push = new PushService($this->db);
        $estate = new EstateService($this->db);

        if (! $this->db->tableExists('tenants')) {
            return $counts;
        }

        foreach ($this->db->table('tenants')->select('id')->get()->getResultArray() as $tenant) {
            $tenantId = (int) $tenant['id'];
            $token = (string) ($estate->setting($tenantId, 'whatsapp_token') ?? '');
            $counts['followups'] += $this->followups($tenantId, $from, $to, $now, $start, $token, $estate, $push);
            $counts['visits'] += $this->visits($tenantId, $from, $to, $now, $start, $token, $estate, $push);
            $counts['tasks'] += $this->tasks($tenantId, $from, $to, $now, $start, $token, $estate, $push);
        }

        return $counts;
    }

    private function followups(int $tenantId, string $from, string $to, int $now, int $start, string $token, EstateService $estate, PushService $push): int
    {
        if (! $this->db->tableExists('followups') || ! $this->db->fieldExists('call_scheduled_at', 'followups')) {
            return 0;
        }

        $rows = $this->db->table('followups f')
            ->select('f.id, f.lead_id, f.assigned_to, f.user_id, f.call_subject, f.call_scheduled_at, f.reminder_for, f.assigned_by, l.name AS lead_name, IFNULL(l.phone_number, l.phone) AS lead_phone, b.name AS assigned_by_name', false)
            ->join('leads l', 'l.id = f.lead_id', 'left')
            ->join('users b', 'b.id = f.assigned_by', 'left')
            ->where('f.tenant_id', $tenantId)
            ->where('f.deleted_at', null)
            ->where('f.done_at', null)
            ->whereIn('f.current_state', ['SCHEDULED', 'OVERDUE'])
            ->where('f.call_scheduled_at >=', $from)
            ->where('f.call_scheduled_at <', $to)
            ->get()
            ->getResultArray();
        $sent = 0;

        foreach ($rows as $row) {
            if (! $this->due((string) $row['call_scheduled_at'], (string) ($row['reminder_for'] ?? ''), $now, $start)) {
                continue;
            }

            $userId = (int) ($row['assigned_to'] ?: $row['user_id']);
            $when = $this->label((string) $row['call_scheduled_at']);
            $lead = (string) ($row['lead_name'] ?: 'a lead');
            $this->notify($push, $tenantId, $userId, 'followup.reminder', 'Follow-up reminder', 'You have a follow-up with ' . $lead . ' at ' . $when, 'followup', (int) $row['id'], $lead, (string) ($row['call_subject'] ?? ''), $when);
            $this->whatsapp($tenantId, $token, $estate, $userId, (int) ($row['lead_id'] ?? 0), 'whatsapp_followup_reminder', 'Follow-up reminder', [
                'clientname' => $lead, 'name' => $lead, 'number' => (string) ($row['lead_phone'] ?? ''), 'phone' => (string) ($row['lead_phone'] ?? ''),
                'subject' => (string) ($row['call_subject'] ?? ''), 'scheduleddate' => $when, 'when' => $when, 'assignedby' => (string) ($row['assigned_by_name'] ?? ''),
            ], ['clientname', 'number', 'subject', 'scheduleddate', 'assignedby']);
            $this->db->table('followups')->where('id', (int) $row['id'])->update(['reminder_for' => $row['call_scheduled_at']]);
            $sent++;
        }

        return $sent;
    }

    private function visits(int $tenantId, string $from, string $to, int $now, int $start, string $token, EstateService $estate, PushService $push): int
    {
        if (! $this->db->tableExists('site_visits') || ! $this->db->fieldExists('scheduled_at', 'site_visits')) {
            return 0;
        }

        $rows = $this->db->table('site_visits v')
            ->select('v.id, v.lead_id, v.staff_id, v.scheduled_at, v.reminder_for, l.name AS lead_name, IFNULL(l.phone_number, l.phone) AS lead_phone, pr.name AS project_name, b.name AS assigned_by_name', false)
            ->join('leads l', 'l.id = v.lead_id', 'left')
            ->join('projects pr', 'pr.id = v.project_id', 'left')
            ->join('users b', 'b.id = v.assigned_by', 'left')
            ->where('v.tenant_id', $tenantId)
            ->where('v.deleted_at', null)
            ->whereIn('v.current_state', ['SCHEDULED', 'OVERDUE'])
            ->where('v.scheduled_at >=', $from)
            ->where('v.scheduled_at <', $to)
            ->get()
            ->getResultArray();
        $sent = 0;

        foreach ($rows as $row) {
            if (! $this->due((string) $row['scheduled_at'], (string) ($row['reminder_for'] ?? ''), $now, $start)) {
                continue;
            }

            $userId = (int) $row['staff_id'];
            $when = $this->label((string) $row['scheduled_at']);
            $lead = (string) ($row['lead_name'] ?: 'a lead');
            $project = (string) ($row['project_name'] ?: '');
            $this->notify($push, $tenantId, $userId, 'visit.reminder', 'Visit reminder', 'You have a site visit with ' . $lead . ' at ' . $when, 'visit', (int) $row['id'], $lead, $project, $when);
            $this->whatsapp($tenantId, $token, $estate, $userId, (int) ($row['lead_id'] ?? 0), 'whatsapp_visit_reminder', 'Visit reminder', [
                'clientname' => $lead, 'name' => $lead, 'number' => (string) ($row['lead_phone'] ?? ''), 'phone' => (string) ($row['lead_phone'] ?? ''),
                'visitproject' => $project, 'project' => $project, 'scheduleddate' => $when, 'when' => $when, 'assignedby' => (string) ($row['assigned_by_name'] ?? ''),
            ], ['clientname', 'number', 'visitproject', 'scheduleddate', 'assignedby']);
            $this->db->table('site_visits')->where('id', (int) $row['id'])->update(['reminder_for' => $row['scheduled_at']]);
            $sent++;
        }

        return $sent;
    }

    private function tasks(int $tenantId, string $from, string $to, int $now, int $start, string $token, EstateService $estate, PushService $push): int
    {
        if (! $this->db->tableExists('lead_tasks') || ! $this->db->fieldExists('scheduled_at', 'lead_tasks')) {
            return 0;
        }

        $rows = $this->db->table('lead_tasks t')
            ->select('t.id, t.lead_id, t.assigned_to, t.subject, t.scheduled_at, t.reminder_for, l.name AS lead_name, b.name AS assigned_by_name', false)
            ->join('leads l', 'l.id = t.lead_id', 'left')
            ->join('users b', 'b.id = t.assigned_by', 'left')
            ->where('t.tenant_id', $tenantId)
            ->where('t.deleted_at', null)
            ->whereIn('t.current_state', ['SCHEDULED', 'OVERDUE'])
            ->where('t.scheduled_at >=', $from)
            ->where('t.scheduled_at <', $to)
            ->get()
            ->getResultArray();
        $sent = 0;

        foreach ($rows as $row) {
            if (! $this->due((string) $row['scheduled_at'], (string) ($row['reminder_for'] ?? ''), $now, $start)) {
                continue;
            }

            $userId = (int) $row['assigned_to'];
            $when = $this->label((string) $row['scheduled_at']);
            $lead = (string) ($row['lead_name'] ?: '');
            $subject = (string) ($row['subject'] ?: 'Task');
            $this->notify($push, $tenantId, $userId, 'task.reminder', 'Task reminder', $subject . ' is due at ' . $when, 'task', (int) $row['id'], $lead, $subject, $when);
            $this->whatsapp($tenantId, $token, $estate, $userId, (int) ($row['lead_id'] ?? 0), 'whatsapp_task_reminder', 'Task reminder', [
                'clientname' => $lead !== '' ? $lead : '-', 'name' => $lead !== '' ? $lead : '-', 'subject' => $subject,
                'scheduleddate' => $when, 'when' => $when, 'assignedby' => (string) ($row['assigned_by_name'] ?? ''),
            ], ['clientname', 'subject', 'scheduleddate', 'assignedby']);
            $this->db->table('lead_tasks')->where('id', (int) $row['id'])->update(['reminder_for' => $row['scheduled_at']]);
            $sent++;
        }

        return $sent;
    }

    private function due(string $scheduled, string $reminded, int $now, int $start): bool
    {
        $at = strtotime($scheduled);

        if ($at === false) {
            return false;
        }

        $inSlot = $at >= $start && $at < ($start + 15 * 60);
        $catchUp = $at >= ($start - 15 * 60) && $at < $start && $at <= $now;

        if (! $inSlot && ! $catchUp) {
            return false;
        }

        return $reminded === '' || strtotime($reminded) !== $at;
    }

    private function notify(PushService $push, int $tenantId, int $userId, string $event, string $title, string $message, string $type, int $id, string $lead, string $task, string $when): void
    {
        if ($userId < 1) {
            return;
        }

        $context = [
            'subject_user_id' => $userId,
            'subject_type'    => $type,
            'subject_id'      => $id,
            'name'            => '',
            'lead'            => $lead,
            'task'            => $task,
            'place'           => $when,
        ];
        $ruled = 0;

        try {
            $ruled = $push->trigger($tenantId, 0, $event, $context);
        } catch (\Throwable $e) {
            log_message('error', 'Reminder push rule failed: ' . $e->getMessage());
        }

        if ($ruled > 0) {
            return;
        }

        try {
            $push->remindUser($tenantId, $userId, $event, $title, $message, $type, $id);
        } catch (\Throwable $e) {
            log_message('error', 'Reminder push failed: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, string> $known
     * @param list<string> $fallback
     */
    private function whatsapp(int $tenantId, string $token, EstateService $estate, int $userId, int $leadId, string $setting, string $subject, array $known, array $fallback): void
    {
        $name = trim((string) ($estate->setting($tenantId, $setting) ?? ''));

        if ($token === '' || $name === '' || $userId < 1) {
            return;
        }

        $phone = $this->staffPhone($tenantId, $userId);

        if ($phone === '') {
            return;
        }

        $template = $this->db->table('whatsapp_templates')
            ->where('tenant_id', $tenantId)
            ->where('name', $name)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();
        $language = (string) ($template['language'] ?? 'en');
        $body = (string) ($template['body'] ?? '');
        $values = $this->askeva->values($body, $known, $fallback);
        $result = $this->askeva->sendTemplate($token, $phone, $name, $language, $values);
        $rendered = $body !== '' ? strtr($body, $this->braces($known)) : $subject;

        if ($leadId < 1 || ! $this->db->tableExists('whatsapp_messages')) {
            return;
        }

        $this->db->table('whatsapp_messages')->insert([
            'tenant_id'   => $tenantId,
            'lead_id'     => $leadId,
            'user_id'     => $userId,
            'direction'   => 'out',
            'template_id' => $template === null ? null : (int) $template['id'],
            'to_phone'    => $phone,
            'subject'     => $subject,
            'body'        => mb_substr($rendered, 0, 2000),
            'status'      => $result['ok'] ? 'sent' : 'failed',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string, string> $known
     * @return array<string, string>
     */
    private function braces(array $known): array
    {
        $map = [];

        foreach ($known as $key => $value) {
            $map['{{' . $key . '}}'] = $value;
        }

        return $map;
    }

    private function staffPhone(int $tenantId, int $userId): string
    {
        if (! $this->db->tableExists('staff_profiles')) {
            return '';
        }

        $row = $this->db->table('staff_profiles')
            ->select('phone_number, phone')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return trim((string) (($row['phone_number'] ?? '') !== '' ? $row['phone_number'] : ($row['phone'] ?? '')));
    }

    private function label(string $value): string
    {
        $stamp = strtotime($value);

        return $stamp === false ? $value : date('d M Y, h:i A', $stamp);
    }

    private function ensureColumns(): void
    {
        foreach (['followups', 'site_visits', 'lead_tasks'] as $table) {
            if (! $this->db->tableExists($table) || $this->db->fieldExists('reminder_for', $table)) {
                continue;
            }

            $name = str_replace('`', '', $this->db->prefixTable($table));
            $this->db->query('ALTER TABLE `' . $name . '` ADD `reminder_for` DATETIME NULL');
            $this->db->resetDataCache();
        }
    }
}
