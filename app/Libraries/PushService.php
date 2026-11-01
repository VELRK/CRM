<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Resolves who should hear about a workspace event, stores the in-app notice,
 * and delivers a phone push when a Firebase service account is configured.
 */
class PushService
{
    /** @var array<string, array{module: string, label: string}> */
    public const EVENTS = [
        'lead.assigned'      => ['module' => 'Leads', 'label' => 'Lead assigned or transferred'],
        'followup.created'   => ['module' => 'Follow-ups', 'label' => 'Follow-up scheduled'],
        'visit.scheduled'    => ['module' => 'Visits', 'label' => 'Site visit scheduled'],
        'task.assigned'      => ['module' => 'Tasks', 'label' => 'Task assigned'],
        'task.completed'     => ['module' => 'Tasks', 'label' => 'Task completed'],
        'followup.reminder'  => ['module' => 'Follow-ups', 'label' => 'Follow-up reminder'],
        'visit.reminder'     => ['module' => 'Visits', 'label' => 'Site visit reminder'],
        'task.reminder'      => ['module' => 'Tasks', 'label' => 'Task reminder'],
        'attendance.marked'  => ['module' => 'Attendance', 'label' => 'Attendance marked'],
        'manual'             => ['module' => 'Announcement', 'label' => 'Announcement'],
    ];

    private ?string $accessToken = null;

