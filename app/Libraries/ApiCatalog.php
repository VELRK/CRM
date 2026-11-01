<?php

namespace App\Libraries;

class ApiCatalog
{
    /**
     * Sample requests and responses for the mobile team. Samples are examples, not live rows.
     *
     * @return list<array<string, mixed>>
     */
    public static function entries(): array
    {
        $lead = [
            'id' => 1, 'name' => 'Mr. Ravi Kumar', 'client_name_prefix' => 'Mr.', 'client_first_name' => 'Ravi', 'client_last_name' => 'Kumar',
            'phone_number' => '9840012345', 'phone' => '9840012345', 'alternate_phone_number' => null,
            'email' => 'ravi@example.com', 'lead_stage' => 'New', 'lead_source' => 'Walk-in',
            'assigned_type' => 'USER', 'assigned_to' => 2, 'owner_name' => 'Priya', 'assigned_by_name' => 'Priya',
            'project_id' => 1, 'project_name' => 'Green Meadows', 'location' => 'Coimbatore',
        ];
        $leadInput = [
            'clientNamePrefix' => 'Mr.', 'clientFirstName' => 'Ravi', 'clientLastName' => 'Kumar',
            'phoneNumber' => '9840012345', 'alternatePhoneNumber' => '', 'email' => 'ravi@example.com',
            'assignedType' => 'USER', 'assignedTo' => 2, 'leadStage' => 'New', 'leadSource' => 'Walk-in',
            'projectId' => 1, 'location' => 'Coimbatore', 'description' => 'Asked about the east plot',
        ];

        return [
            self::entry('Auth', 'FR-15', 'Sign in', 'POST', '/api/v1/auth/login', false, null, [
                'email' => 'priya@example.com', 'password' => 'password1', 'device' => 'Android',
            ], ['ok' => true, 'data' => [
                'token' => 'paste-this-as-bearer',
                'user' => ['id' => 1, 'name' => 'Priya', 'email' => 'priya@example.com', 'role' => 'Owner', 'workspace' => 'Northwind', 'plan' => 'enterprise'],
                'permissions' => ['leads.view', 'leads.transfer'],
                'modules' => [['module' => 'Leads', 'allowed' => true, 'actions' => [['key' => 'leads.view', 'label' => 'View leads', 'allowed' => true], ['key' => 'leads.transfer', 'label' => 'Transfer a lead', 'allowed' => true]]]],
                'scope' => ['level' => 'company', 'label' => 'Company', 'user_ids' => null, 'team_ids' => null],
            ]]),
            self::entry('Auth', 'FR-15', 'Current user', 'GET', '/api/v1/me', true, null, null, ['ok' => true, 'data' => [
                'id' => 1, 'name' => 'Priya', 'email' => 'priya@example.com', 'role' => 'Owner', 'workspace' => 'Northwind', 'plan' => 'enterprise',
                'permissions' => ['leads.view'],
                'modules' => [['module' => 'Leads', 'allowed' => true, 'actions' => [['key' => 'leads.view', 'label' => 'View leads', 'allowed' => true]]]],
                'scope' => ['level' => 'team', 'label' => 'North wing', 'user_ids' => [3, 7], 'team_ids' => [1]],
            ]]),
            self::entry('Auth', 'FR-15', 'Sign out', 'POST', '/api/v1/auth/logout', true, null, new \stdClass(), ['ok' => true, 'data' => ['revoked' => true]]),
            self::entry('Auth', 'FR-15', 'Set password from link', 'POST', '/api/v1/auth/password', false, null, [
                'token' => 'token-from-setup-link', 'password' => 'new-password',
            ], ['ok' => true, 'data' => ['saved' => true]]),
            self::entry('Auth', 'FR-15', 'Change password', 'POST', '/api/v1/me/password', true, null, [
                'current_password' => 'password1', 'password' => 'new-password',
            ], ['ok' => true, 'data' => ['saved' => true]]),
            self::entry('Catalog', 'FR-11', 'Endpoint catalog', 'GET', '/api/v1/catalog', true, null, null, ['ok' => true, 'data' => ['entries' => ['…this list']]]),
            self::entry('Catalog', 'FR-11', 'Postman collection', 'GET', '/api/v1/catalog/postman', true, null, null, ['info' => ['name' => 'Atrium API'], 'item' => []]),

            self::entry('Staff', 'FR-1.1', 'List staff', 'GET', '/api/v1/staff', true, 'staff.view', null, ['ok' => true, 'data' => [['user_id' => 1, 'name' => 'Priya', 'phone_number' => '9840000000', 'email' => 'priya@example.com', 'role_name' => 'Owner', 'profile' => 'Owner', 'status' => 'active']]]),
            self::entry('Staff', 'FR-1.1', 'Update staff', 'POST', '/api/v1/staff/1', true, 'staff.update', [
                'phone' => '9840000000', 'status' => 'active', 'team_id' => 1, 'attendance_applicable' => 1, 'lead_eligible' => 1, 'incentive_eligible' => 1,
            ], ['ok' => true, 'data' => ['user_id' => 1]]),
            self::entry('Staff', 'FR-1.4', 'Open work before relieving', 'GET', '/api/v1/staff/1/open-work', true, 'staff.view', null, ['ok' => true, 'data' => ['leads' => [$lead], 'followups' => [['id' => 4, 'lead_name' => 'Mr. Ravi Kumar', 'call_subject' => 'Site visit', 'display_status' => 'SCHEDULED']]]]),
            self::entry('Teams', 'FR-1.2', 'List teams', 'GET', '/api/v1/teams', true, 'teams.view', null, ['ok' => true, 'data' => [['id' => 1, 'name' => 'East wing', 'head_user_id' => 1, 'head_name' => 'Priya', 'project_ids' => [1], 'projects' => 'Green Meadows', 'member_ids' => [1, 2], 'members' => 'Priya, Anitha Joseph']]]),
            self::entry('Teams', 'FR-1.2', 'Create team', 'POST', '/api/v1/teams', true, 'teams.manage', [
                'name' => 'East wing', 'head_user_id' => 1, 'member_ids' => [1], 'project_ids' => [1],
            ], ['ok' => true, 'data' => ['id' => 1]]),
            self::entry('Teams', 'FR-1.2', 'Update team', 'POST', '/api/v1/teams/1', true, 'teams.manage', [
                'name' => 'East wing', 'head_user_id' => 1, 'member_ids' => [1], 'project_ids' => [1],
            ], ['ok' => true, 'data' => ['id' => 1]]),
            self::entry('Teams', 'FR-1.3', 'Membership history', 'GET', '/api/v1/teams/1/history', true, 'teams.view', null, ['ok' => true, 'data' => [['user_id' => 1, 'name' => 'Priya', 'from_on' => '2026-10-01', 'to_on' => null]]]),

            self::entry('Projects', 'FR-2.1', 'List projects', 'GET', '/api/v1/projects', true, 'projects.view', null, ['ok' => true, 'data' => [['id' => 1, 'name' => 'Green Meadows', 'location' => 'Coimbatore', 'project_type' => 'plotted', 'total_plots' => 120, 'launch_on' => '2026-11-01', 'status' => 'ongoing']]]),
            self::entry('Projects', 'FR-2.1', 'Create project', 'POST', '/api/v1/projects', true, 'projects.create', [
                'name' => 'Green Meadows', 'location' => 'Coimbatore', 'status' => 'upcoming', 'project_type' => 'Plotted layout', 'total_plots' => 120, 'launch_on' => '2026-11-01',
            ], ['ok' => true, 'data' => ['id' => 2]]),
            self::entry('Projects', 'FR-2.1', 'Update project', 'POST', '/api/v1/projects/1', true, 'projects.update', ['name' => 'Green Meadows', 'status' => 'ongoing'], ['ok' => true, 'data' => ['id' => 1]]),
            self::entry('Projects', 'FR-2.4', 'Project dashboard', 'GET', '/api/v1/projects/1/dashboard', true, 'projects.view', null, ['ok' => true, 'data' => ['plots' => ['available' => 10, 'booked' => 1], 'leads' => 4, 'visits' => 2, 'bookings' => 1]]),
            self::entry('Files', 'FR-2.2', 'Files on a record', 'GET', '/api/v1/files?type=project&id=1', true, 'files.view', null, ['ok' => true, 'data' => [['id' => 3, 'name' => 'layout.pdf']]]),
            self::entry('Files', 'FR-2.2', 'Attach a file', 'POST', '/api/v1/files', true, 'files.attach', ['type' => 'project', 'subject_id' => 1, 'file' => '(multipart field file)'], ['ok' => true, 'data' => ['id' => 3]]),

            self::entry('Plots', 'FR-3.5', 'List plots', 'GET', '/api/v1/plots?project=1&status=available', true, 'plots.view', null, ['ok' => true, 'data' => [['id' => 1, 'project_id' => 1, 'project_name' => 'Green Meadows', 'phase' => 'A', 'plot_no' => 'A-12', 'facing' => 'East', 'area_sqft' => '1200.00', 'commission_minor' => 5000000, 'status' => 'available', 'customer_name' => null, 'associate_name' => null, 'head_name' => null]]]),
            self::entry('Plots', 'FR-3.1', 'Create plot', 'POST', '/api/v1/plots', true, 'plots.create', [
                'project_id' => 1, 'phase' => 'A', 'plot_no' => 'A-13', 'facing' => 'East', 'area_sqft' => '1200', 'commission' => '50000', 'status' => 'available',
            ], ['ok' => true, 'data' => ['id' => 2, 'status' => 'available']]),
            self::entry('Plots', 'FR-3.1', 'Update a plot record', 'POST', '/api/v1/plots/1', true, 'plots.update', [
                'project_id' => 1, 'phase' => 'A', 'plot_no' => 'A-12', 'facing' => 'East', 'area_sqft' => '1200', 'commission' => '50000', 'status' => 'hold',
            ], ['ok' => true, 'data' => ['id' => 1, 'status' => 'hold']]),
            self::entry('Plots', 'FR-3.4', 'Plot status history', 'GET', '/api/v1/plots/1/history', true, 'plots.view', null, ['ok' => true, 'data' => [['old_status' => 'available', 'new_status' => 'booked', 'remark' => 'Token received', 'created_at' => '2026-10-24 15:00:00']]]),
            self::entry('Plots', 'FR-3.5', 'Export plots', 'GET', '/api/v1/plots/export', true, 'exports.download', null, 'CSV file'),

            self::entry('Mobile', 'FR-4', 'Dashboard counts', 'GET', '/api/v1/mobile/dashboard', true, 'leads.view', null, ['ok' => true, 'data' => [
                'leads' => 30, 'new_leads' => 8, 'followups' => 12, 'followups_overdue' => 3, 'followups_upcoming' => 9, 'followups_done' => 20,
                'visits' => 6, 'tasks' => 4, 'plots_available' => 40,
                'stages' => [['name' => 'New', 'total' => 8]],
                'sources' => [['name' => 'Walk-in', 'total' => 5]],
                'campaigns' => [['name' => 'Facebook', 'total' => 2]],
            ]]),
            self::entry('Mobile', 'FR-4.2', 'List lead stages', 'GET', '/api/v1/lead-stages?active=1', true, 'leads.view', null, ['ok' => true, 'data' => [['id' => 1, 'name' => 'New', 'active' => true, 'sort_order' => 1]]]),
            self::entry('Mobile', 'FR-4.2', 'Add lead stage', 'POST', '/api/v1/lead-stages', true, 'leads.update', ['name' => 'Negotiation', 'active' => true], ['ok' => true, 'data' => ['id' => 2, 'name' => 'Negotiation', 'active' => true, 'sort_order' => 2]]),
            self::entry('Mobile', 'FR-4.2', 'Update lead stage', 'POST', '/api/v1/lead-stages/2', true, 'leads.update', ['name' => 'Negotiation', 'active' => false], ['ok' => true, 'data' => ['id' => 2, 'name' => 'Negotiation', 'active' => false, 'sort_order' => 2]]),
            self::entry('Mobile', 'FR-4.2', 'Delete lead stage', 'POST', '/api/v1/lead-stages/2/delete', true, 'leads.update', new \stdClass(), ['ok' => true, 'data' => ['id' => 2, 'deleted' => true]]),
            self::entry('Mobile', 'FR-4.2', 'List lead sources', 'GET', '/api/v1/lead-sources', true, 'leads.view', null, ['ok' => true, 'data' => [['id' => 1, 'name' => 'Walk-in', 'active' => true, 'sort_order' => 1]]]),
            self::entry('Mobile', 'FR-4.2', 'Add lead source', 'POST', '/api/v1/lead-sources', true, 'leads.update', ['name' => 'Walk-in'], ['ok' => true, 'data' => ['id' => 1, 'name' => 'Walk-in', 'active' => true, 'sort_order' => 1]]),
            self::entry('Mobile', 'FR-4.2', 'Update lead source', 'POST', '/api/v1/lead-sources/1', true, 'leads.update', ['name' => 'Walk in', 'active' => true], ['ok' => true, 'data' => ['id' => 1, 'name' => 'Walk in', 'active' => true, 'sort_order' => 1]]),
            self::entry('Mobile', 'FR-4.2', 'Delete lead source', 'POST', '/api/v1/lead-sources/1/delete', true, 'leads.update', new \stdClass(), ['ok' => true, 'data' => ['id' => 1, 'deleted' => true]]),
            self::entry('Mobile', 'FR-4.2', 'List campaign sources', 'GET', '/api/v1/campaign-sources', true, 'leads.view', null, ['ok' => true, 'data' => [['id' => 1, 'name' => 'Facebook', 'active' => true, 'sort_order' => 1]]]),
            self::entry('Mobile', 'FR-4.2', 'Add campaign source', 'POST', '/api/v1/campaign-sources', true, 'leads.update', ['name' => 'Facebook'], ['ok' => true, 'data' => ['id' => 1, 'name' => 'Facebook', 'active' => true, 'sort_order' => 1]]),
            self::entry('Mobile', 'FR-4.2', 'Update campaign source', 'POST', '/api/v1/campaign-sources/1', true, 'leads.update', ['name' => 'Facebook Ads'], ['ok' => true, 'data' => ['id' => 1, 'name' => 'Facebook Ads', 'active' => true, 'sort_order' => 1]]),
            self::entry('Mobile', 'FR-4.2', 'Delete campaign source', 'POST', '/api/v1/campaign-sources/1/delete', true, 'masters.manage', new \stdClass(), ['ok' => true, 'data' => ['id' => 1, 'deleted' => true]]),
            self::entry('Mobile', 'FR-4.2', 'List follow-up purposes', 'GET', '/api/v1/followup-purposes?active=1', true, 'followups.view', null, ['ok' => true, 'data' => [['id' => 1, 'name' => 'Follow up', 'active' => true, 'sort_order' => 1]]]),
            self::entry('Mobile', 'FR-4.2', 'Add follow-up purpose', 'POST', '/api/v1/followup-purposes', true, 'masters.manage', ['name' => 'Follow up'], ['ok' => true, 'data' => ['id' => 1, 'name' => 'Follow up', 'active' => true, 'sort_order' => 1]]),
            self::entry('Mobile', 'FR-4.2', 'List call feedback', 'GET', '/api/v1/call-feedback?active=1', true, 'followups.view', null, ['ok' => true, 'data' => [['id' => 1, 'name' => 'Interested', 'active' => true, 'sort_order' => 1]]]),
            self::entry('Mobile', 'FR-4.2', 'List visit feedback', 'GET', '/api/v1/visit-feedback?active=1', true, 'visits.view', null, ['ok' => true, 'data' => [['id' => 1, 'name' => 'Interested', 'active' => true, 'sort_order' => 1]]]),
            self::entry('Mobile', 'FR-4.8', 'Approve a lead', 'POST', '/api/v1/leads/1/approve', true, 'leads.approve', new \stdClass(), ['ok' => true, 'data' => ['id' => 1, 'approval_status' => 'approved']]),
            self::entry('Mobile', 'FR-4.9', 'List deals', 'GET', '/api/v1/deals', true, 'deals.view', null, ['ok' => true, 'data' => [['id' => 1, 'approval_status' => 'pending', 'deal_stage' => 'PROPOSAL_SUBMITTED']]]),
            self::entry('Mobile', 'FR-4.9', 'Approve a deal', 'POST', '/api/v1/deals/1/approve', true, 'deals.approve', new \stdClass(), ['ok' => true, 'data' => ['id' => 1, 'approval_status' => 'approved']]),
            self::entry('Leads', 'FR-4.2', 'List leads', 'GET', '/api/v1/leads?q=Ravi', true, 'leads.view', null, ['ok' => true, 'data' => [$lead]]),
            self::entry('Mobile', 'FR-4.2', 'Filter leads', 'POST', '/api/v1/leads/filter', true, 'leads.view', [
                'q' => 'Ravi', 'stage' => 'New', 'source' => 'Walk-in', 'campaign' => 'Facebook', 'project' => 'Green Meadows', 'assignee' => 'Priya', 'range' => 'this_month',
            ], ['ok' => true, 'data' => [$lead], 'meta' => ['total' => 1]]),
            self::entry('Leads', 'FR-4.4', 'Duplicate phone check', 'POST', '/api/v1/leads/check', true, 'leads.view', ['phoneNumber' => '9840012345'], ['ok' => true, 'data' => ['duplicate' => true, 'lead' => $lead, 'timeline' => []]]),
            self::entry('Leads', 'FR-4.2', 'Create lead', 'POST', '/api/v1/leads', true, 'leads.create', $leadInput, ['ok' => true, 'data' => ['id' => 1]]),
            self::entry('Leads', 'FR-4.6', 'Lead activity', 'GET', '/api/v1/leads/1/timeline?from=2026-10-01&to=2026-10-31', true, 'leads.view', null, ['ok' => true, 'data' => [
                ['at' => '2026-10-01 09:00:00', 'action' => 'created', 'title' => 'Lead created', 'who' => 'Priya', 'meta' => '01 Oct 2026, 09:00 · Priya', 'body' => 'Came in from Website. Stage New.'],
                ['at' => '2026-10-01 09:00:00', 'action' => 'allocated', 'title' => 'Allocated', 'who' => 'Priya', 'meta' => '01 Oct 2026, 09:00 · Priya', 'body' => 'Allocated to Meena.'],
                ['at' => '2026-10-24 11:00:00', 'action' => 'transferred', 'title' => 'Transferred', 'who' => 'Priya', 'meta' => '24 Oct 2026, 11:00 · Priya', 'body' => 'Meena → Arun. Bookings and registrations already recorded stay with Meena. New bookings and registrations count for Arun.'],
            ]]),
            self::entry('Leads', 'FR-7.1', 'Transfer one lead', 'POST', '/api/v1/leads/1/transfer', true, 'leads.transfer', ['assignedTo' => 2, 'reason' => 'Customer asked for Priya'], ['ok' => true, 'data' => ['id' => 1]]),
            self::entry('Leads', 'FR-7.3', 'Bulk transfer', 'POST', '/api/v1/leads/transfer', true, 'leads.transfer', ['lead_ids' => [1], 'assignedTo' => 2, 'reason' => 'Staff relieved'], ['ok' => true, 'data' => ['moved' => 1]]),
            self::entry('Leads', 'FR-4.2', 'Export leads', 'GET', '/api/v1/leads/export', true, 'exports.download', null, 'CSV file'),

            self::entry('Follow-ups', 'FR-6.3', 'List follow-ups', 'GET', '/api/v1/followups?view=today', true, 'followups.view', null, ['ok' => true, 'data' => [['id' => 4, 'lead_id' => 1, 'lead_name' => 'Mr. Ravi Kumar', 'lead_phone' => '9840012345', 'call_subject' => 'Site visit', 'call_purpose' => 'Follow up', 'call_scheduled_at' => '2026-10-30 11:00:00', 'display_status' => 'SCHEDULED', 'assigned_name' => 'Priya', 'assigned_by_name' => 'Priya']]]),
            self::entry('Mobile', 'FR-6.3', 'Filter follow-ups', 'POST', '/api/v1/followups/filter', true, 'followups.view', [
                'status' => 'OVERDUE', 'assignee' => 'Priya', 'project' => 'Green Meadows', 'range' => 'this_month', 'q' => 'Ravi',
            ], ['ok' => true, 'data' => [['id' => 4, 'lead_name' => 'Mr. Ravi Kumar', 'display_status' => 'OVERDUE']], 'meta' => ['total' => 1]]),
            self::entry('Follow-ups', 'FR-6.1', 'Log a follow-up', 'POST', '/api/v1/followups', true, 'followups.create', [
                'leadId' => 1, 'callSubject' => 'Site visit', 'callPurpose' => 'Follow up', 'callDescription' => 'Call back about the east plot', 'callScheduledDateTime' => '2026-10-30T11:00', 'assignedTo' => 2,
            ], ['ok' => true, 'data' => ['id' => 5]]),
            self::entry('Follow-ups', 'FR-6.1', 'Mark follow-up done', 'POST', '/api/v1/followups/5/done', true, 'followups.create', new \stdClass(), ['ok' => true, 'data' => ['id' => 5, 'status' => 'COMPLETED']]),

            self::entry('Visits', 'FR-8.1', 'List visits', 'GET', '/api/v1/visits', true, 'visits.view', null, ['ok' => true, 'data' => [['id' => 1, 'lead_id' => 1, 'lead_name' => 'Mr. Ravi Kumar', 'project_name' => 'Green Meadows', 'visit_type' => 'New Visit', 'scheduled_date_time' => '2026-10-30 10:00:00', 'display_status' => 'SCHEDULED', 'assigned_name' => 'Priya']]]),
            self::entry('Mobile', 'FR-8.1', 'Filter visits', 'POST', '/api/v1/visits/filter', true, 'visits.view', [
                'status' => 'SCHEDULED', 'assignee' => 'Priya', 'project' => 'Green Meadows', 'range' => 'today',
            ], ['ok' => true, 'data' => [['id' => 1, 'lead_name' => 'Mr. Ravi Kumar', 'display_status' => 'SCHEDULED']], 'meta' => ['total' => 1]]),
            self::entry('Visits', 'FR-8.1', 'Schedule a visit', 'POST', '/api/v1/visits', true, 'visits.create', [
                'leadId' => 1, 'projectId' => 1, 'scheduledDateTime' => '2026-10-30T10:00', 'assignedTo' => 2, 'visitType' => 'New Visit', 'description' => 'Walk the east row', 'currentState' => 'SCHEDULED',
            ], ['ok' => true, 'data' => ['id' => 2]]),
            self::entry('Visits', 'FR-8.2', 'Update visit status', 'POST', '/api/v1/visits/1', true, 'visits.attend', ['status' => 'completed', 'note' => 'Customer walked the east row', 'lead_attended' => 1, 'staff_present' => 1], ['ok' => true, 'data' => ['id' => 1]]),

            self::entry('Attendance', 'FR-9.1', 'Attendance for a month', 'GET', '/api/v1/attendance?month=2026-10', true, 'attendance.view', null, ['ok' => true, 'data' => [['user_id' => 1, 'user_name' => 'Priya', 'work_date' => '2026-10-24', 'status' => 'present', 'check_in' => '2026-10-24 09:58:00']]]),
            self::entry('Attendance', 'FR-9.1', 'Check in', 'POST', '/api/v1/attendance', true, 'attendance.mark', [
                'user_id' => 1, 'work_date' => '2026-10-29', 'status' => 'present', 'action' => 'in', 'note' => '', 'location_id' => 1, 'lat' => '11.0168000', 'lng' => '76.9558000', 'accuracy' => 12,
            ], ['ok' => true, 'data' => ['saved' => true]]),
            self::entry('Attendance', 'FR-9.1', 'Assigned locations', 'GET', '/api/v1/attendance/places?lat=11.0168&lng=76.9558', true, 'attendance.mark', null, ['ok' => true, 'data' => [['id' => 1, 'name' => 'North gate', 'entity_type' => 'project', 'entity_name' => 'Green Meadows', 'distance_metres' => 40, 'inside' => true, 'check_status' => 'none']]]),
            self::entry('Attendance', 'FR-9.1', 'Attendance locations', 'GET', '/api/v1/attendance/locations', true, 'attendance.view', null, ['ok' => true, 'data' => [['id' => 1, 'name' => 'North gate', 'radius_metres' => 150, 'status' => 'active', 'entity_type' => 'project', 'entity_id' => 1]]]),
            self::entry('Attendance', 'FR-9.1', 'Save an attendance location', 'POST', '/api/v1/attendance/locations', true, 'attendance.correct', [
                'name' => 'North gate', 'address' => 'North gate', 'latitude' => '11.0168000', 'longitude' => '76.9558000', 'radius_metres' => 150, 'work_start' => '09:00', 'work_end' => '18:00', 'accuracy_metres' => 50, 'status' => 'active', 'entity_type' => 'project', 'entity_id' => 1,
            ], ['ok' => true, 'data' => ['id' => 1]]),
            self::entry('Attendance', 'FR-9.3', 'Attendance sessions', 'GET', '/api/v1/attendance/sessions?date=2026-10-29', true, 'attendance.view', null, ['ok' => true, 'data' => [['id' => 1, 'user_id' => 1, 'user_name' => 'Priya', 'work_date' => '2026-10-29', 'status' => 'present', 'check_in' => '2026-10-29 09:58:00', 'check_out' => '2026-10-29 18:05:00', 'check_status' => 'done', 'location_name' => 'North gate', 'entity_name' => 'Green Meadows', 'check_in_place' => 'North gate', 'check_out_place' => 'North gate', 'check_in_lat' => '11.0168000', 'check_in_lng' => '76.9558000', 'check_out_lat' => '11.0168100', 'check_out_lng' => '76.9558100']]]),

            self::entry('Incentives', 'FR-12.3', 'List incentives', 'GET', '/api/v1/incentives', true, 'incentives.view', null, ['ok' => true, 'data' => [['id' => 1, 'user_id' => 1, 'status' => 'pending', 'amount_minor' => 5000000]]]),
            self::entry('Incentives', 'FR-12.6', 'Set incentive status', 'POST', '/api/v1/incentives/1', true, 'incentives.approve', ['status' => 'approved'], ['ok' => true, 'data' => ['id' => 1, 'status' => 'approved']]),
            self::entry('Incentives', 'FR-12.3', 'Incentive percent', 'POST', '/api/v1/incentives/rule', true, 'incentives.approve', ['percent' => 2], ['ok' => true, 'data' => ['percent' => 2]]),

            self::entry('Reports', 'FR-13', 'Role dashboard', 'GET', '/api/v1/reports', true, 'reports.view', null, ['ok' => true, 'data' => ['scope' => ['level' => 'company', 'label' => 'Company'], 'leads' => 1, 'conversion' => ['leads' => 1, 'visited' => 1, 'booked' => 1], 'sources' => [], 'funnel' => [], 'staff' => [], 'teams' => [], 'projects' => []]]),
            self::entry('Reports', 'FR-9.3', 'Attendance places', 'GET', '/api/v1/reports/attendance?month=2026-10', true, 'reports.view', null, ['ok' => true, 'data' => [['id' => 1, 'user_name' => 'Priya', 'work_date' => '2026-10-29', 'status' => 'present', 'check_in' => '2026-10-29 09:58:00', 'check_out' => '2026-10-29 18:05:00', 'check_in_place' => 'North gate', 'check_out_place' => 'North gate', 'check_in_lat' => '11.0168000', 'check_in_lng' => '76.9558000', 'check_out_lat' => '11.0168100', 'check_out_lng' => '76.9558100', 'entity_name' => 'Green Meadows']]]),
            self::entry('Reports', 'FR-13', 'Excel export', 'GET', '/api/v1/reports/excel', true, 'exports.download', null, ['file' => 'atrium-reports.xls']),
            self::entry('Reports', 'FR-13', 'PDF export', 'GET', '/api/v1/reports/pdf', true, 'exports.download', null, ['file' => 'atrium-reports.pdf']),
            self::entry('Activity', 'FR-10', 'Activity log', 'GET', '/api/v1/activity?from=2026-10-01&to=2026-10-31', true, 'team.view', null, ['ok' => true, 'data' => [['id' => 9, 'user_name' => 'Priya', 'action' => 'plot.status', 'subject_type' => 'plot', 'subject_id' => 1, 'detail' => 'booked', 'created_at' => '2026-10-24 16:00:00']]]),
            self::entry('Notifications', 'FR-15', 'Register a phone', 'POST', '/api/v1/devices', true, null, [
                'token' => 'fcm-device-token', 'platform' => 'android', 'name' => 'Pixel',
            ], ['ok' => true, 'data' => ['saved' => true]]),
            self::entry('Notifications', 'FR-15', 'Forget a phone', 'POST', '/api/v1/devices/deactivate', true, null, [
                'token' => 'fcm-device-token',
            ], ['ok' => true, 'data' => ['saved' => true]]),
            self::entry('Notifications', 'FR-15', 'Notifications', 'GET', '/api/v1/notifications', true, null, null, ['ok' => true, 'data' => [['id' => 1, 'title' => 'Follow-up due', 'body' => 'Call Ravi', 'kind' => 'text', 'media' => null, 'read_at' => null]]]),
            self::entry('Notifications', 'FR-15', 'Push rules', 'GET', '/api/v1/notifications/rules', true, 'notifications.manage', null, ['ok' => true, 'data' => [[
                'id' => 1, 'notification_name' => 'New task', 'module' => 'Tasks', 'event_key' => 'task.assigned', 'kind' => 'text', 'media' => '', 'title' => 'New task', 'message' => '{name}, you have a task: {task}', 'active' => true,
                'recipients' => ['subject' => true, 'subject_team' => false, 'team_head' => true, 'all_active' => false, 'users' => [], 'teams' => [], 'roles' => [], 'designations' => []],
                'conditions' => ['active_only' => true, 'project_id' => 0, 'designation' => '', 'manager_user_id' => 0],
            ]]]),
            self::entry('Notifications', 'FR-15', 'Save a push rule', 'POST', '/api/v1/notifications/rules', true, 'notifications.manage', [
                'name' => 'New task', 'event_key' => 'task.assigned', 'kind' => 'text', 'media' => '', 'title' => 'New task', 'message' => '{name}, you have a task: {task}', 'active' => true,
                'recipients' => ['subject' => true, 'subject_team' => true, 'team_head' => false, 'users' => [], 'teams' => [], 'roles' => [], 'designations' => []],
                'conditions' => ['active_only' => true],
            ], ['ok' => true, 'data' => ['id' => 1]]),
            self::entry('Notifications', 'FR-15', 'Upload a notification image or video', 'POST', '/api/v1/notifications/media', true, 'notifications.manage', [
                'file' => 'multipart PNG, JPG, WebP, or GIF up to 8 MB, or MP4, WebM, or MOV up to 20 MB',
            ], ['ok' => true, 'data' => ['kind' => 'image', 'media' => '/api/v1/notifications/media/0123456789abcdef0123456789abcdef.jpg']]),
            self::entry('Notifications', 'FR-15', 'Open a notification image or video', 'GET', '/api/v1/notifications/media/0123456789abcdef0123456789abcdef.jpg', true, null, null, 'Image or video file'),
            self::entry('Notifications', 'FR-15', 'Send an announcement', 'POST', '/api/v1/notifications/send', true, 'notifications.manage', [
                'kind' => 'video', 'media' => 'https://cdn.example.com/briefing.mp4', 'title' => 'Site meeting', 'message' => 'Meet at the east gate at 10.',
                'recipients' => ['teams' => [1]], 'conditions' => ['active_only' => true],
            ], ['ok' => true, 'data' => ['recipients' => 4, 'kind' => 'video']]),
            self::entry('Notifications', 'FR-15', 'Push history', 'GET', '/api/v1/notifications/history', true, 'notifications.manage', null, ['ok' => true, 'data' => [[
                'id' => 1, 'event_key' => 'task.assigned', 'kind' => 'text', 'media' => null, 'title' => 'New task', 'message' => 'You have a task', 'total_recipients' => 1, 'successful_count' => 0, 'failed_count' => 1, 'created_at' => '2026-10-31 11:00:00',
            ]]]),
            self::entry('Notifications', 'FR-15', 'Mark all read', 'POST', '/api/v1/notifications/read', true, null, new \stdClass(), ['ok' => true, 'data' => ['read' => true]]),

            self::entry('WhatsApp', 'FR-14', 'Sent messages', 'GET', '/api/v1/whatsapp/messages', true, 'leads.view', null, ['ok' => true, 'data' => [['id' => 1, 'lead_name' => 'Ravi Kumar', 'to_phone' => '9840012345', 'subject' => 'Welcome', 'body' => 'Hello Ravi Kumar', 'status' => 'failed', 'status_label' => 'Not delivered', 'user_name' => 'Priya', 'created_at' => '2026-10-29 18:00:00']]]),
            self::entry('WhatsApp', 'FR-14', 'Templates', 'GET', '/api/v1/whatsapp/templates', true, 'whatsapp.manage', null, ['ok' => true, 'data' => [['id' => 1, 'name' => 'Welcome', 'body' => 'Hello {{name}}']]]),
            self::entry('WhatsApp', 'FR-14', 'Save template', 'POST', '/api/v1/whatsapp/templates', true, 'whatsapp.manage', ['name' => 'Welcome', 'language' => 'en', 'body' => 'Hello {{name}}', 'active' => 1], ['ok' => true, 'data' => ['saved' => true]]),
            self::entry('WhatsApp', 'FR-14', 'Send a template through AskEva', 'POST', '/api/v1/leads/1/whatsapp', true, 'whatsapp.manage', ['template_id' => 1], ['ok' => true, 'data' => ['status' => 'sent', 'detail' => 'Sent through AskEva.']]),

            self::entry('Company', 'Company', 'Company details', 'GET', '/api/v1/company', true, null, null, ['ok' => true, 'data' => ['companyName' => 'Northwind', 'companyAdminName' => 'Priya Nair', 'companyAdminEmail' => 'priya@example.com', 'phone' => '9840012345', 'address' => '12 East Street, Coimbatore', 'logo' => false, 'attendanceLat' => '11.0168000', 'attendanceLng' => '76.9558000', 'attendanceKm' => '1.00', 'updatedDate' => '2026-10-29 18:00:00']]),
            self::entry('Company', 'Company', 'Save company details', 'POST', '/api/v1/company', true, 'workspace.settings', ['companyName' => 'Northwind', 'companyAdminName' => 'Priya Nair', 'companyAdminEmail' => 'priya@example.com', 'phone' => '9840012345', 'address' => '12 East Street, Coimbatore', 'attendanceLat' => '11.0168000', 'attendanceLng' => '76.9558000', 'attendanceKm' => '1'], ['ok' => true, 'data' => ['companyName' => 'Northwind', 'phone' => '9840012345', 'address' => '12 East Street, Coimbatore', 'logo' => false, 'attendanceKm' => '1.00']]),
            self::entry('Company', 'Company', 'Company logo', 'GET', '/api/v1/company/logo', true, null, null, 'PNG, JPG, or WebP image'),
            self::entry('Company', 'Company', 'Update logo', 'POST', '/api/v1/company/logo', true, 'workspace.settings', ['logo' => 'multipart image file'], ['ok' => true, 'data' => ['logo' => true]]),
            self::entry('Access', 'Roles', 'List roles', 'GET', '/api/v1/roles', true, 'team.view', null, ['ok' => true, 'data' => [['id' => 1, 'name' => 'Owner', 'is_owner' => 1]]]),
            self::entry('Access', 'Roles', 'Create role', 'POST', '/api/v1/roles', true, 'roles.manage', ['name' => 'Site executive', 'keys' => ['leads.view', 'leads.create']], ['ok' => true, 'data' => ['id' => 9]]),
            self::entry('Access', 'Roles', 'Invite a person', 'POST', '/api/v1/invites', true, 'team.invite', ['email' => 'advisor@example.com', 'role_id' => 8], ['ok' => true, 'data' => ['invite_path' => '/join/token-once']]),
            self::entry('Lists', 'FR-3.2', 'Lookup lists', 'GET', '/api/v1/lookups?kind=lead_status', true, 'workspace.settings', null, ['ok' => true, 'data' => [['code' => 'new', 'label' => 'New', 'active' => 1]]]),

            self::entry('Staff', 'FR-1.1', 'Create user', 'POST', '/api/v1/users', true, 'team.invite', [
                'name' => 'Anitha Joseph', 'email' => 'anitha@example.com', 'password' => 'password1', 'phoneNumber' => '9840011111', 'role_id' => 8, 'profile' => 'Advisor', 'groupId' => 1, 'dob' => '1994-04-12', 'street' => '12 East Street', 'city' => 'Coimbatore', 'state' => 'Tamil Nadu', 'zipCode' => '641001', 'active' => 1,
            ], ['ok' => true, 'data' => ['id' => 4, 'email' => 'anitha@example.com', 'password' => 'password1', 'temporary_password' => 'password1', 'setup_path' => '/desk/?setup=token-once', 'login' => 'POST /api/v1/auth/login with this email and password. Any role is allowed.']]),
            self::entry('Teams', 'FR-1.2', 'Delete team', 'POST', '/api/v1/teams/1/delete', true, 'teams.manage', new \stdClass(), ['ok' => true, 'data' => ['id' => 1, 'deleted' => true]]),
            self::entry('Projects', 'FR-2.1', 'Delete project', 'POST', '/api/v1/projects/1/delete', true, 'projects.delete', new \stdClass(), ['ok' => true, 'data' => ['id' => 1, 'deleted' => true]]),
            self::entry('Plots', 'FR-3.6', 'Save booking', 'POST', '/api/v1/plots/1/booking', true, 'plots.update', [
                'status' => 'booked', 'customerName' => 'Ravi Kumar', 'customerPhone' => '9840012345', 'associateUserId' => 2,
                'bookingOn' => '2026-10-29', 'advance' => '50000', 'advanceReceivedOn' => '2026-10-29', 'bookingAmount' => '2500000', 'commission' => '50000',
                'expectedRegistrationOn' => '2026-12-08', 'registeredOn' => '', 'remarks' => 'Token received',
            ], ['ok' => true, 'data' => ['id' => 1, 'status' => 'booked', 'customer_name' => 'Ravi Kumar', 'customer_phone' => '9840012345', 'associate_name' => 'Priya', 'head_name' => 'Priya', 'advance_minor' => 5000000, 'booking_amount_minor' => 250000000, 'booking_on' => '2026-10-29']]),
            self::entry('Plots', 'FR-3.1', 'Delete plot', 'POST', '/api/v1/plots/1/delete', true, 'plots.delete', new \stdClass(), ['ok' => true, 'data' => ['id' => 1, 'deleted' => true]]),
            self::entry('Projects', 'FR-3.2', 'Upload a project image', 'POST', '/api/v1/projects/1/image', true, 'projects.update', ['image' => '(multipart field image, PNG JPG WebP or GIF, up to 8 MB)'], ['ok' => true, 'data' => ['image' => '/api/v1/projects/1/image']]),
            self::entry('Projects', 'FR-3.2', 'Project image file', 'GET', '/api/v1/projects/1/image', true, 'projects.view', null, 'Image file'),
            self::entry('Plots', 'FR-3.7', 'Upload a plot image', 'POST', '/api/v1/plots/1/images', true, 'plots.update', ['image' => '(multipart field image, PNG JPG WebP or GIF, up to 8 MB, 12 images)'], ['ok' => true, 'data' => ['id' => 1, 'url' => '/api/v1/plots/1/images/abc.png']]),
            self::entry('Plots', 'FR-3.7', 'Plot image file', 'GET', '/api/v1/plots/1/images/abc.png', true, 'plots.view', null, 'Image file'),
            self::entry('Plots', 'FR-3.7', 'Remove a plot image', 'POST', '/api/v1/plots/1/images/1/delete', true, 'plots.update', new \stdClass(), ['ok' => true, 'data' => ['id' => 1, 'deleted' => true]]),
            self::entry('Leads', 'FR-4.2', 'Lead detail', 'GET', '/api/v1/leads/1', true, 'leads.view', null, ['ok' => true, 'data' => $lead]),
            self::entry('Leads', 'FR-4.2', 'Update lead', 'POST', '/api/v1/leads/1', true, 'leads.update', array_merge($leadInput, ['leadStage' => 'Qualified']), ['ok' => true, 'data' => ['id' => 1]]),
            self::entry('Leads', 'FR-4.2', 'Delete lead', 'POST', '/api/v1/leads/1/delete', true, 'leads.delete', new \stdClass(), ['ok' => true, 'data' => ['id' => 1, 'deleted' => true]]),
            self::entry('Leads', 'FR-4.6', 'Change stage', 'POST', '/api/v1/leads/1/stage', true, 'leads.update', ['leadStage' => 'Follow Up'], ['ok' => true, 'data' => array_merge($lead, ['lead_stage' => 'Follow Up'])]),
            self::entry('Leads', 'FR-4.6', 'Interested projects', 'POST', '/api/v1/leads/1/projects', true, 'leads.update', ['intrestedProjects' => ['1']], ['ok' => true, 'data' => array_merge($lead, ['interested_projects' => '1'])]),
            self::entry('Leads', 'FR-4.6', 'List notes', 'GET', '/api/v1/leads/1/notes', true, 'leads.view', null, ['ok' => true, 'data' => [['id' => 1, 'note_heading' => 'Call', 'note_content' => 'Asked for a site visit']]]),
            self::entry('Leads', 'FR-4.6', 'Add note', 'POST', '/api/v1/leads/1/notes', true, 'leads.update', ['noteHeading' => 'Call', 'noteContent' => 'Asked for a site visit'], ['ok' => true, 'data' => ['id' => 2]]),
            self::entry('Leads', 'FR-4.6', 'Delete note', 'POST', '/api/v1/leads/1/notes/2/delete', true, 'leads.update', new \stdClass(), ['ok' => true, 'data' => ['id' => 2, 'deleted' => true]]),
            self::entry('Leads', 'FR-4.6', 'List deals', 'GET', '/api/v1/leads/1/deals', true, 'leads.view', null, ['ok' => true, 'data' => [['id' => 1, 'deal_stage' => 'PROPOSAL_SUBMITTED', 'total_deal_value' => 250000000, 'deal_description' => 'East row', 'project_details' => '[{"projectId":1,"projectName":"Green Meadows","amount":250000000}]']]]),
            self::entry('Leads', 'FR-4.6', 'Add deal', 'POST', '/api/v1/leads/1/deals', true, 'leads.update', [
                'dealStage' => 'PROPOSAL_SUBMITTED', 'totalDealValue' => 2500000, 'dealDescription' => 'East row', 'projectId' => 1, 'amount' => 2500000, 'projectDetails' => [['projectId' => 1, 'amount' => 2500000]],
            ], ['ok' => true, 'data' => ['id' => 1]]),
            self::entry('Follow-ups', 'FR-6.1', 'Reschedule follow-up', 'POST', '/api/v1/followups/5/reschedule', true, 'followups.create', ['callScheduledDateTime' => '2026-10-31T11:00'], ['ok' => true, 'data' => ['id' => 5, 'status' => 'SCHEDULED']]),
            self::entry('Visits', 'FR-8.2', 'Mark visit done', 'POST', '/api/v1/visits/1/done', true, 'visits.attend', new \stdClass(), ['ok' => true, 'data' => ['id' => 1, 'status' => 'COMPLETED']]),
            self::entry('Visits', 'FR-8.1', 'Reschedule visit', 'POST', '/api/v1/visits/1/reschedule', true, 'visits.create', ['scheduledDateTime' => '2026-10-31T10:00'], ['ok' => true, 'data' => ['id' => 1, 'status' => 'SCHEDULED']]),
            self::entry('Tasks', 'FR-6.4', 'List tasks', 'GET', '/api/v1/tasks', true, 'followups.view', null, ['ok' => true, 'data' => [['id' => 1, 'lead_id' => 1, 'lead_name' => 'Mr. Ravi Kumar', 'subject' => 'Send brochure', 'priority' => 'MEDIUM', 'scheduled_at' => '2026-10-30 15:00:00', 'display_status' => 'SCHEDULED', 'assigned_name' => 'Priya']]]),
            self::entry('Mobile', 'FR-6.4', 'Filter tasks', 'POST', '/api/v1/tasks/filter', true, 'followups.view', [
                'status' => 'SCHEDULED', 'assignee' => 'Priya', 'range' => 'this_week', 'q' => 'brochure',
            ], ['ok' => true, 'data' => [['id' => 1, 'subject' => 'Send brochure', 'display_status' => 'SCHEDULED']], 'meta' => ['total' => 1]]),
            self::entry('Tasks', 'FR-6.4', 'Create task', 'POST', '/api/v1/tasks', true, 'followups.create', ['leadId' => 1, 'subject' => 'Send brochure', 'priority' => 'MEDIUM', 'scheduledDateTime' => '2026-10-30T15:00', 'assignedTo' => 2, 'description' => 'Email the east-row brochure'], ['ok' => true, 'data' => ['id' => 2]]),
            self::entry('Tasks', 'FR-6.4', 'Mark task done', 'POST', '/api/v1/tasks/1/done', true, 'followups.create', ['taskFeedback' => 'Brochure sent to the customer'], ['ok' => true, 'data' => ['id' => 1, 'status' => 'COMPLETED']]),
            self::entry('Tasks', 'FR-6.4', 'Reschedule task', 'POST', '/api/v1/tasks/1/reschedule', true, 'followups.create', ['scheduledDateTime' => '2026-10-31T15:00'], ['ok' => true, 'data' => ['id' => 1, 'status' => 'SCHEDULED']]),
            self::entry('Notifications', 'FR-15', 'Mark one read', 'POST', '/api/v1/notifications/1/read', true, null, new \stdClass(), ['ok' => true, 'data' => ['id' => 1]]),
            self::entry('Access', 'Roles', 'Permission catalog', 'GET', '/api/v1/access', true, 'roles.manage', null, ['ok' => true, 'data' => ['Leads' => ['leads.view', 'leads.create']]]),
            self::entry('Access', 'Roles', 'Role access', 'GET', '/api/v1/roles/8', true, 'roles.manage', null, ['ok' => true, 'data' => ['id' => 8, 'name' => 'Property Advisor', 'keys' => ['leads.view']]]),
            self::entry('Access', 'Roles', 'Allocate access', 'POST', '/api/v1/roles/8/access', true, 'roles.manage', ['keys' => ['leads.view', 'leads.create']], ['ok' => true, 'data' => ['id' => 8]]),
            self::entry('Access', 'Roles', 'Delete role', 'POST', '/api/v1/roles/9/delete', true, 'roles.manage', new \stdClass(), ['ok' => true, 'data' => ['id' => 9, 'deleted' => true]]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function postman(string $base): array
    {
        $items = [];

        foreach (self::entries() as $entry) {
            $headers = [['key' => 'Accept', 'value' => 'application/json']];

            if ($entry['auth']) {
                $headers[] = ['key' => 'Authorization', 'value' => 'Bearer {{token}}'];
            }

            if ($entry['method'] !== 'GET') {
                $headers[] = ['key' => 'Content-Type', 'value' => 'application/json'];
            }

            $request = [
                'method' => $entry['method'],
                'header' => $headers,
                'url'    => rtrim($base, '/') . $entry['path'],
            ];

            if ($entry['method'] !== 'GET') {
                $request['body'] = [
                    'mode' => 'raw',
                    'raw'  => json_encode($entry['sample_request'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                ];
            }

            $items[] = [
                'name'        => $entry['group'] . ' — ' . $entry['name'],
                'description' => $entry['how_to_send'],
                'request'     => $request,
            ];
        }

        return [
            'info' => [
                'name'   => 'Atrium API',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'variable' => [
                ['key' => 'token', 'value' => ''],
            ],
            'item' => $items,
        ];
    }

    /**
     * @param array<string, mixed>|object|null $request
     * @param array<string, mixed>|string $response
     * @return array<string, mixed>
     */
    private static function entry(string $group, string $spec, string $name, string $method, string $path, bool $auth, ?string $permission, array|object|null $request, array|string $response): array
    {
        $query = [];
        $route = $path;

        if (str_contains($path, '?')) {
            [$route, $queryString] = explode('?', $path, 2);
            parse_str($queryString, $query);
        }

        $multipart = $method !== 'GET' && is_array($request) && array_key_exists('file', $request);
        $headers   = ['Accept' => 'application/json'];

        if ($auth) {
            $headers['Authorization'] = 'Bearer <token from POST /api/v1/auth/login>';
        }

        if ($method !== 'GET') {
            $headers['Content-Type'] = $multipart ? 'multipart/form-data' : 'application/json';
        }

        $lines = [
            $method . ' ' . $path,
            'Accept: application/json',
        ];

        if ($auth) {
            $lines[] = 'Authorization: Bearer <token from POST /api/v1/auth/login>';
        }

        if ($method !== 'GET') {
            $lines[] = 'Content-Type: ' . $headers['Content-Type'];
            $lines[] = '';
            $lines[] = $multipart
                ? "Form fields, not JSON:\n" . json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : (string) json_encode($request ?? new \stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $lines[] = '';
            $lines[] = $query === []
                ? 'GET has no JSON body. Send the headers only.'
                : "GET has no JSON body. Send these values on the URL:\n" . json_encode($query, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $lines[] = '';
        $lines[] = $auth
            ? 'The workspace comes from the token. Do not send companyId or an owner id.'
            : 'This call does not send Authorization. Sign-in returns data.token. Send that token on every later call.';

        return [
            'group'           => $group,
            'spec'            => $spec,
            'name'            => $name,
            'method'          => $method,
            'path'            => $path,
            'auth'            => $auth,
            'permission'      => $permission,
            'headers'         => $headers,
            'query'           => $query === [] ? new \stdClass() : $query,
            'how_to_send'     => implode("\n", $lines),
            'sample_request'  => $method === 'GET' ? ($query === [] ? new \stdClass() : $query) : $request,
            'sample_response' => $response,
        ];
    }
}
