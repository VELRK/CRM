<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class SiteService
{
    public const PROJECT_STATUSES = ['upcoming', 'ongoing', 'completed', 'planning', 'active'];
    public const PLOT_STATUSES    = ['available', 'advance', 'hold', 'booked', 'sold', 'owner_use', 'future', 'registered', 'blocked'];
    public const LEAD_STATUSES    = ['new', 'contacted', 'followup', 'site_visit', 'site_visit_done', 'negotiation', 'booked', 'won', 'lost', 'not_interested'];
    public const VISIT_STATUSES   = ['scheduled', 'completed', 'cancelled', 'rescheduled', 'no_show', 'planned', 'attended', 'missed'];
    public const SALE_STATUSES    = ['booked', 'sold', 'registered'];

    private bool $commissionReady = false;

    public function __construct(private BaseConnection $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function staff(int $tenantId): array
    {
        return $this->db->table('tenant_users tu')
            ->select('u.id AS user_id, u.name, u.email, r.name AS role_name, r.id AS role_id, sp.designation, sp.phone, sp.phone_number, sp.profile, sp.status, sp.employee_code, sp.joining_on, sp.address, sp.street, sp.city, sp.state, sp.zip_code, sp.dob, sp.attendance_applicable, sp.lead_eligible, sp.incentive_eligible, sp.relieved_on, sp.team_id, sp.group_id, sp.user_status, sp.active')
            ->join('users u', 'u.id = tu.user_id')
            ->join('roles r', 'r.id = tu.role_id')
            ->join('staff_profiles sp', 'sp.user_id = tu.user_id AND sp.tenant_id = tu.tenant_id AND sp.deleted_at IS NULL', 'left')
            ->where('tu.tenant_id', $tenantId)
            ->orderBy('u.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveStaff(int $tenantId, int $userId, array $input): void
    {
        if (! in_array($userId, $this->memberIds($tenantId), true)) {
            throw new RuntimeException('That person is not on this workspace.');
        }

        $status = (string) ($input['status'] ?? 'active');

        if (! in_array($status, ['invited', 'active', 'inactive', 'relieved'], true)) {
            throw new RuntimeException('Choose a staff status.');
        }

        $relievedOn = trim((string) ($input['relieved_on'] ?? ''));

        if ($status === 'relieved' && $relievedOn === '') {
            throw new RuntimeException('A relieved staff member needs a relieving date.');
        }

        $now         = date('Y-m-d H:i:s');
        $designation = $this->blank($input['designation'] ?? null);
        $existing = $this->db->table('staff_profiles')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        if ($designation !== null && $this->db->tableExists('lookup_items')) {
            $known = $this->db->table('lookup_items')
                ->where('tenant_id', $tenantId)
                ->where('kind', 'designation')
                ->where('code', $designation)
                ->where('deleted_at', null)
                ->where('active', 1)
                ->countAllResults();

            if ($known < 1 && $designation !== (string) ($existing['designation'] ?? '')) {
                throw new RuntimeException('Choose a designation from the list.');
            }
        }

        $data = [
            'designation'            => $designation,
            'phone'                  => $this->blank($input['phone'] ?? null),
            'status'                 => $status,
            'employee_code'          => $this->blank($input['employee_code'] ?? null),
            'joining_on'             => $this->day($input['joining_on'] ?? null),
            'address'                => $this->blank($input['address'] ?? null),
            'attendance_applicable'  => empty($input['attendance_applicable']) ? 0 : 1,
            'lead_eligible'          => empty($input['lead_eligible']) ? 0 : 1,
            'incentive_eligible'     => empty($input['incentive_eligible']) ? 0 : 1,
            'relieved_on'            => $status === 'relieved' ? $relievedOn : null,
            'team_id'                => (int) ($input['team_id'] ?? 0) > 0 ? (int) $input['team_id'] : null,
            'updated_at'             => $now,
            'deleted_at'             => null,
        ];

        if ($existing === null) {
            $data['tenant_id']  = $tenantId;
            $data['user_id']    = $userId;
            $data['created_at'] = $now;
            $this->db->table('staff_profiles')->insert($data);

            return;
        }

        $this->db->table('staff_profiles')->where('id', (int) $existing['id'])->update($data);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function assignable(int $tenantId): array
    {
        $people = [];

        foreach ($this->staff($tenantId) as $person) {
            if (! in_array((string) ($person['status'] ?? 'active'), ['inactive', 'relieved', 'invited'], true)) {
                $person['status'] = $person['status'] ?: 'active';
                $people[]         = $person;
            }
        }

        return $people;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function projects(int $tenantId): array
    {
        return $this->db->table('projects')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function project(int $tenantId, int $id): ?array
    {
        $row = $this->db->table('projects')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveProject(int $tenantId, ?int $id, array $input): int
    {
        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('A project needs a name.');
        }

        $status = (string) ($input['status'] ?? 'upcoming');

        if (! in_array($status, self::PROJECT_STATUSES, true)) {
            throw new RuntimeException('Choose a project status.');
        }

        if ($id !== null && $this->project($tenantId, $id) === null) {
            throw new RuntimeException('That project is not in this workspace.');
        }

        $now  = date('Y-m-d H:i:s');
        $data = [
            'name'       => $name,
            'location'     => $this->blank($input['location'] ?? null),
            'status'       => $status,
            'notes'        => $this->blank($input['notes'] ?? null),
            'project_type' => $this->blank($input['project_type'] ?? null),
            'total_plots'  => (int) ($input['total_plots'] ?? 0) > 0 ? (int) $input['total_plots'] : null,
            'launch_on'    => $this->day($input['launch_on'] ?? null),
            'updated_at'   => $now,
        ];

        if ($id === null) {
            $data['tenant_id']  = $tenantId;
            $data['created_at'] = $now;
            $this->db->table('projects')->insert($data);

            return (int) $this->db->insertID();
        }

        $this->db->table('projects')->where('id', $id)->update($data);

        return $id;
    }

    public function deleteProject(int $tenantId, int $id): void
    {
        if ($this->project($tenantId, $id) === null) {
            throw new RuntimeException('That project is not in this workspace.');
        }

        $plots = $this->db->table('plots')
            ->where('tenant_id', $tenantId)
            ->where('project_id', $id)
            ->where('deleted_at', null)
            ->countAllResults();

        if ($plots > 0) {
            throw new RuntimeException('Remove the plots in this project first.');
        }

        $this->db->table('projects')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function plots(int $tenantId, int $projectId = 0): array
    {
        $this->ensureCommissionColumn();
        $builder = $this->db->table('plots p')
            ->select('p.*, pr.name AS project_name, a.name AS associate_name, h.name AS head_name')
            ->join('projects pr', 'pr.id = p.project_id')
            ->join('users a', 'a.id = p.associate_user_id', 'left')
            ->join('users h', 'h.id = p.head_user_id', 'left')
            ->where('p.tenant_id', $tenantId)
            ->where('p.deleted_at', null)
            ->where('pr.deleted_at', null);

        if ($projectId > 0) {
            $builder->where('p.project_id', $projectId);
        }

        $rows = $builder->orderBy('pr.name', 'ASC')->orderBy('p.plot_no', 'ASC')->get()->getResultArray();

        return array_map(fn (array $row): array => $this->decoratePlot($row), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function plot(int $tenantId, int $id): ?array
    {
        $this->ensureCommissionColumn();
        $row = $this->db->table('plots')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function savePlot(int $tenantId, ?int $id, array $input): int
    {
        $this->ensureCommissionColumn();
        $projectId = (int) ($input['project_id'] ?? 0);

        if ($this->project($tenantId, $projectId) === null) {
            throw new RuntimeException('Choose a project for this plot.');
        }

        $plotNo = trim((string) ($input['plot_no'] ?? ''));

        if ($plotNo === '') {
            throw new RuntimeException('A plot needs a number.');
        }

        $status = (string) ($input['status'] ?? 'available');

        if (! in_array($status, self::PLOT_STATUSES, true)) {
            throw new RuntimeException('Choose a plot status.');
        }

        $block = trim((string) ($input['block'] ?? ''));
        $area  = trim((string) ($input['area_sqft'] ?? ''));

        if ($area !== '' && (! is_numeric($area) || (float) $area < 0)) {
            throw new RuntimeException('Enter the area in square feet.');
        }

        $previousStatus = null;

        if ($id !== null) {
            $current = $this->plot($tenantId, $id);

            if ($current === null) {
                throw new RuntimeException('That plot is not in this workspace.');
            }

            $previousStatus = (string) $current['status'];
        }

        $duplicate = $this->db->table('plots')
            ->where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->where('plot_no', $plotNo)
            ->where('block', $block)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($duplicate !== null && (int) $duplicate['id'] !== (int) $id) {
            throw new RuntimeException('That plot number is already in this project.');
        }

        $now  = date('Y-m-d H:i:s');
        $data = [
            'project_id'  => $projectId,
            'plot_no'     => $plotNo,
            'block'       => $block,
            'area_sqft'   => $area === '' ? null : number_format((float) $area, 2, '.', ''),
            'phase'       => $this->blank($input['phase'] ?? null),
            'facing'      => $this->blank($input['facing'] ?? null),
            'unit'        => $this->blank($input['unit'] ?? null) ?? 'sqft',
            'price_per_unit_minor' => BookService::rupeesToMinor((string) ($input['price_per_unit'] ?? '')),
            'price_minor' => BookService::rupeesToMinor((string) ($input['price'] ?? '')),
            'commission_minor' => $this->commissionMinor($input),
            'status'      => $status,
            'updated_at'  => $now,
        ];

        if ($id === null) {
            $data['tenant_id']  = $tenantId;
            $data['created_at'] = $now;
            $this->db->table('plots')->insert($data);
            $id = (int) $this->db->insertID();
        } else {
            $this->db->table('plots')->where('id', $id)->update($data);
        }

        $this->logPlotStatus($tenantId, $id, $previousStatus, $status, (int) ($input['by_user_id'] ?? 0), (string) ($input['status_remark'] ?? ''));

        return $id;
    }

    public function deletePlot(int $tenantId, int $id): void
    {
        if ($this->plot($tenantId, $id) === null) {
            throw new RuntimeException('That plot is not in this workspace.');
        }

        $this->db->table('plots')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveBooking(int $tenantId, int $id, array $input): void
    {
        $this->ensureCommissionColumn();
        $plot = $this->plot($tenantId, $id);

        if ($plot === null) {
            throw new RuntimeException('That plot is not in this workspace.');
        }

        $status = (string) ($input['status'] ?? $plot['status']);

        if (! in_array($status, self::PLOT_STATUSES, true)) {
            throw new RuntimeException('Choose a plot status.');
        }

        $phone = preg_replace('/\D+/', '', (string) ($input['customerPhone'] ?? $input['customer_phone'] ?? '')) ?? '';

        if ($phone !== '' && strlen($phone) !== 10) {
            throw new RuntimeException('Contact number must be 10 digits.');
        }

        $associate = (int) ($input['associateUserId'] ?? $input['associate_user_id'] ?? 0);
        $head      = (int) ($input['headUserId'] ?? $input['head_user_id'] ?? 0);
        $leadId    = (int) ($input['leadId'] ?? $input['lead_id'] ?? 0);
        $saleLocked = $this->db->tableExists('sales') && $this->db->table('sales')
            ->where('tenant_id', $tenantId)
            ->where('plot_id', $id)
            ->where('deleted_at', null)
            ->where('status', 'active')
            ->countAllResults() > 0;

        if ($saleLocked) {
            $associate = (int) ($plot['associate_user_id'] ?? 0);
            $head = (int) ($plot['head_user_id'] ?? 0);
        } elseif ($leadId > 0 && in_array($status, self::SALE_STATUSES, true)) {
            $lead = $this->lead($tenantId, $leadId);
            $ownerId = (int) ($lead['owner_id'] ?? 0);

            if ($ownerId > 0) {
                $associate = $ownerId;
                $head = 0;
            }
        }

        if ($associate > 0 && ! in_array($associate, $this->memberIds($tenantId), true)) {
            throw new RuntimeException('Choose an associate in this workspace.');
        }

        if ($associate > 0 && $head < 1) {
            $profile = $this->db->table('staff_profiles')->select('team_id')->where('tenant_id', $tenantId)->where('user_id', $associate)->get()->getRowArray();
            $teamId  = (int) ($profile['team_id'] ?? 0);

            if ($teamId > 0) {
                $team = $this->db->table('teams')->select('head_user_id')->where('tenant_id', $tenantId)->where('id', $teamId)->where('deleted_at', null)->get()->getRowArray();
                $head = (int) ($team['head_user_id'] ?? 0);
            }
        }

        $customer = $this->blank($input['customerName'] ?? $input['customer_name'] ?? null);

        if (in_array($status, ['advance', 'booked', 'sold'], true) && $customer === null) {
            throw new RuntimeException('A booking needs the customer name.');
        }

        $previous = (string) $plot['status'];
        $this->db->table('plots')->where('id', $id)->update([
            'status'                   => $status,
            'customer_name'            => $customer,
            'customer_phone'           => $phone === '' ? null : $phone,
            'associate_user_id'        => $associate > 0 ? $associate : null,
            'head_user_id'             => $head > 0 ? $head : null,
            'booking_on'               => $this->blank($input['bookingOn'] ?? $input['booking_on'] ?? null),
            'advance_minor'            => BookService::rupeesToMinor((string) ($input['advance'] ?? '0')),
            'advance_received_on'      => $this->blank($input['advanceReceivedOn'] ?? $input['advance_received_on'] ?? null),
            'booking_amount_minor'     => BookService::rupeesToMinor((string) ($input['bookingAmount'] ?? '0')),
            'commission_minor'         => $this->commissionMinor($input),
            'expected_registration_on' => $this->blank($input['expectedRegistrationOn'] ?? $input['expected_registration_on'] ?? null),
            'registered_on'            => $this->blank($input['registeredOn'] ?? $input['registered_on'] ?? null),
            'remarks'                  => $this->blank($input['remarks'] ?? null),
            'updated_at'               => date('Y-m-d H:i:s'),
        ]);
        $this->logPlotStatus($tenantId, $id, $previous, $status, (int) ($input['by_user_id'] ?? 0), (string) ($input['remarks'] ?? ''));
    }

    public function ensureCommissionColumn(): void
    {
        if ($this->commissionReady) {
            return;
        }

        if (! $this->db->fieldExists('commission_minor', 'plots')) {
            \Config\Database::forge()->addColumn('plots', [
                'commission_minor' => ['type' => 'BIGINT', 'default' => 0],
            ]);
            $this->db->resetDataCache();
        }

        $this->commissionReady = true;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function commissionMinor(array $input): int
    {
        $raw = str_replace([',', '₹', ' '], '', trim((string) ($input['commission'] ?? '')));

        if ($raw === '' || ! is_numeric($raw) || (float) $raw <= 0) {
            throw new RuntimeException('Enter the commission for this plot.');
        }

        return BookService::rupeesToMinor($raw);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decoratePlot(array $row): array
    {
        $today    = strtotime(date('Y-m-d'));
        $booking  = $this->dayStamp($row['booking_on'] ?? null);
        $received = $this->dayStamp($row['advance_received_on'] ?? null) ?? $booking;
        $advanceDeadline = $booking === null ? null : strtotime('+4 days', $booking);
        $registrationDeadline = $received === null ? null : strtotime('+40 days', $received);
        $registered = $this->blank($row['registered_on'] ?? null) !== null || (string) ($row['status'] ?? '') === 'registered';
        $advance = (int) ($row['advance_minor'] ?? 0);
        $amount  = (int) ($row['booking_amount_minor'] ?? 0);
        $row['advance_deadline'] = $advanceDeadline === null ? null : date('Y-m-d', $advanceDeadline);
        $row['registration_deadline'] = $registrationDeadline === null ? null : date('Y-m-d', $registrationDeadline);
        $row['balance_minor'] = max(0, $amount - $advance);
        $row['advance_overdue'] = in_array((string) ($row['status'] ?? ''), ['advance', 'booked'], true) && $advanceDeadline !== null && $advanceDeadline < $today;
        $row['registration_overdue'] = ! $registered && $advance > 0 && $registrationDeadline !== null && $registrationDeadline < $today;
        $row['registration_followup'] = ! $registered && $advance > 0;
        $row['days_left'] = (! $registered && $registrationDeadline !== null) ? (int) round(($registrationDeadline - $today) / 86400) : null;

        return $row;
    }

    private function dayStamp(mixed $value): ?int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $stamp = strtotime(substr($value, 0, 10));

        return $stamp === false ? null : $stamp;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function leads(int $tenantId, string $query = ''): array
    {
        $builder = $this->db->table('leads l')
            ->select('l.*, pr.name AS project_name, p.plot_no, p.block, u.name AS owner_name, ab.name AS assigned_by_name')
            ->join('projects pr', 'pr.id = l.project_id AND pr.deleted_at IS NULL', 'left')
            ->join('plots p', 'p.id = l.plot_id AND p.deleted_at IS NULL', 'left')
            ->join('users u', 'u.id = l.owner_id', 'left')
            ->join('users ab', 'ab.id = l.assigned_by', 'left')
            ->where('l.tenant_id', $tenantId)
            ->where('l.deleted_at', null);

        $query = trim($query);

        if ($query !== '') {
            $builder->groupStart()
                ->like('l.name', $query)
                ->orLike('l.client_first_name', $query)
                ->orLike('l.phone', $query)
                ->orLike('l.phone_number', $query)
                ->orLike('l.email', $query)
                ->groupEnd();
        }

        return $builder->orderBy('l.id', 'DESC')->get()->getResultArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lead(int $tenantId, int $id): ?array
    {
        $row = $this->db->table('leads l')
            ->select('l.*, pr.name AS project_name, p.plot_no, p.block, u.name AS owner_name, ab.name AS assigned_by_name')
            ->join('projects pr', 'pr.id = l.project_id AND pr.deleted_at IS NULL', 'left')
            ->join('plots p', 'p.id = l.plot_id AND p.deleted_at IS NULL', 'left')
            ->join('users u', 'u.id = l.owner_id', 'left')
            ->join('users ab', 'ab.id = l.assigned_by', 'left')
            ->where('l.tenant_id', $tenantId)
            ->where('l.id', $id)
            ->where('l.deleted_at', null)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    public function setLeadStage(int $tenantId, int $id, string $stage, int $byUserId): void
    {
        $existing = $this->lead($tenantId, $id);

        if ($existing === null) {
            throw new RuntimeException('That lead is not in this workspace.');
        }

        $stage = trim($stage);

        if ($stage === '') {
            throw new RuntimeException('Choose a lead stage.');
        }

        $status = $this->leadStatus($stage, (string) ($existing['status'] ?? ''));
        $this->db->table('leads')->where('id', $id)->update([
            'lead_stage' => $stage,
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $oldStage = (string) ($existing['lead_stage'] ?? '');

        if ($stage !== $oldStage) {
            $this->logLeadStatus($tenantId, $id, $oldStage !== '' ? $oldStage : null, $stage, $byUserId);
        } elseif ((string) ($existing['status'] ?? '') !== $status) {
            $this->logLeadStatus($tenantId, $id, (string) ($existing['status'] ?? ''), $status, $byUserId);
        }
    }

    /**
     * @param mixed $projects
     */
    public function setInterestedProjects(int $tenantId, int $id, mixed $projects): void
    {
        if ($this->lead($tenantId, $id) === null) {
            throw new RuntimeException('That lead is not in this workspace.');
        }

        $raw = is_array($projects) ? $projects : explode(',', (string) $projects);
        $ids = [];

        foreach ($raw as $item) {
            $projectId = (int) $item;

            if ($projectId > 0 && $this->project($tenantId, $projectId) !== null) {
                $ids[] = (string) $projectId;
            }
        }

        $this->db->table('leads')->where('id', $id)->update([
            'interested_projects' => $ids === [] ? null : implode(',', $ids),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function leadNotes(int $tenantId, int $leadId): array
    {
        if ($this->lead($tenantId, $leadId) === null) {
            throw new RuntimeException('That lead is not in this workspace.');
        }

        return $this->db->table('lead_notes n')
            ->select('n.*, u.name AS user_name')
            ->join('users u', 'u.id = n.user_id', 'left')
            ->where('n.tenant_id', $tenantId)
            ->where('n.lead_id', $leadId)
            ->where('n.deleted_at', null)
            ->orderBy('n.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveNote(int $tenantId, int $leadId, int $userId, array $input): int
    {
        if ($this->lead($tenantId, $leadId) === null) {
            throw new RuntimeException('That lead is not in this workspace.');
        }

        $heading = trim((string) ($input['noteHeading'] ?? $input['note_heading'] ?? ''));
        $content = trim((string) ($input['noteContent'] ?? $input['note_content'] ?? ''));

        if ($heading === '' || $content === '') {
            throw new RuntimeException('A note needs a title and some text.');
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('lead_notes')->insert([
            'tenant_id'    => $tenantId,
            'lead_id'      => $leadId,
            'user_id'      => $userId,
            'note_heading' => $heading,
            'note_content' => $content,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        return (int) $this->db->insertID();
    }

    public function deleteNote(int $tenantId, int $leadId, int $noteId): void
    {
        $row = $this->db->table('lead_notes')
            ->where('tenant_id', $tenantId)
            ->where('lead_id', $leadId)
            ->where('id', $noteId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw new RuntimeException('That note is not on this lead.');
        }

        $this->db->table('lead_notes')->where('id', $noteId)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function leadDeals(int $tenantId, int $leadId): array
    {
        if ($this->lead($tenantId, $leadId) === null) {
            throw new RuntimeException('That lead is not in this workspace.');
        }

        $rows = $this->db->table('lead_deals')
            ->where('tenant_id', $tenantId)
            ->where('lead_id', $leadId)
            ->where('deleted_at', null)
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $decoded = json_decode((string) ($row['project_details'] ?? ''), true);
            $row['project_details'] = is_array($decoded) ? $decoded : [];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveDeal(int $tenantId, int $leadId, int $userId, array $input): int
    {
        if ($this->lead($tenantId, $leadId) === null) {
            throw new RuntimeException('That lead is not in this workspace.');
        }

        $stages = ['PROPOSAL_SUBMITTED', 'NEGOTIATION', 'BOOKING', 'BOOKING_CANCELLED', 'REGISTERED'];
        $stage  = strtoupper(trim((string) ($input['dealStage'] ?? $input['deal_stage'] ?? '')));

        if (! in_array($stage, $stages, true)) {
            throw new RuntimeException('Choose a deal stage.');
        }

        $projects = $input['projectDetails'] ?? $input['project_details'] ?? [];
        $clean    = [];

        if (is_array($projects)) {
            foreach ($projects as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $projectId = (int) ($item['projectId'] ?? $item['project_id'] ?? 0);
                $project   = $projectId > 0 ? $this->project($tenantId, $projectId) : null;

                if ($project === null) {
                    continue;
                }

                $clean[] = [
                    'projectId'   => $projectId,
                    'projectName' => (string) $project['name'],
                    'amount'      => BookService::rupeesToMinor((string) ($item['amount'] ?? '0')),
                    'notes'       => $this->blank($item['notes'] ?? null),
                ];
            }
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('lead_deals')->insert([
            'tenant_id'                  => $tenantId,
            'lead_id'                    => $leadId,
            'deal_stage'                 => $stage,
            'deal_stage_description'     => $this->blank($input['dealStageDescription'] ?? $input['deal_stage_description'] ?? null),
            'total_deal_value'           => BookService::rupeesToMinor((string) ($input['totalDealValue'] ?? $input['total_deal_value'] ?? '0')),
            'deal_description'           => $this->blank($input['dealDescription'] ?? $input['deal_description'] ?? null),
            'project_details'            => json_encode($clean),
            'booking_date'               => $this->blank($input['bookingDate'] ?? $input['booking_date'] ?? null),
            'expected_registration_date' => $this->blank($input['expectedRegistrationDate'] ?? $input['expected_registration_date'] ?? null),
            'registration_date'          => $this->blank($input['registrationDate'] ?? $input['registration_date'] ?? null),
            'inserted_by'                => $userId,
            'created_at'                 => $now,
            'updated_at'                 => $now,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveLead(int $tenantId, ?int $id, array $input): int
    {
        $prefix = trim((string) ($input['clientNamePrefix'] ?? $input['client_name_prefix'] ?? ''));
        $first  = trim((string) ($input['clientFirstName'] ?? $input['client_first_name'] ?? ''));
        $last   = trim((string) ($input['clientLastName'] ?? $input['client_last_name'] ?? ''));

        if ($first === '') {
            $first = trim((string) ($input['name'] ?? ''));
        }

        $phone = trim((string) ($input['phoneNumber'] ?? $input['phone_number'] ?? $input['phone'] ?? ''));
        $name  = trim((string) (preg_replace('/\s+/', ' ', trim($prefix . ' ' . $first . ' ' . $last)) ?? ''));

        if ($first === '' || $phone === '') {
            throw new RuntimeException('A lead needs a first name and a phone number.');
        }

        $phoneKey = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($phoneKey) !== 10) {
            throw new RuntimeException('Phone number must be 10 digits.');
        }

        $alternateDigits = preg_replace('/\D+/', '', (string) ($input['alternatePhoneNumber'] ?? $input['alternate_phone_number'] ?? $input['phone_secondary'] ?? '')) ?? '';

        if ($alternateDigits !== '' && strlen($alternateDigits) !== 10) {
            throw new RuntimeException('Mobile number must be 10 digits.');
        }

        $duplicate = $this->db->table('leads')
            ->where('tenant_id', $tenantId)
            ->where('phone_key', $phoneKey)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($duplicate !== null && (int) $duplicate['id'] !== (int) $id) {
            throw new RuntimeException('This phone already belongs to ' . $duplicate['name'] . '. Add a note on that lead instead of creating another.');
        }

        $email = strtolower(trim((string) ($input['email'] ?? '')));

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email or leave it blank.');
        }

        $stage  = trim((string) ($input['leadStage'] ?? $input['lead_stage'] ?? ''));
        $status = $this->leadStatus($stage, (string) ($input['status'] ?? ''));

        if ($stage === '') {
            $stage = $status;
        }

        $existing     = $id === null ? null : $this->lead($tenantId, $id);
        $projectId    = (int) ($input['projectId'] ?? $input['project_id'] ?? 0);
        $plotId       = (int) ($input['plot_id'] ?? 0);
        $assignedType = strtoupper((string) ($input['assignedType'] ?? $input['assigned_type'] ?? 'USER'));
        $assignedTo   = (int) ($input['assignedTo'] ?? $input['assigned_to'] ?? $input['owner_id'] ?? 0);
        $ownerId      = $assignedType === 'USER' ? $assignedTo : (int) ($input['owner_id'] ?? 0);

        if (! in_array($assignedType, ['USER', 'GROUP'], true)) {
            throw new RuntimeException('Assign the lead to a person or a team.');
        }

        if ($id !== null && $existing === null) {
            throw new RuntimeException('That lead is not in this workspace.');
        }

        if ($id === null && $assignedTo < 1) {
            throw new RuntimeException('Choose who this lead is assigned to.');
        }

        if ($projectId > 0 && $this->project($tenantId, $projectId) === null) {
            throw new RuntimeException('That project is not in this workspace.');
        }

        if ($plotId > 0) {
            $plot = $this->plot($tenantId, $plotId);

            if ($plot === null) {
                throw new RuntimeException('That plot is not in this workspace.');
            }

            if ($projectId > 0 && (int) $plot['project_id'] !== $projectId) {
                throw new RuntimeException('That plot is not in the selected project.');
            }

            if ($projectId < 1) {
                $projectId = (int) $plot['project_id'];
            }
        }

        $assignedName = '';
        $teamId       = (int) ($input['team_id'] ?? $input['groupId'] ?? 0);
        $byUserId     = (int) ($input['by_user_id'] ?? 0);

        if ($assignedType === 'GROUP') {
            if ($assignedTo < 1) {
                throw new RuntimeException('Choose the team this lead is assigned to.');
            }

            $team = $this->db->table('teams')
                ->where('tenant_id', $tenantId)
                ->where('id', $assignedTo)
                ->where('deleted_at', null)
                ->get()
                ->getRowArray();

            if ($team === null) {
                throw new RuntimeException('That team is not in this workspace.');
            }

            $assignedName = (string) $team['name'];
            $teamId       = $assignedTo;
            $headId       = (int) ($team['head_user_id'] ?? 0);
            $ownerId      = $headId > 0 ? $headId : $byUserId;
        }

        if ($ownerId > 0) {
            $previous = (int) ($existing['owner_id'] ?? 0);

            if (! in_array($ownerId, $this->memberIds($tenantId), true)) {
                throw new RuntimeException('Choose someone on this workspace.');
            }

            if ($ownerId !== $previous && in_array($this->staffStatus($tenantId, $ownerId), ['inactive', 'relieved'], true)) {
                throw new RuntimeException('That person is inactive and cannot take a new lead.');
            }

            if ($assignedType === 'USER') {
                $person = $this->db->table('users')->select('name')->where('id', $ownerId)->get()->getRowArray();
                $assignedName = (string) ($person['name'] ?? '');
            }
        }

        $projectName = $this->blank($input['projectName'] ?? $input['project_name'] ?? null);

        if ($projectId > 0 && $projectName === null) {
            $project = $this->project($tenantId, $projectId);
            $projectName = $project === null ? null : (string) $project['name'];
        }

        $budgetRaw = (string) ($input['clientBudgetValue'] ?? $input['client_budget_value'] ?? $input['budget'] ?? '');
        $source    = $this->blank($input['leadSource'] ?? $input['lead_source'] ?? $input['source'] ?? null);
        $alternate = $this->blank($input['alternatePhoneNumber'] ?? $input['alternate_phone_number'] ?? $input['phone_secondary'] ?? null);
        $notes     = $this->blank($input['description'] ?? $input['notes'] ?? null);
        $now       = date('Y-m-d H:i:s');
        $data      = [
            'name'                   => $name,
            'client_name_prefix'     => $prefix !== '' ? $prefix : null,
            'client_first_name'      => $first,
            'client_last_name'       => $last !== '' ? $last : null,
            'phone'                  => $phone,
            'phone_number'           => $phone,
            'phone_key'              => $phoneKey,
            'phone_secondary'        => $alternate,
            'alternate_phone_number' => $alternate,
            'email'                  => $email !== '' ? $email : null,
            'location'               => $this->blank($input['location'] ?? null),
            'budget_minor'           => BookService::rupeesToMinor($budgetRaw),
            'client_budget_value'    => (int) round((float) $budgetRaw),
            'client_budget_type'     => $this->blank($input['clientBudgetType'] ?? $input['client_budget_type'] ?? null),
            'team_id'                => $teamId > 0 ? $teamId : null,
            'group_id'               => $teamId > 0 ? $teamId : null,
            'source'                 => $source,
            'lead_source'            => $source,
            'campaign_source'        => $this->blank($input['campaignSource'] ?? $input['campaign_source'] ?? null),
            'project_id'             => $projectId > 0 ? $projectId : null,
            'project_name'           => $projectName,
            'plot_id'                => $plotId > 0 ? $plotId : null,
            'owner_id'               => $ownerId > 0 ? $ownerId : null,
            'assigned_to'            => $assignedTo > 0 ? $assignedTo : ($ownerId > 0 ? $ownerId : null),
            'assigned_type'          => $assignedType,
            'assigned_name'          => $assignedName !== '' ? $assignedName : null,
            'assigned_by'            => $byUserId > 0 ? $byUserId : null,
            'assigned_date'          => $now,
            'self_assigned'          => $assignedType === 'USER' && $byUserId > 0 && $byUserId === $assignedTo ? 1 : 0,
            'property_type'          => $this->blank($input['propertyType'] ?? $input['property_type'] ?? null),
            'area'                   => $this->blank($input['area'] ?? null),
            'client_preference'      => $this->blank($input['clientPreference'] ?? $input['client_preference'] ?? null),
            'client_street'          => $this->blank($input['clientStreet'] ?? $input['client_street'] ?? null),
            'client_city'            => $this->blank($input['clientCity'] ?? $input['client_city'] ?? null),
            'client_state'           => $this->blank($input['clientState'] ?? $input['client_state'] ?? null),
            'client_country'         => $this->blank($input['clientCountry'] ?? $input['client_country'] ?? null),
            'client_zip_code'        => $this->blank($input['clientZipCode'] ?? $input['client_zip_code'] ?? null),
            'status'                 => $status,
            'lead_stage'             => $stage,
            'lead_type'              => 'USER',
            'notes'                  => $notes,
            'description'            => $notes,
            'remarks'                => $this->blank($input['remarks'] ?? null),
            'interested_projects'    => $this->projectList($input['intrestedProjects'] ?? $input['interestedProjects'] ?? $input['interested_projects'] ?? null),
            'updated_at'             => $now,
        ];

        if ($id === null) {
            $data['tenant_id']   = $tenantId;
            $data['inserted_by'] = $byUserId > 0 ? $byUserId : null;
            $data['created_at']  = $now;
            $this->db->table('leads')->insert($data);
            $saved = (int) $this->db->insertID();

            if ($ownerId > 0) {
                $this->db->table('lead_transfers')->insert([
                    'tenant_id'    => $tenantId,
                    'lead_id'      => $saved,
                    'from_user_id' => null,
                    'to_user_id'   => $ownerId,
                    'by_user_id'   => (int) ($input['by_user_id'] ?? $ownerId),
                    'note'         => 'First assignment',
                    'created_at'   => $now,
                ]);
            }

            $this->logLeadStatus($tenantId, $saved, null, $status, (int) ($input['by_user_id'] ?? $ownerId));

            return $saved;
        }

        $previous = (int) ($existing['owner_id'] ?? 0);
        $this->db->table('leads')->where('id', $id)->update($data);
        $oldStage = (string) ($existing['lead_stage'] ?? '');
        $byUser = (int) ($input['by_user_id'] ?? 0);

        if ($stage !== $oldStage) {
            $this->logLeadStatus($tenantId, $id, $oldStage !== '' ? $oldStage : null, $stage, $byUser);
        } elseif ((string) ($existing['status'] ?? '') !== $status) {
            $this->logLeadStatus($tenantId, $id, (string) ($existing['status'] ?? ''), $status, $byUser);
        }

        if ($ownerId > 0 && $ownerId !== $previous) {
            $this->db->table('lead_transfers')->insert([
                'tenant_id'    => $tenantId,
                'lead_id'      => $id,
                'from_user_id' => $previous > 0 ? $previous : null,
                'to_user_id'   => $ownerId,
                'by_user_id'   => (int) ($input['by_user_id'] ?? $ownerId),
                'note'         => 'Owner changed on the lead',
                'created_at'   => $now,
            ]);
        }

        return $id;
    }

    public function deleteLead(int $tenantId, int $id): void
    {
        if ($this->lead($tenantId, $id) === null) {
            throw new RuntimeException('That lead is not in this workspace.');
        }

        $this->db->table('leads')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    public function transfer(int $tenantId, int $leadId, int $toUserId, int $byUserId, string $note): void
    {
        $lead = $this->lead($tenantId, $leadId);

        if ($lead === null) {
            throw new RuntimeException('That lead is not in this workspace.');
        }

        if (! in_array($toUserId, $this->memberIds($tenantId), true)) {
            throw new RuntimeException('Choose someone on this workspace.');
        }

        if ($this->staffStatus($tenantId, $toUserId) === 'inactive') {
            throw new RuntimeException('That person is inactive.');
        }

        $from = (int) ($lead['owner_id'] ?? 0);

        if (trim($note) === '') {
            throw new RuntimeException('A transfer needs a reason.');
        }

        if ($from === $toUserId) {
            throw new RuntimeException('That lead is already with this person.');
        }

        $now = date('Y-m-d H:i:s');
        $person = $this->db->table('users')->select('name')->where('id', $toUserId)->get()->getRowArray();
        $this->db->table('leads')->where('id', $leadId)->update([
            'owner_id'      => $toUserId,
            'assigned_to'   => $toUserId,
            'assigned_type' => 'USER',
            'assigned_name' => (string) ($person['name'] ?? ''),
            'assigned_by'   => $byUserId,
            'assigned_date' => $now,
            'self_assigned' => $byUserId === $toUserId ? 1 : 0,
            'group_id'      => null,
            'updated_at'    => $now,
        ]);
        $this->db->table('lead_transfers')->insert([
            'tenant_id'    => $tenantId,
            'lead_id'      => $leadId,
            'from_user_id' => $from > 0 ? $from : null,
            'to_user_id'   => $toUserId,
            'by_user_id'   => $byUserId,
            'note'         => $this->blank($note),
            'created_at'   => $now,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function followups(int $tenantId): array
    {
        $rows = $this->db->table('followups f')
            ->select('f.*, l.name AS lead_name, IFNULL(l.phone_number, l.phone) AS lead_phone, l.lead_stage, l.lead_source, l.campaign_source, l.project_name, u.name AS user_name, a.name AS assigned_name, b.name AS assigned_by_name', false)
            ->join('leads l', 'l.id = f.lead_id')
            ->join('users u', 'u.id = f.user_id', 'left')
            ->join('users a', 'a.id = f.assigned_to', 'left')
            ->join('users b', 'b.id = f.assigned_by', 'left')
            ->where('f.tenant_id', $tenantId)
            ->where('f.deleted_at', null)
            ->where('l.deleted_at', null)
            ->orderBy('f.call_scheduled_at', 'DESC')
            ->orderBy('f.id', 'DESC')
            ->get()
            ->getResultArray();

        return $this->stampActivity($rows, 'call_scheduled_at', 'next_at');
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveFollowup(int $tenantId, int $userId, int $leadId, array $input): int
    {
        if ($this->lead($tenantId, $leadId) === null) {
            throw new RuntimeException('That lead is not in this workspace.');
        }

        $subject = trim((string) ($input['callSubject'] ?? $input['call_subject'] ?? ''));
        $purpose = trim((string) ($input['callPurpose'] ?? $input['call_purpose'] ?? ''));
        $body    = trim((string) ($input['callDescription'] ?? $input['call_description'] ?? $input['body'] ?? ''));

        if ($body === '') {
            $body = trim($subject . ($purpose !== '' ? ' — ' . $purpose : ''));
        }

        if ($subject === '' || $purpose === '') {
            throw new RuntimeException('A call needs a subject and a purpose.');
        }

        if (strlen($subject) > 30) {
            throw new RuntimeException('Subject must be 30 characters or less.');
        }

        $assignedTo = (int) ($input['assignedTo'] ?? $input['assigned_to'] ?? $input['assigned_user_id'] ?? 0);

        if ($assignedTo < 1) {
            $assignedTo = $userId;
        }

        if (! in_array($assignedTo, $this->memberIds($tenantId), true)) {
            throw new RuntimeException('Choose someone on this workspace.');
        }

        $state  = strtoupper(trim((string) ($input['currentState'] ?? $input['current_state'] ?? 'SCHEDULED')));
        $status = match ($state) {
            'COMPLETED', 'DONE' => 'done',
            'MISSED'            => 'missed',
            'RESCHEDULED'       => 'rescheduled',
            default             => 'pending',
        };
        $when = $this->future((string) ($input['callScheduledDateTime'] ?? $input['call_scheduled_at'] ?? $input['next_at'] ?? ''));
        $now  = date('Y-m-d H:i:s');
        $this->db->table('followups')->insert([
            'tenant_id'         => $tenantId,
            'lead_id'           => $leadId,
            'user_id'           => $userId,
            'body'              => $body,
            'call_subject'      => $subject !== '' ? $subject : $body,
            'call_purpose'      => $purpose !== '' ? $purpose : null,
            'call_feedback'     => $this->blank($input['callFeedback'] ?? $input['call_feedback'] ?? null),
            'call_notes'        => $this->blank($input['callNotes'] ?? $input['call_notes'] ?? null),
            'call_description'  => $body,
            'next_at'           => $when,
            'call_scheduled_at' => $when,
            'followup_type'     => in_array((string) ($input['followup_type'] ?? ''), ['call', 'whatsapp', 'visit', 'meeting'], true) ? $input['followup_type'] : 'call',
            'status'            => $status,
            'current_state'     => $state !== '' ? $state : 'SCHEDULED',
            'outcome'           => $this->blank($input['outcome'] ?? null),
            'assigned_user_id'  => $assignedTo,
            'assigned_to'       => $assignedTo,
            'assigned_by'       => $userId,
            'self_assigned'     => $userId === $assignedTo ? 1 : 0,
            'inserted_by'       => $userId,
            'created_at'        => $now,
        ]);

        return (int) $this->db->insertID();
    }

    public function completeFollowup(int $tenantId, int $id): void
    {
        $row = $this->db->table('followups')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw new RuntimeException('That follow-up is not in this workspace.');
        }

        if ($row['done_at'] !== null) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('followups')->where('id', $id)->update([
            'done_at'        => $now,
            'completed_date' => $now,
            'status'         => 'done',
            'current_state'  => 'COMPLETED',
        ]);
    }

    public function rescheduleFollowup(int $tenantId, int $id, string $when): void
    {
        $row = $this->db->table('followups')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw new RuntimeException('That call is not in this workspace.');
        }

        $saved = $this->future($when);
        $this->db->table('followups')->where('id', $id)->update([
            'next_at'           => $saved,
            'call_scheduled_at' => $saved,
            'status'            => 'pending',
            'current_state'     => 'SCHEDULED',
            'done_at'           => null,
            'completed_date'    => null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tasks(int $tenantId): array
    {
        $rows = $this->db->table('lead_tasks t')
            ->select('t.*, l.name AS lead_name, IFNULL(l.phone_number, l.phone) AS lead_phone, l.lead_stage, l.lead_source, l.campaign_source, l.project_name, a.name AS assigned_name, b.name AS assigned_by_name', false)
            ->join('leads l', 'l.id = t.lead_id')
            ->join('users a', 'a.id = t.assigned_to', 'left')
            ->join('users b', 'b.id = t.assigned_by', 'left')
            ->where('t.tenant_id', $tenantId)
            ->where('t.deleted_at', null)
            ->where('l.deleted_at', null)
            ->orderBy('t.scheduled_at', 'DESC')
            ->get()
            ->getResultArray();

        return $this->stampActivity($rows, 'scheduled_at');
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveTask(int $tenantId, int $userId, array $input): int
    {
        $leadId = (int) ($input['leadId'] ?? $input['lead_id'] ?? 0);

        if ($this->lead($tenantId, $leadId) === null) {
            throw new RuntimeException('That lead is not in this workspace.');
        }

        $subject  = trim((string) ($input['subject'] ?? ''));
        $priority = strtoupper(trim((string) ($input['priority'] ?? 'MEDIUM')));
        $assigned = (int) ($input['assignedTo'] ?? $input['assigned_to'] ?? 0);

        if ($subject === '') {
            throw new RuntimeException('A task needs a subject.');
        }

        if (! in_array($priority, ['LOW', 'MEDIUM', 'HIGH'], true)) {
            throw new RuntimeException('Choose a priority.');
        }

        if (! in_array($assigned, $this->memberIds($tenantId), true)) {
            throw new RuntimeException('Choose someone on this workspace.');
        }

        $when = $this->future((string) ($input['scheduledDateTime'] ?? $input['scheduled_at'] ?? ''));
        $now  = date('Y-m-d H:i:s');
        $this->db->table('lead_tasks')->insert([
            'tenant_id'      => $tenantId,
            'lead_id'        => $leadId,
            'assigned_to'    => $assigned,
            'assigned_by'    => $userId,
            'subject'        => $subject,
            'priority'       => $priority,
            'task_type'      => 'LEAD',
            'description'    => $this->blank($input['description'] ?? null),
            'current_state'  => 'SCHEDULED',
            'scheduled_at'   => $when,
            'self_assigned'  => $userId === $assigned ? 1 : 0,
            'inserted_by'    => $userId,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        return (int) $this->db->insertID();
    }

    public function completeTask(int $tenantId, int $id): void
    {
        $this->touchTask($tenantId, $id, [
            'current_state'  => 'COMPLETED',
            'completed_date' => date('Y-m-d H:i:s'),
        ]);
    }

    public function rescheduleTask(int $tenantId, int $id, string $when): void
    {
        $this->touchTask($tenantId, $id, [
            'scheduled_at'   => $this->future($when),
            'current_state'  => 'SCHEDULED',
            'completed_date' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function touchTask(int $tenantId, int $id, array $data): void
    {
        $row = $this->db->table('lead_tasks')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw new RuntimeException('That task is not in this workspace.');
        }

        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->table('lead_tasks')->where('id', $id)->update($data);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function visits(int $tenantId): array
    {
        $rows = $this->db->table('site_visits v')
            ->select('v.*, l.name AS lead_name, IFNULL(l.phone_number, l.phone) AS lead_phone, l.lead_stage, l.lead_source, l.campaign_source, u.name AS staff_name, b.name AS assigned_by_name, pr.name AS project_name, p.plot_no', false)
            ->join('leads l', 'l.id = v.lead_id')
            ->join('users u', 'u.id = v.staff_id', 'left')
            ->join('users b', 'b.id = v.assigned_by', 'left')
            ->join('projects pr', 'pr.id = v.project_id', 'left')
            ->join('plots p', 'p.id = v.plot_id', 'left')
            ->where('v.tenant_id', $tenantId)
            ->where('v.deleted_at', null)
            ->where('l.deleted_at', null)
            ->orderBy('v.scheduled_at', 'DESC')
            ->get()
            ->getResultArray();

        return $this->stampActivity($rows, 'scheduled_date_time', 'scheduled_at');
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveVisit(int $tenantId, int $leadId, array $input): int
    {
        $lead = $this->lead($tenantId, $leadId);

        if ($lead === null) {
            throw new RuntimeException('That lead is not in this workspace.');
        }

        $staffId = (int) ($input['assignedTo'] ?? $input['assigned_to'] ?? $input['staff_id'] ?? 0);

        if (! in_array($staffId, $this->memberIds($tenantId), true)) {
            throw new RuntimeException('Choose the staff member for this visit.');
        }

        if ($this->staffStatus($tenantId, $staffId) === 'inactive') {
            throw new RuntimeException('That person is inactive.');
        }

        $when = $this->future((string) ($input['scheduledDateTime'] ?? $input['scheduled_date_time'] ?? $input['scheduled_at'] ?? ''));

        $projectId = (int) ($input['projectId'] ?? $input['project_id'] ?? 0);
        $plotId    = (int) ($input['plot_id'] ?? 0);
        $visitType = trim((string) ($input['visitType'] ?? $input['visit_type'] ?? ''));

        if ($visitType === '') {
            $visitType = 'New Visit';
        }

        if ($projectId < 1) {
            $projectId = (int) ($lead['project_id'] ?? 0);
        }

        if ($plotId < 1) {
            $plotId = (int) ($lead['plot_id'] ?? 0);
        }

        if ($projectId < 1) {
            throw new RuntimeException('Choose the project for this visit.');
        }

        if ($this->project($tenantId, $projectId) === null) {
            throw new RuntimeException('That project is not in this workspace.');
        }

        if ($plotId > 0 && $this->plot($tenantId, $plotId) === null) {
            throw new RuntimeException('That plot is not in this workspace.');
        }

        $description = $this->blank($input['description'] ?? $input['note'] ?? null);

        if ($description === null) {
            throw new RuntimeException('A visit needs a description.');
        }
        $actor       = (int) ($input['created_by'] ?? 0);
        $state       = strtoupper(trim((string) ($input['currentState'] ?? $input['current_state'] ?? 'SCHEDULED')));
        $now         = date('Y-m-d H:i:s');
        $this->db->table('site_visits')->insert([
            'tenant_id'           => $tenantId,
            'lead_id'             => $leadId,
            'project_id'          => $projectId > 0 ? $projectId : null,
            'plot_id'             => $plotId > 0 ? $plotId : null,
            'staff_id'            => $staffId,
            'assigned_to'         => $staffId,
            'scheduled_at'        => $when,
            'scheduled_date_time' => $when,
            'status'              => 'scheduled',
            'current_state'       => $state !== '' ? $state : 'SCHEDULED',
            'visit_type'          => $visitType,
            'description'         => $description,
            'pickup'              => $this->blank($input['pickup'] ?? null),
            'created_by'          => $actor > 0 ? $actor : null,
            'inserted_by'         => $actor > 0 ? $actor : null,
            'assigned_by'         => $actor > 0 ? $actor : null,
            'self_assigned'       => $actor > 0 && $actor === $staffId ? 1 : 0,
            'note'                => $description,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        return (int) $this->db->insertID();
    }

    public function completeVisit(int $tenantId, int $id): void
    {
        $visit = $this->visitRow($tenantId, $id);
        $now = date('Y-m-d H:i:s');
        $this->db->table('site_visits')->where('id', (int) $visit['id'])->update([
            'status'         => 'completed',
            'current_state'  => 'COMPLETED',
            'completed_date' => $now,
            'attended_at'    => $now,
            'updated_at'     => $now,
        ]);
    }

    public function rescheduleVisit(int $tenantId, int $id, string $when): void
    {
        $visit = $this->visitRow($tenantId, $id);
        $saved = $this->future($when);
        $this->db->table('site_visits')->where('id', (int) $visit['id'])->update([
            'scheduled_at'        => $saved,
            'scheduled_date_time' => $saved,
            'status'              => 'scheduled',
            'current_state'       => 'SCHEDULED',
            'completed_date'      => null,
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function visitRow(int $tenantId, int $id): array
    {
        $visit = $this->db->table('site_visits')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($visit === null) {
            throw new RuntimeException('That visit is not in this workspace.');
        }

        return $visit;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function attendance(int $tenantId, int $visitId, int $userId, array $input): void
    {
        $visit = $this->db->table('site_visits')
            ->where('tenant_id', $tenantId)
            ->where('id', $visitId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($visit === null) {
            throw new RuntimeException('That visit is not in this workspace.');
        }

        $status = (string) ($input['status'] ?? '');

        $map = ['attended' => 'completed', 'missed' => 'no_show'];
        $status = $map[$status] ?? $status;

        if (! in_array($status, ['completed', 'cancelled', 'rescheduled', 'no_show', 'scheduled'], true)) {
            throw new RuntimeException('Choose a visit result.');
        }

        $now      = date('Y-m-d H:i:s');
        $attended = $status === 'completed';
        $this->db->table('site_visits')->where('id', $visitId)->update([
            'status'        => $status,
            'attended_at'   => $attended ? $now : null,
            'lead_attended' => $attended && ! empty($input['lead_attended']) ? 1 : 0,
            'staff_present' => $attended && ! empty($input['staff_present']) ? 1 : 0,
            'marked_by'     => $userId,
            'note'          => $this->blank($input['note'] ?? null) ?? $visit['note'],
            'updated_at'    => $now,
        ]);
    }

    /**
     * Every action on one lead, oldest first: created, allocated, transferred, stage, follow-up, visit, task, note, deal, message, booking.
     *
     * @return list<array{at: string, action: string, title: string, who: string, meta: string, body: string}>
     */
    public function leadActivity(int $tenantId, int $leadId): array
    {
        $lead = $this->lead($tenantId, $leadId);

        if ($lead === null) {
            return [];
        }

        $events = [];
        $creator = $this->personName((int) ($lead['inserted_by'] ?? 0));
        $source = trim((string) ($lead['lead_source'] ?? $lead['source'] ?? ''));
        $stage = trim((string) ($lead['lead_stage'] ?? ''));
        $came = 'Came in' . ($source !== '' ? ' from ' . $source : '') . '.';

        if ($stage !== '') {
            $came .= ' Stage ' . $stage . '.';
        }

        $events[] = $this->activityEvent((string) ($lead['created_at'] ?? ''), 'created', 'Lead created', $creator, $came);

        $transfers = $this->db->table('lead_transfers t')
            ->select('t.*, fu.name AS from_name, tu.name AS to_name, bu.name AS by_name')
            ->join('users fu', 'fu.id = t.from_user_id', 'left')
            ->join('users tu', 'tu.id = t.to_user_id', 'left')
            ->join('users bu', 'bu.id = t.by_user_id', 'left')
            ->where('t.tenant_id', $tenantId)
            ->where('t.lead_id', $leadId)
            ->get()
            ->getResultArray();

        foreach ($transfers as $row) {
            $from = trim((string) ($row['from_name'] ?? ''));
            $to = trim((string) ($row['to_name'] ?? ''));
            $note = trim((string) ($row['note'] ?? ''));
            $first = (int) ($row['from_user_id'] ?? 0) < 1;
            $body = $first
                ? 'Allocated to ' . ($to !== '' ? $to : 'a team member') . '.'
                : ($from !== '' ? $from : 'Unassigned') . ' → ' . $to . '. Bookings and registrations already recorded stay with ' . ($from !== '' ? $from : 'the previous person') . '. New bookings and registrations count for ' . ($to !== '' ? $to : 'the new person') . '.';

            if ($note !== '' && ! in_array($note, ['First assignment', 'Owner changed on the lead'], true)) {
                $body .= ' Reason: ' . $note . '.';
            }

            $events[] = $this->activityEvent(
                (string) $row['created_at'],
                $first ? 'allocated' : 'transferred',
                $first ? 'Allocated' : 'Transferred',
                (string) ($row['by_name'] ?? ''),
                $body,
            );
        }

        $ownerName = trim((string) ($lead['owner_name'] ?? ''));

        if ($transfers === [] && $ownerName !== '') {
            $events[] = $this->activityEvent(
                (string) ($lead['assigned_date'] ?? $lead['created_at'] ?? ''),
                'allocated',
                'Allocated',
                (string) ($lead['assigned_by_name'] ?? ''),
                'Allocated to ' . $ownerName . '.',
            );
        }

        if ($this->db->tableExists('lead_status_history')) {
            $history = $this->db->table('lead_status_history h')
                ->select('h.*, u.name AS user_name')
                ->join('users u', 'u.id = h.user_id', 'left')
                ->where('h.tenant_id', $tenantId)
                ->where('h.lead_id', $leadId)
                ->get()
                ->getResultArray();

            foreach ($history as $row) {
                $old = trim((string) ($row['old_status'] ?? ''));
                $new = trim((string) ($row['new_status'] ?? ''));

                if ($old === '' && (string) ($row['created_at'] ?? '') === (string) ($lead['created_at'] ?? '')) {
                    continue;
                }

                $body = $old === '' ? 'Stage set to ' . $new . '.' : $old . ' → ' . $new . '.';
                $events[] = $this->activityEvent((string) $row['created_at'], 'stage', 'Stage', (string) ($row['user_name'] ?? ''), $body);
            }
        }

        $followups = $this->db->table('followups f')
            ->select('f.*, u.name AS user_name')
            ->join('users u', 'u.id = f.user_id', 'left')
            ->where('f.tenant_id', $tenantId)
            ->where('f.lead_id', $leadId)
            ->where('f.deleted_at', null)
            ->get()
            ->getResultArray();

        foreach ($followups as $row) {
            $text = trim((string) ($row['call_subject'] ?? ''));
            $body = trim((string) ($row['body'] ?? ''));

            if ($text !== '' && $body !== '' && $text !== $body) {
                $text .= ' — ' . $body;
            } elseif ($text === '') {
                $text = $body;
            }

            $events[] = $this->activityEvent((string) ($row['created_at'] ?? ''), 'followup', 'Follow-up', (string) ($row['user_name'] ?? ''), $text);

            if (! empty($row['done_at'])) {
                $events[] = $this->activityEvent((string) $row['done_at'], 'followup_done', 'Follow-up done', (string) ($row['user_name'] ?? ''), $text);
            }
        }

        $visits = $this->db->table('site_visits v')
            ->select('v.*, u.name AS staff_name')
            ->join('users u', 'u.id = v.staff_id', 'left')
            ->where('v.tenant_id', $tenantId)
            ->where('v.lead_id', $leadId)
            ->where('v.deleted_at', null)
            ->get()
            ->getResultArray();

        foreach ($visits as $row) {
            $note = trim((string) ($row['note'] ?? ''));
            $events[] = $this->activityEvent(
                (string) ($row['created_at'] ?? $row['scheduled_at'] ?? ''),
                'visit',
                'Site visit',
                (string) ($row['staff_name'] ?? ''),
                'Scheduled' . ($note !== '' ? ' — ' . $note : '') . '.',
            );

            if (in_array((string) ($row['status'] ?? ''), ['completed', 'cancelled', 'no_show', 'attended', 'missed'], true)) {
                $when = (string) ($row['attended_at'] ?? $row['updated_at'] ?? $row['scheduled_at'] ?? '');
                $events[] = $this->activityEvent($when, 'visit_done', 'Site visit ' . ucfirst((string) $row['status']), (string) ($row['staff_name'] ?? ''), $note);
            }
        }

        if ($this->db->tableExists('lead_tasks')) {
            $tasks = $this->db->table('lead_tasks t')
                ->select('t.*, u.name AS user_name')
                ->join('users u', 'u.id = t.inserted_by', 'left')
                ->where('t.tenant_id', $tenantId)
                ->where('t.lead_id', $leadId)
                ->where('t.deleted_at', null)
                ->get()
                ->getResultArray();

            foreach ($tasks as $row) {
                $text = trim((string) ($row['subject'] ?? ''));
                $events[] = $this->activityEvent((string) ($row['created_at'] ?? ''), 'task', 'Task', (string) ($row['user_name'] ?? ''), $text);

                if (! empty($row['completed_date'])) {
                    $events[] = $this->activityEvent((string) $row['completed_date'], 'task_done', 'Task done', (string) ($row['user_name'] ?? ''), $text);
                }
            }
        }

        if ($this->db->tableExists('lead_notes')) {
            $notes = $this->db->table('lead_notes n')
                ->select('n.*, u.name AS user_name')
                ->join('users u', 'u.id = n.user_id', 'left')
                ->where('n.tenant_id', $tenantId)
                ->where('n.lead_id', $leadId)
                ->where('n.deleted_at', null)
                ->get()
                ->getResultArray();

            foreach ($notes as $row) {
                $heading = trim((string) ($row['note_heading'] ?? ''));
                $content = trim((string) ($row['note_content'] ?? ''));
                $events[] = $this->activityEvent((string) ($row['created_at'] ?? ''), 'note', 'Note', (string) ($row['user_name'] ?? ''), trim($heading . ($content !== '' ? ' — ' . $content : '')));
            }
        }

        if ($this->db->tableExists('lead_deals')) {
            $deals = $this->db->table('lead_deals d')
                ->select('d.*, u.name AS user_name')
                ->join('users u', 'u.id = d.inserted_by', 'left')
                ->where('d.tenant_id', $tenantId)
                ->where('d.lead_id', $leadId)
                ->where('d.deleted_at', null)
                ->get()
                ->getResultArray();

            foreach ($deals as $row) {
                $label = trim((string) ($row['deal_stage'] ?? 'Deal'));
                $detail = trim((string) ($row['deal_description'] ?? ''));
                $events[] = $this->activityEvent((string) ($row['created_at'] ?? ''), 'deal', 'Deal', (string) ($row['user_name'] ?? ''), $label . ($detail !== '' ? ' — ' . $detail : ''));
            }
        }

        if ($this->db->tableExists('whatsapp_messages')) {
            $messages = $this->db->table('whatsapp_messages m')
                ->select('m.*, u.name AS user_name')
                ->join('users u', 'u.id = m.user_id', 'left')
                ->where('m.tenant_id', $tenantId)
                ->where('m.lead_id', $leadId)
                ->get()
                ->getResultArray();

            foreach ($messages as $row) {
                $events[] = $this->activityEvent((string) ($row['created_at'] ?? ''), 'whatsapp', 'WhatsApp', (string) ($row['user_name'] ?? ''), trim((string) ($row['body'] ?? '')));
            }
        }

        if ($this->db->tableExists('sales')) {
            $sales = $this->db->table('sales s')
                ->select('s.created_at, s.plot_status, s.credited_user_id, u.name AS user_name, p.plot_no')
                ->join('users u', 'u.id = s.credited_user_id', 'left')
                ->join('plots p', 'p.id = s.plot_id', 'left')
                ->where('s.tenant_id', $tenantId)
                ->where('s.lead_id', $leadId)
                ->where('s.deleted_at', null)
                ->get()
                ->getResultArray();

            foreach ($sales as $row) {
                $who = trim((string) ($row['user_name'] ?? ''));
                $plot = trim((string) ($row['plot_no'] ?? ''));
                $kind = ucfirst((string) ($row['plot_status'] ?? 'booked'));
                $body = ($plot !== '' ? 'Plot ' . $plot . '. ' : '') . 'This achievement stays with ' . ($who !== '' ? $who : 'the person who booked it') . '.';
                $events[] = $this->activityEvent((string) ($row['created_at'] ?? ''), 'achievement', $kind, $who, $body);
            }
        }

        $createdAt = (string) ($lead['created_at'] ?? '');
        $pinned = [];
        $rest = [];

        foreach ($events as $event) {
            $pin = $event['action'] === 'created' || ($event['action'] === 'allocated' && $event['at'] === $createdAt);

            if ($pin) {
                $pinned[] = $event;
            } else {
                $rest[] = $event;
            }
        }

        $byTime = static function (array $a, array $b): int {
            $left = (string) ($a['at'] ?? '');
            $right = (string) ($b['at'] ?? '');

            if ($left === $right) {
                return strcmp((string) $a['title'], (string) $b['title']);
            }

            if ($left === '') {
                return 1;
            }

            if ($right === '') {
                return -1;
            }

            return strcmp($left, $right);
        };
        usort($pinned, static function (array $a, array $b): int {
            if ($a['action'] === $b['action']) {
                return 0;
            }

            return $a['action'] === 'created' ? -1 : 1;
        });
        usort($rest, $byTime);

        return array_merge($pinned, $rest);
    }

    /**
     * @return array<int, list<array{title: string, meta: string, body: string}>>
     */
    public function timelines(int $tenantId): array
    {
        $events = [];
        $transfers = $this->db->table('lead_transfers t')
            ->select('t.*, fu.name AS from_name, tu.name AS to_name, bu.name AS by_name')
            ->join('users fu', 'fu.id = t.from_user_id', 'left')
            ->join('users tu', 'tu.id = t.to_user_id', 'left')
            ->join('users bu', 'bu.id = t.by_user_id', 'left')
            ->where('t.tenant_id', $tenantId)
            ->get()
            ->getResultArray();

        foreach ($transfers as $row) {
            $note = trim((string) ($row['note'] ?? ''));
            $events[(int) $row['lead_id']][] = [
                'at'    => (string) $row['created_at'],
                'title' => 'Transfer',
                'meta'  => $this->when($row['created_at']) . ' · ' . (string) ($row['by_name'] ?? ''),
                'body'  => (($row['from_name'] ?? '') !== '' ? $row['from_name'] : 'Unassigned') . ' → ' . (string) ($row['to_name'] ?? '') . ($note !== '' ? ' — ' . $note : ''),
            ];
        }

        $followups = $this->db->table('followups f')
            ->select('f.*, u.name AS user_name')
            ->join('users u', 'u.id = f.user_id', 'left')
            ->where('f.tenant_id', $tenantId)
            ->where('f.deleted_at', null)
            ->get()
            ->getResultArray();

        foreach ($followups as $row) {
            $meta = $this->when($row['created_at']) . ' · ' . (string) ($row['user_name'] ?? '');

            if ($row['next_at'] !== null) {
                $meta .= ' · next ' . $this->when($row['next_at']);
            }

            $meta .= $row['done_at'] === null ? ' · open' : ' · done';
            $events[(int) $row['lead_id']][] = [
                'at'    => (string) $row['created_at'],
                'title' => 'Follow-up',
                'meta'  => $meta,
                'body'  => (string) $row['body'],
            ];
        }

        $visits = $this->db->table('site_visits v')
            ->select('v.*, u.name AS staff_name')
            ->join('users u', 'u.id = v.staff_id', 'left')
            ->where('v.tenant_id', $tenantId)
            ->where('v.deleted_at', null)
            ->get()
            ->getResultArray();

        foreach ($visits as $row) {
            $bits = [];

            if ((int) $row['lead_attended'] === 1) {
                $bits[] = 'Lead showed up';
            }

            if ((int) $row['staff_present'] === 1) {
                $bits[] = 'Staff present';
            }

            if (trim((string) ($row['note'] ?? '')) !== '') {
                $bits[] = trim((string) $row['note']);
            }

            $events[(int) $row['lead_id']][] = [
                'at'    => (string) $row['scheduled_at'],
                'title' => 'Site visit · ' . ucfirst((string) $row['status']),
                'meta'  => $this->when($row['scheduled_at']) . ' · ' . (string) ($row['staff_name'] ?? ''),
                'body'  => implode(' — ', $bits),
            ];
        }

        $out = [];

        foreach ($events as $leadId => $list) {
            usort($list, static fn (array $a, array $b): int => strcmp($b['at'], $a['at']));
            $out[$leadId] = array_map(static fn (array $item): array => [
                'title' => $item['title'],
                'meta'  => $item['meta'],
                'body'  => $item['body'],
            ], $list);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchLeads(int $tenantId, string $query): array
    {
        if (trim($query) === '') {
            return [];
        }

        return $this->leads($tenantId, $query);
    }

    /**
     * @return list<int>
     */
    private function memberIds(int $tenantId): array
    {
        $rows = $this->db->table('tenant_users')
            ->select('user_id')
            ->where('tenant_id', $tenantId)
            ->get()
            ->getResultArray();

        return array_map('intval', array_column($rows, 'user_id'));
    }

    private function staffStatus(int $tenantId, int $userId): string
    {
        $row = $this->db->table('staff_profiles')
            ->select('status')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return $row['status'] ?? 'active';
    }

    private function logPlotStatus(int $tenantId, int $plotId, ?string $old, string $new, int $userId, string $remark): void
    {
        if (! $this->db->tableExists('plot_status_history') || $old === $new) {
            return;
        }

        $this->db->table('plot_status_history')->insert([
            'tenant_id'  => $tenantId,
            'plot_id'    => $plotId,
            'old_status' => $old !== null && $old !== '' ? $old : null,
            'new_status' => $new,
            'user_id'    => $userId > 0 ? $userId : null,
            'remark'     => $this->blank($remark),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function logLeadStatus(int $tenantId, int $leadId, ?string $old, string $new, int $userId): void
    {
        if (! $this->db->tableExists('lead_status_history') || $old === $new) {
            return;
        }

        $this->db->table('lead_status_history')->insert([
            'tenant_id'  => $tenantId,
            'lead_id'    => $leadId,
            'old_status' => $old !== '' ? $old : null,
            'new_status' => $new,
            'user_id'    => $userId > 0 ? $userId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function day(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $stamp = strtotime($value);

        if ($stamp === false) {
            throw new RuntimeException('Enter a valid date.');
        }

        return date('Y-m-d', $stamp);
    }

    private function leadStatus(string $stage, string $fallback): string
    {
        $map = [
            'new' => 'new', 'new lead' => 'new', 'contacted' => 'contacted', 'qualified' => 'contacted',
            'follow-up' => 'followup', 'followup' => 'followup', 'follow up' => 'followup',
            'proposal sent' => 'negotiation',
            'site visit' => 'site_visit', 'site visit scheduled' => 'site_visit', 'site_visit' => 'site_visit',
            'site visit done' => 'site_visit_done', 'site_visit_done' => 'site_visit_done',
            'negotiation' => 'negotiation', 'booked' => 'booked', 'won' => 'won',
            'lost' => 'lost', 'not interested' => 'not_interested', 'not_interested' => 'not_interested',
        ];
        $key = strtolower(trim($stage));

        if (isset($map[$key])) {
            return $map[$key];
        }

        $fallback = strtolower(trim($fallback));

        return in_array($fallback, self::LEAD_STATUSES, true) ? $fallback : 'new';
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function stampActivity(array $rows, string $whenKey, ?string $fallbackKey = null): array
    {
        foreach ($rows as &$row) {
            $when = (string) ($row[$whenKey] ?? '');

            if ($when === '' && $fallbackKey !== null) {
                $when = (string) ($row[$fallbackKey] ?? '');
            }

            $state = (string) ($row['current_state'] ?? $row['status'] ?? '');

            if ((! empty($row['done_at']) || ! empty($row['completed_date'])) && ! in_array(strtoupper(trim($state)), ['CANCELLED', 'CANCELED'], true)) {
                $state = 'COMPLETED';
            }

            $row['display_status'] = $this->activityStatus($state, $when);

            if (($row['assigned_name'] ?? '') === '' && ($row['staff_name'] ?? '') !== '') {
                $row['assigned_name'] = $row['staff_name'];
            }
        }

        return $rows;
    }

    private function activityStatus(string $state, string $when): string
    {
        $state = strtoupper(trim($state));

        if (in_array($state, ['COMPLETED', 'DONE'], true)) {
            return 'COMPLETED';
        }

        if (in_array($state, ['CANCELLED', 'CANCELED'], true)) {
            return 'CANCELLED';
        }

        $stamp = $when !== '' ? strtotime($when) : false;

        if ($stamp !== false && $stamp < time()) {
            return 'OVERDUE';
        }

        return 'SCHEDULED';
    }

    private function future(string $when): string
    {
        $saved = $this->dateTime($when);

        if ($saved === null) {
            throw new RuntimeException('Choose when this is scheduled.');
        }

        if (strtotime($saved) < time() - 120) {
            throw new RuntimeException('Choose a future time.');
        }

        return $saved;
    }

    private function projectList(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = implode(',', array_filter(array_map(static fn ($item): string => trim((string) $item), $value)));
        }

        return $this->blank($value);
    }

    private function blank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function dateTime(string $value): ?string
    {
        $value = trim(str_replace('T', ' ', $value));

        if ($value === '') {
            return null;
        }

        $stamp = strtotime($value);

        if ($stamp === false) {
            throw new RuntimeException('Enter a valid date and time.');
        }

        return date('Y-m-d H:i:s', $stamp);
    }

    private function personName(int $userId): string
    {
        if ($userId < 1) {
            return '';
        }

        $row = $this->db->table('users')->select('name')->where('id', $userId)->get()->getRowArray();

        return trim((string) ($row['name'] ?? ''));
    }

    /**
     * @return array{at: string, action: string, title: string, who: string, meta: string, body: string}
     */
    private function activityEvent(string $at, string $action, string $title, string $who, string $body): array
    {
        $who = trim($who);

        return [
            'at'     => $at,
            'action' => $action,
            'title'  => $title,
            'who'    => $who,
            'meta'   => trim($this->when($at !== '' ? $at : null) . ($who !== '' ? ' · ' . $who : '')),
            'body'   => trim($body),
        ];
    }

    private function when(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $stamp = strtotime($value);

        return $stamp === false ? $value : date('d M Y, H:i', $stamp);
    }
}