    public function __construct(private BaseConnection $db)
    {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function registerDevice(int $tenantId, int $userId, array $input): void
    {
        $this->ready();
        $token = trim((string) ($input['token'] ?? $input['device_token'] ?? ''));
        $platform = strtolower(trim((string) ($input['platform'] ?? $input['device_type'] ?? '')));
        $name = trim((string) ($input['name'] ?? $input['device_name'] ?? ''));

        if ($token === '' || strlen($token) > 255) {
            throw new RuntimeException('Send the device token from the phone.');
        }

        if (! in_array($platform, ['android', 'ios'], true)) {
            throw new RuntimeException('The phone platform must be android or ios.');
        }

        if (! $this->member($tenantId, $userId)) {
            throw new RuntimeException('That person is not in this workspace.');
        }

        $now = date('Y-m-d H:i:s');
        $existing = $this->db->table('mobile_device_tokens')
            ->where('tenant_id', $tenantId)
            ->where('device_token', $token)
            ->get()
            ->getRowArray();
        $data = [
            'user_id'      => $userId,
            'device_type'  => $platform,
            'device_name'  => $name === '' ? null : mb_substr($name, 0, 80),
            'is_active'    => 1,
            'last_used_at' => $now,
            'updated_at'   => $now,
        ];

        if ($existing === null) {
            $data['tenant_id'] = $tenantId;
            $data['device_token'] = $token;
            $data['created_at'] = $now;
            $this->db->table('mobile_device_tokens')->insert($data);

            return;
        }

        $this->db->table('mobile_device_tokens')->where('id', (int) $existing['id'])->update($data);
    }

    public function deactivateDevice(int $tenantId, int $userId, string $token): void
    {
        if (! $this->db->tableExists('mobile_device_tokens')) {
            return;
        }

        $token = trim($token);

        if ($token === '') {
            return;
        }

        $this->db->table('mobile_device_tokens')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('device_token', $token)
            ->update(['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function trigger(int $tenantId, int $actorId, string $eventKey, array $context = []): int
    {
        if (! isset(self::EVENTS[$eventKey]) || $eventKey === 'manual' || ! $this->db->tableExists('notification_templates')) {
            return 0;
        }

        $rules = $this->db->table('notification_templates t')
            ->select('t.*, c.id AS config_id, c.recipient_config_json, c.conditions_json')
            ->join('notification_configurations c', 'c.template_id = t.id AND c.tenant_id = t.tenant_id')
            ->where('t.tenant_id', $tenantId)
            ->where('t.event_key', $eventKey)
            ->where('t.status', 'active')
            ->where('c.is_enabled', 1)
            ->get()
            ->getResultArray();
        $sent = 0;

        foreach ($rules as $rule) {
            $sent += $this->deliver($tenantId, $actorId, $rule, $context);
        }

        return $sent;
    }

    public function remindUser(int $tenantId, int $userId, string $event, string $title, string $message, string $subjectType, int $subjectId): void
    {
        if ($userId < 1 || ! $this->member($tenantId, $userId)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $noticeId = $this->notice($tenantId, $userId, $title, $message, $subjectType, $subjectId);
        $devices = $this->db->tableExists('mobile_device_tokens') ? $this->devices($tenantId, $userId) : [];

        if (! $this->db->tableExists('notification_logs')) {
            foreach ($devices as $device) {
                $this->sendFcm((string) $device['device_token'], $title, $message, [
                    'event'        => $event,
                    'kind'         => 'text',
                    'subject_type' => $subjectType,
                    'subject_id'   => (string) $subjectId,
                ]);
            }

            return;
        }

        $this->ensureMediaColumns();
        $this->db->table('notification_logs')->insert([
            'tenant_id'        => $tenantId,
            'template_id'      => null,
            'event_key'        => $event,
            'kind'             => 'text',
            'media'            => null,
            'title'            => mb_substr($title, 0, 190),
            'message'          => mb_substr($message, 0, 500),
            'subject_type'     => $subjectType,
            'subject_id'       => $subjectId > 0 ? $subjectId : null,
            'triggered_by'     => null,
            'total_recipients' => max(1, count($devices)),
            'successful_count' => 0,
            'failed_count'     => 0,
            'created_at'       => $now,
        ]);
        $logId = (int) $this->db->insertID();
        $sent = 0;
        $failed = 0;

        if ($devices === []) {
            $this->recipientRow($tenantId, $logId, $userId, null, $noticeId, 'failed', null, 'No active device.', null);
            $failed = 1;
        }

        foreach ($devices as $device) {
            $result = $this->sendFcm((string) $device['device_token'], $title, $message, [
                'event'        => $event,
                'kind'         => 'text',
                'subject_type' => $subjectType,
                'subject_id'   => (string) $subjectId,
            ]);

            if (! empty($result['drop'])) {
                $this->db->table('mobile_device_tokens')->where('id', (int) $device['id'])->update(['is_active' => 0, 'updated_at' => $now]);
            }

            $ok = ! empty($result['ok']);
            $this->recipientRow($tenantId, $logId, $userId, (int) $device['id'], $noticeId, $ok ? 'sent' : 'failed', $result['id'] ?? null, $ok ? null : (string) ($result['error'] ?? 'The phone could not be reached.'), $ok ? $now : null);
            $ok ? $sent++ : $failed++;
        }

        $this->db->table('notification_logs')->where('id', $logId)->update([
            'total_recipients' => max(1, $sent + $failed),
            'successful_count' => $sent,
            'failed_count'     => $failed,
        ]);
    }

    /**
     * @param array<string, mixed> $recipients
     * @param array<string, mixed> $conditions
     * @return array<string, mixed>
     */
    public function sendManual(int $tenantId, int $actorId, string $title, string $message, array $recipients, array $conditions, array $content = []): array
    {
        $this->ready();
        $this->ensureMediaColumns();
        $title = trim($title);
        $message = trim($message);
        $piece = $this->content($content);

        if ($title === '') {
            throw new RuntimeException('An announcement needs a title.');
        }

        if ($piece['kind'] === 'text' && $message === '') {
            throw new RuntimeException('An announcement needs text.');
        }

        if ($message === '') {
            $message = $piece['kind'] === 'image' ? 'Open the image.' : 'Open the video.';
        }

        $rule = [
            'id'                    => null,
            'event_key'             => 'manual',
            'title'                 => mb_substr($title, 0, 190),
            'message'               => mb_substr($message, 0, 500),
            'payload_json'          => json_encode($piece),
            'recipient_config_json' => json_encode($this->recipientBag($recipients)),
            'conditions_json'       => json_encode($this->conditions($conditions)),
        ];
        $count = $this->deliver($tenantId, $actorId, $rule, ['subject_type' => 'announcement', 'subject_id' => 0]);

        return ['recipients' => $count, 'kind' => $piece['kind']];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rules(int $tenantId): array
    {
        if (! $this->db->tableExists('notification_templates')) {
            return [];
        }

        $this->ensureMediaColumns();
        $rows = $this->db->table('notification_templates t')
            ->select('t.id, t.notification_name, t.module, t.event_key, t.title, t.message, t.payload_json, t.status, c.recipient_config_json, c.conditions_json, c.is_enabled')
            ->join('notification_configurations c', 'c.template_id = t.id AND c.tenant_id = t.tenant_id', 'left')
            ->where('t.tenant_id', $tenantId)
            ->orderBy('t.id', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
            $row['active'] = ($row['status'] ?? '') === 'active' && (int) ($row['is_enabled'] ?? 0) === 1;
            $row['kind'] = is_array($payload) && in_array($payload['kind'] ?? '', ['text', 'image', 'video'], true) ? $payload['kind'] : 'text';
            $row['media'] = in_array($row['kind'], ['image', 'video'], true) && is_array($payload) ? (string) ($payload['media'] ?? '') : '';
            $row['recipients'] = json_decode((string) ($row['recipient_config_json'] ?? ''), true) ?: $this->recipientBag([]);
            $row['conditions'] = json_decode((string) ($row['conditions_json'] ?? ''), true) ?: $this->conditions([]);
            unset($row['recipient_config_json'], $row['conditions_json'], $row['payload_json'], $row['is_enabled'], $row['status']);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveRule(int $tenantId, int $actorId, ?int $id, array $input): int
    {
        $this->ready();
        $this->ensureMediaColumns();
        $name = trim((string) ($input['name'] ?? $input['notification_name'] ?? ''));
        $event = trim((string) ($input['event_key'] ?? $input['event'] ?? ''));
        $title = trim((string) ($input['title'] ?? ''));
        $message = trim((string) ($input['message'] ?? ''));
        $piece = $this->content($input);

        if ($name === '' || strlen($name) > 120) {
            throw new RuntimeException('Enter a notification name of 120 characters or less.');
        }

        if (! isset(self::EVENTS[$event]) || $event === 'manual') {
            throw new RuntimeException('Choose an event from this workspace.');
        }

        if ($title === '') {
            throw new RuntimeException('Enter the title people will see.');
        }

        if ($piece['kind'] === 'text' && $message === '') {
            throw new RuntimeException('Enter the text people will see.');
        }

        if ($message === '') {
            $message = $piece['kind'] === 'image' ? 'Open the image.' : 'Open the video.';
        }

        $active = ! isset($input['active']) || ! empty($input['active']);
        $now = date('Y-m-d H:i:s');
        $template = [
            'notification_name' => $name,
            'module'            => self::EVENTS[$event]['module'],
            'event_key'         => $event,
            'title'             => mb_substr($title, 0, 190),
            'message'           => mb_substr($message, 0, 500),
            'payload_json'      => json_encode($piece),
            'status'            => $active ? 'active' : 'inactive',
            'updated_at'        => $now,
        ];
        $config = [
            'recipient_type'        => 'combination',
            'recipient_config_json' => json_encode($this->recipientBag(is_array($input['recipients'] ?? null) ? $input['recipients'] : [])),
            'conditions_json'       => json_encode($this->conditions(is_array($input['conditions'] ?? null) ? $input['conditions'] : [])),
            'is_enabled'            => $active ? 1 : 0,
            'updated_at'            => $now,
        ];

        if ($id === null) {
            $template['tenant_id'] = $tenantId;
            $template['created_by'] = $actorId > 0 ? $actorId : null;
            $template['created_at'] = $now;
            $this->db->table('notification_templates')->insert($template);
            $saved = (int) $this->db->insertID();
            $config['tenant_id'] = $tenantId;
            $config['template_id'] = $saved;
            $config['created_at'] = $now;
            $this->db->table('notification_configurations')->insert($config);

            return $saved;
        }

        $existing = $this->db->table('notification_templates')->where('tenant_id', $tenantId)->where('id', $id)->get()->getRowArray();

        if ($existing === null) {
            throw new RuntimeException('That notification is not in this workspace.');
        }

        $this->db->table('notification_templates')->where('id', $id)->update($template);
        $this->db->table('notification_configurations')->where('tenant_id', $tenantId)->where('template_id', $id)->update($config);

        return $id;
    }

    public function deleteRule(int $tenantId, int $id): void
    {
        $this->ready();
        $existing = $this->db->table('notification_templates')->where('tenant_id', $tenantId)->where('id', $id)->get()->getRowArray();

        if ($existing === null) {
            throw new RuntimeException('That notification is not in this workspace.');
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('notification_templates')->where('id', $id)->update(['status' => 'inactive', 'updated_at' => $now]);
        $this->db->table('notification_configurations')->where('tenant_id', $tenantId)->where('template_id', $id)->update(['is_enabled' => 0, 'updated_at' => $now]);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(int $tenantId): array
    {
        if (! $this->db->tableExists('notification_logs')) {
            return ['total' => 0, 'sent' => 0, 'failed' => 0, 'recent' => []];
        }

        $row = $this->db->table('notification_logs')
            ->select('COUNT(*) AS total, COALESCE(SUM(successful_count), 0) AS sent, COALESCE(SUM(failed_count), 0) AS failed', false)
            ->where('tenant_id', $tenantId)
            ->get()
            ->getRowArray();

        return [
            'total'  => (int) ($row['total'] ?? 0),
            'sent'   => (int) ($row['sent'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
            'recent' => $this->history($tenantId, 8),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(int $tenantId, int $limit = 300): array
    {
        if (! $this->db->tableExists('notification_logs')) {
            return [];
        }

        $this->ensureMediaColumns();

        return $this->db->table('notification_logs l')
            ->select('l.id, l.event_key, l.kind, l.media, l.title, l.message, l.subject_type, l.subject_id, l.total_recipients, l.successful_count, l.failed_count, l.created_at, u.name AS triggered_by_name')
            ->join('users u', 'u.id = l.triggered_by', 'left')
            ->where('l.tenant_id', $tenantId)
            ->orderBy('l.id', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recipients(int $tenantId, int $logId): array
    {
        if (! $this->db->tableExists('notification_recipients')) {
            return [];
        }

        $log = $this->db->table('notification_logs')->where('tenant_id', $tenantId)->where('id', $logId)->get()->getRowArray();

        if ($log === null) {
            throw new RuntimeException('That notification is not in this workspace.');
        }

        return $this->db->table('notification_recipients r')
            ->select('r.user_id, u.name AS user_name, r.delivery_status, r.error_message, r.sent_at, r.read_at')
            ->join('users u', 'u.id = r.user_id', 'left')
            ->where('r.tenant_id', $tenantId)
            ->where('r.notification_log_id', $logId)
            ->orderBy('u.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function markRead(int $tenantId, int $userId, int $noticeId): void
    {
        if (! $this->db->tableExists('notification_recipients') || $noticeId < 1) {
            return;
        }

        $this->db->table('notification_recipients')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('notice_id', $noticeId)
            ->update(['read_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function options(int $tenantId): array
    {
        $events = [];

        foreach (self::EVENTS as $key => $event) {
            if ($key === 'manual') {
                continue;
            }

            $events[] = ['key' => $key, 'module' => $event['module'], 'label' => $event['label']];
        }

        $staff = $this->db->table('tenant_users tu')
            ->select('u.id AS user_id, u.name, r.name AS role_name, sp.designation, sp.status, sp.team_id')
            ->join('users u', 'u.id = tu.user_id')
            ->join('roles r', 'r.id = tu.role_id')
            ->join('staff_profiles sp', 'sp.user_id = tu.user_id AND sp.tenant_id = tu.tenant_id AND sp.deleted_at IS NULL', 'left')
            ->where('tu.tenant_id', $tenantId)
            ->where('u.deleted_at', null)
            ->orderBy('u.name', 'ASC')
            ->get()
            ->getResultArray();
        $teams = $this->db->tableExists('teams')
            ? $this->db->table('teams')->select('id, name')->where('tenant_id', $tenantId)->where('deleted_at', null)->orderBy('name', 'ASC')->get()->getResultArray()
            : [];
        $roles = $this->db->table('roles')->select('id, name')->where('tenant_id', $tenantId)->where('deleted_at', null)->orderBy('name', 'ASC')->get()->getResultArray();
        $designations = $this->db->tableExists('lookup_items')
            ? $this->db->table('lookup_items')->select('code, label')->where('tenant_id', $tenantId)->where('kind', 'designation')->where('deleted_at', null)->where('active', 1)->orderBy('sort_order', 'ASC')->get()->getResultArray()
            : [];
        $projects = $this->db->tableExists('projects')
            ? $this->db->table('projects')->select('id, name')->where('tenant_id', $tenantId)->where('deleted_at', null)->orderBy('name', 'ASC')->get()->getResultArray()
            : [];

        return [
            'events'       => $events,
            'staff'        => $staff,
            'teams'        => $teams,
            'roles'        => $roles,
            'designations' => $designations,
            'projects'     => $projects,
            'push'         => $this->serviceAccount() !== null,
        ];
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $context
     */
    private function deliver(int $tenantId, int $actorId, array $rule, array $context): int
    {
        $recipients = json_decode((string) ($rule['recipient_config_json'] ?? ''), true);
        $conditions = json_decode((string) ($rule['conditions_json'] ?? ''), true);
        $userIds = $this->resolve($tenantId, is_array($recipients) ? $recipients : [], is_array($conditions) ? $conditions : [], $context);
        $event = (string) ($rule['event_key'] ?? 'manual');
        $subjectType = (string) ($context['subject_type'] ?? '');
        $subjectId = (int) ($context['subject_id'] ?? 0);
        $title = $this->fill((string) ($rule['title'] ?? ''), $context);
        $message = $this->fill((string) ($rule['message'] ?? ''), $context);
        $payload = json_decode((string) ($rule['payload_json'] ?? ''), true);
        $kind = is_array($payload) && in_array($payload['kind'] ?? '', ['text', 'image', 'video'], true) ? $payload['kind'] : 'text';
        $media = in_array($kind, ['image', 'video'], true) ? (string) ($payload['media'] ?? '') : '';
        $now = date('Y-m-d H:i:s');
        $this->ensureMediaColumns();
        $this->db->table('notification_logs')->insert([
            'tenant_id'        => $tenantId,
            'template_id'      => isset($rule['id']) ? (int) $rule['id'] : null,
            'event_key'        => $event,
            'kind'             => $kind,
            'media'            => $media !== '' ? mb_substr($media, 0, 500) : null,
            'title'            => mb_substr($title, 0, 190),
            'message'          => mb_substr($message, 0, 500),
            'subject_type'     => $subjectType !== '' ? $subjectType : null,
            'subject_id'       => $subjectId > 0 ? $subjectId : null,
            'triggered_by'     => $actorId > 0 ? $actorId : null,
            'total_recipients' => 0,
            'successful_count' => 0,
            'failed_count'     => 0,
            'created_at'       => $now,
        ]);
        $logId = (int) $this->db->insertID();
        $sent = 0;
        $failed = 0;
        $rows = 0;

        foreach ($userIds as $userId) {
            if ($event !== 'manual' && $subjectId > 0 && $this->duplicate($tenantId, $event, $subjectType, $subjectId, $userId, $logId)) {
                continue;
            }

            $noticeId = $this->notice($tenantId, $userId, $title, $message, $subjectType, $subjectId, $kind, $media);
            $devices = $this->devices($tenantId, $userId);

            if ($devices === []) {
                $this->recipientRow($tenantId, $logId, $userId, null, $noticeId, 'failed', null, 'No active device.', null);
                $failed++;
                $rows++;
                continue;
            }

            foreach ($devices as $device) {
                $result = $this->sendFcm((string) $device['device_token'], $title, $message, [
                    'event'        => $event,
                    'kind'         => $kind,
                    'media'        => $media,
                    'subject_type' => $subjectType,
                    'subject_id'   => (string) $subjectId,
                ]);

                if (! empty($result['drop'])) {
                    $this->db->table('mobile_device_tokens')->where('id', (int) $device['id'])->update(['is_active' => 0, 'updated_at' => $now]);
                }

                $ok = ! empty($result['ok']);
                $this->recipientRow($tenantId, $logId, $userId, (int) $device['id'], $noticeId, $ok ? 'sent' : 'failed', $result['id'] ?? null, $ok ? null : (string) ($result['error'] ?? 'The phone could not be reached.'), $ok ? $now : null);
                $rows++;
                $ok ? $sent++ : $failed++;
            }
        }

        $this->db->table('notification_logs')->where('id', $logId)->update([
            'total_recipients' => $rows,
            'successful_count' => $sent,
            'failed_count'     => $failed,
        ]);

        return count($userIds);
    }

    /**
     * @param array<string, mixed> $rules
     * @param array<string, mixed> $conditions
     * @param array<string, mixed> $context
     * @return list<int>
     */
    private function resolve(int $tenantId, array $rules, array $conditions, array $context): array
    {
        $ids = [];
        $subject = (int) ($context['subject_user_id'] ?? 0);

        if (! empty($rules['subject']) && $subject > 0) {
            $ids[] = $subject;
        }

        if (! empty($rules['subject_team']) && $subject > 0) {
            $teamId = (int) ($this->profile($tenantId, $subject)['team_id'] ?? 0);

            if ($teamId > 0) {
                $ids = array_merge($ids, $this->teamMembers($tenantId, [$teamId]));
                $head = $this->teamHead($tenantId, $subject);

                if ($head > 0) {
                    $ids[] = $head;
                }
            }
        }

        if (! empty($rules['team_head']) && $subject > 0) {
            $head = $this->teamHead($tenantId, $subject);

            if ($head > 0) {
                $ids[] = $head;
            }
        }

        foreach ($this->ints($rules['users'] ?? []) as $id) {
            $ids[] = $id;
        }

        $teamIds = $this->ints($rules['teams'] ?? []);

        if ($teamIds !== []) {
            $ids = array_merge($ids, $this->teamMembers($tenantId, $teamIds));

            if (! empty($rules['team_head'])) {
                $heads = $this->db->table('teams')->select('head_user_id')->where('tenant_id', $tenantId)->whereIn('id', $teamIds)->where('deleted_at', null)->get()->getResultArray();

                foreach ($heads as $head) {
                    if ((int) ($head['head_user_id'] ?? 0) > 0) {
                        $ids[] = (int) $head['head_user_id'];
                    }
                }
            }
        }

        foreach ($this->roleMembers($tenantId, $this->ints($rules['roles'] ?? [])) as $id) {
            $ids[] = $id;
        }

        foreach ($this->designationMembers($tenantId, $this->codes($rules['designations'] ?? [])) as $id) {
            $ids[] = $id;
        }

        if (! empty($rules['all_active'])) {
            $ids = array_merge($ids, $this->activeIds($tenantId));
        }

        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        $activeOnly = ! array_key_exists('active_only', $conditions) || ! empty($conditions['active_only']);
        $projectId = (int) ($conditions['project_id'] ?? 0);
        $designation = trim((string) ($conditions['designation'] ?? ''));
        $managerId = (int) ($conditions['manager_user_id'] ?? 0);
        $projectTeams = $projectId > 0 ? $this->projectTeams($tenantId, $projectId) : null;
        $kept = [];

        foreach ($ids as $id) {
            if (! $this->member($tenantId, $id)) {
                continue;
            }

            $profile = $this->profile($tenantId, $id);

            if ($activeOnly && ! $this->isActive($profile)) {
                continue;
            }

            if ($designation !== '' && (string) ($profile['designation'] ?? '') !== $designation) {
                continue;
            }

            if ($managerId > 0 && $this->teamHead($tenantId, $id) !== $managerId) {
                continue;
            }

            if ($projectTeams !== null) {
                $teamId = (int) ($profile['team_id'] ?? 0);

                if (! in_array($teamId, $projectTeams, true)) {
                    continue;
                }
            }

            $kept[] = $id;
        }

        return $kept;
    }

    /**
     * @param list<int> $teamIds
     * @return list<int>
     */
    private function teamMembers(int $tenantId, array $teamIds): array
    {
        if ($teamIds === [] || ! $this->db->tableExists('staff_profiles')) {
            return [];
        }

        $rows = $this->db->table('staff_profiles')
            ->select('user_id')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->whereIn('team_id', $teamIds)
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): int => (int) $row['user_id'], $rows);
    }

    /**
     * @param list<int> $roleIds
     * @return list<int>
     */
    private function roleMembers(int $tenantId, array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $rows = $this->db->table('tenant_users')
            ->select('user_id')
            ->where('tenant_id', $tenantId)
            ->whereIn('role_id', $roleIds)
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): int => (int) $row['user_id'], $rows);
    }

    /**
     * @param list<string> $codes
     * @return list<int>
     */
    private function designationMembers(int $tenantId, array $codes): array
    {
        if ($codes === [] || ! $this->db->tableExists('staff_profiles')) {
            return [];
        }

        $rows = $this->db->table('staff_profiles')
            ->select('user_id')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->whereIn('designation', $codes)
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): int => (int) $row['user_id'], $rows);
    }

    /**
     * @return list<int>
     */
    private function activeIds(int $tenantId): array
    {
        $rows = $this->db->table('tenant_users tu')
            ->select('tu.user_id')
            ->join('users u', 'u.id = tu.user_id')
            ->where('tu.tenant_id', $tenantId)
            ->where('u.deleted_at', null)
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): int => (int) $row['user_id'], $rows);
    }

    private function teamHead(int $tenantId, int $userId): int
    {
        if ($userId < 1 || ! $this->db->tableExists('staff_profiles') || ! $this->db->tableExists('teams')) {
            return 0;
        }

        $profile = $this->profile($tenantId, $userId);
        $teamId = (int) ($profile['team_id'] ?? 0);

        if ($teamId < 1) {
            return 0;
        }

        $team = $this->db->table('teams')->select('head_user_id')->where('tenant_id', $tenantId)->where('id', $teamId)->where('deleted_at', null)->get()->getRowArray();

        return (int) ($team['head_user_id'] ?? 0);
    }

    /**
     * @return list<int>
     */
    private function projectTeams(int $tenantId, int $projectId): array
    {
        if (! $this->db->tableExists('team_projects')) {
            return [];
        }

        $rows = $this->db->table('team_projects')->select('team_id')->where('tenant_id', $tenantId)->where('project_id', $projectId)->get()->getResultArray();

        return array_map(static fn (array $row): int => (int) $row['team_id'], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(int $tenantId, int $userId): array
    {
        if (! $this->db->tableExists('staff_profiles')) {
            return [];
        }

        $row = $this->db->table('staff_profiles')
            ->select('user_id, team_id, designation, status, relieved_on')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return $row ?? [];
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function isActive(array $profile): bool
    {
        if ($profile === []) {
            return true;
        }

        if (in_array((string) ($profile['status'] ?? 'active'), ['inactive', 'relieved'], true)) {
            return false;
        }

        return empty($profile['relieved_on']);
    }

    private function member(int $tenantId, int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }

        return $this->db->table('tenant_users')->where('tenant_id', $tenantId)->where('user_id', $userId)->countAllResults() > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function devices(int $tenantId, int $userId): array
    {
        return $this->db->table('mobile_device_tokens')
            ->select('id, device_token')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->get()
            ->getResultArray();
    }

    private function duplicate(int $tenantId, string $event, string $subjectType, int $subjectId, int $userId, int $logId): bool
    {
        $since = date('Y-m-d H:i:s', time() - 600);
        $row = $this->db->table('notification_recipients r')
            ->select('r.id')
            ->join('notification_logs l', 'l.id = r.notification_log_id')
            ->where('r.tenant_id', $tenantId)
            ->where('r.user_id', $userId)
            ->where('l.event_key', $event)
            ->where('l.subject_type', $subjectType)
            ->where('l.subject_id', $subjectId)
            ->where('l.id !=', $logId)
            ->where('l.created_at >=', $since)
            ->get()
            ->getRowArray();

        return $row !== null;
    }

    private function notice(int $tenantId, int $userId, string $title, string $message, string $subjectType, int $subjectId, string $kind = 'text', string $media = ''): int
    {
        if (! $this->db->tableExists('notifications')) {
            return 0;
        }

        $this->ensureMediaColumns();
        $this->db->table('notifications')->insert([
            'tenant_id'  => $tenantId,
            'user_id'    => $userId,
            'title'      => mb_substr($title, 0, 190),
            'body'       => mb_substr($message, 0, 255),
            'kind'       => $kind,
            'media'      => $media !== '' ? mb_substr($media, 0, 500) : null,
            'href'       => $subjectType !== '' && $subjectId > 0 ? $subjectType . ':' . $subjectId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * @param array<string, mixed> $input
     * @return array{kind: string, media: ?string}
     */
    private function content(array $input): array
    {
        $kind = strtolower(trim((string) ($input['kind'] ?? $input['type'] ?? 'text')));

        if (! in_array($kind, ['text', 'image', 'video'], true)) {
            throw new RuntimeException('A notification can be text, image, or video.');
        }

        if ($kind === 'text') {
            return ['kind' => 'text', 'media' => null];
        }

        $media = trim((string) ($input['media'] ?? ''));
        $local = $kind === 'image'
            ? preg_match('#^/api/v1/notifications/media/[a-f0-9]{32}\.(jpg|png|webp|gif)$#', $media) === 1
            : preg_match('#^/api/v1/notifications/media/[a-f0-9]{32}\.(mp4|webm|mov)$#', $media) === 1;
        $remote = filter_var($media, FILTER_VALIDATE_URL) !== false && preg_match('#^https://#i', $media) === 1;

        if ($media === '' || strlen($media) > 500 || (! $local && ! $remote)) {
            throw new RuntimeException($kind === 'image'
                ? 'Add an https image link, or upload a PNG, JPG, WebP, or GIF.'
                : 'Add an https video link, or upload an MP4, WebM, or MOV file.');
        }

        return ['kind' => $kind, 'media' => $media];
    }

    public function ensureMediaColumns(): void
    {
        $forge = \Config\Database::forge();

        foreach (['notification_logs', 'notifications'] as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            $columns = [];

            if (! $this->db->fieldExists('kind', $table)) {
                $columns['kind'] = ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'text'];
            }

            if (! $this->db->fieldExists('media', $table)) {
                $columns['media'] = ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true];
            }

            if ($columns === []) {
                continue;
            }

            $forge->addColumn($table, $columns);
            $this->db->resetDataCache();
        }
    }

    private function recipientRow(int $tenantId, int $logId, int $userId, ?int $deviceId, int $noticeId, string $status, ?string $messageId, ?string $error, ?string $sentAt): void
    {
        $this->db->table('notification_recipients')->insert([
            'tenant_id'           => $tenantId,
            'notification_log_id' => $logId,
            'user_id'             => $userId,
            'device_token_id'     => $deviceId,
            'notice_id'           => $noticeId > 0 ? $noticeId : null,
            'delivery_status'     => $status,
            'fcm_message_id'      => $messageId,
            'error_message'       => $error !== null ? mb_substr($error, 0, 255) : null,
            'sent_at'             => $sentAt,
        ]);
    }

    /**
     * @param array<string, string> $data
     * @return array{ok: bool, id: ?string, error: ?string, drop: bool}
     */
    private function sendFcm(string $token, string $title, string $body, array $data): array
    {
        $account = $this->serviceAccount();

        if ($account === null) {
            return ['ok' => false, 'id' => null, 'error' => 'Push delivery is not connected.', 'drop' => false];
        }

        try {
            $access = $this->googleToken($account);
        } catch (RuntimeException $e) {
            return ['ok' => false, 'id' => null, 'error' => $e->getMessage(), 'drop' => false];
        }

        $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode((string) $account['project_id']) . '/messages:send';
        $payload = [
            'message' => [
                'token'        => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data'         => $data,
            ],
        ];
        $response = $this->http('POST', $url, [
            'Authorization: Bearer ' . $access,
            'Content-Type: application/json',
        ], json_encode($payload, JSON_THROW_ON_ERROR));
        $status = $response['status'];
        $json = json_decode($response['body'], true);
        $name = (string) ($json['name'] ?? '');

        if ($status >= 200 && $status < 300 && $name !== '') {
            return ['ok' => true, 'id' => $name, 'error' => null, 'drop' => false];
        }

        $code = (string) ($json['error']['status'] ?? '');
        $drop = in_array($code, ['NOT_FOUND', 'INVALID_ARGUMENT', 'UNREGISTERED'], true);

        return ['ok' => false, 'id' => null, 'error' => 'The phone rejected the message.', 'drop' => $drop];
    }

    /**
     * @param array<string, mixed> $account
     */
    private function googleToken(array $account): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $now = time();
        $header = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claim = $this->b64(json_encode([
            'iss'   => $account['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        $unsigned = $header . '.' . $claim;
        $signature = '';

        if (! openssl_sign($unsigned, $signature, (string) $account['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Push delivery is not connected.');
        }

        $response = $this->http('POST', 'https://oauth2.googleapis.com/token', [
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $unsigned . '.' . $this->b64($signature),
        ]));
        $json = json_decode($response['body'], true);
        $token = (string) ($json['access_token'] ?? '');

        if ($token === '') {
            throw new RuntimeException('Push delivery is not connected.');
        }

        $this->accessToken = $token;

        return $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serviceAccount(): ?array
    {
        $path = trim((string) env('fcm.credentials'));

        if ($path === '') {
            return null;
        }

        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            $path = ROOTPATH . $path;
        }

        if (! is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);

        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key']) || empty($json['project_id'])) {
            return null;
        }

        return $json;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function fill(string $text, array $context): string
    {
        $map = [
            '{name}'  => (string) ($context['name'] ?? ''),
            '{lead}'  => (string) ($context['lead'] ?? ''),
            '{task}'  => (string) ($context['task'] ?? ''),
            '{place}' => (string) ($context['place'] ?? ''),
            '{actor}' => (string) ($context['actor'] ?? ''),
        ];

        return trim(strtr($text, $map));
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function recipientBag(array $input): array
    {
        return [
            'subject'      => ! empty($input['subject']),
            'subject_team' => ! empty($input['subject_team']),
            'team_head'    => ! empty($input['team_head']),
            'all_active'   => ! empty($input['all_active']),
            'users'        => $this->ints($input['users'] ?? []),
            'teams'        => $this->ints($input['teams'] ?? []),
            'roles'        => $this->ints($input['roles'] ?? []),
            'designations' => $this->codes($input['designations'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function conditions(array $input): array
    {
        return [
            'active_only'      => ! array_key_exists('active_only', $input) || ! empty($input['active_only']),
            'project_id'       => (int) ($input['project_id'] ?? 0),
            'designation'      => trim((string) ($input['designation'] ?? '')),
            'manager_user_id'  => (int) ($input['manager_user_id'] ?? 0),
        ];
    }

    /**
     * @return list<int>
     */
    private function ints(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $ids = [];

        foreach ($values as $value) {
            $id = (int) $value;

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<string>
     */
    private function codes(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $codes = [];

        foreach ($values as $value) {
            $code = trim((string) $value);

            if ($code !== '' && strlen($code) <= 40) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, body: string}
     */
    private function http(string $method, string $url, array $headers, string $body): array
    {
        if (! function_exists('curl_init')) {
            return ['status' => 0, 'body' => ''];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => (string) $raw];
    }

    private function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function ready(): void
    {
        if (! $this->db->tableExists('mobile_device_tokens') || ! $this->db->tableExists('notification_templates')) {
            throw new RuntimeException('Notification tables are not installed yet.');
        }
    }
}
