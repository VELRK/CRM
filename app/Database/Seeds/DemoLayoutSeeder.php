<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Demo inventory for the Northwind workspace: 5 projects, 25 plots each,
 * 20 staff, and 30 leads with a follow-up, visit, and task.
 * Safe to run again. Existing Green Meadows rows are left in place.
 */
class DemoLayoutSeeder extends Seeder
{
    public function run(): void
    {
        $db = $this->db;
        $owner = $db->table('users')->where('email', 'priya@example.com')->get()->getRowArray();

        if ($owner === null) {
            echo "Priya is not in this database, so the demo was not loaded.\n";

            return;
        }

        $membership = $db->table('tenant_users')->where('user_id', (int) $owner['id'])->get()->getRowArray();

        if ($membership === null) {
            echo "Priya has no workspace, so the demo was not loaded.\n";

            return;
        }

        if ($db->table('users')->where('email', 'seed.01@example.com')->countAllResults() > 0) {
            echo "Demo layout is already loaded.\n";

            return;
        }

        $tenantId = (int) $membership['tenant_id'];
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $hash = password_hash('password1', defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
        $headRole = $this->roleId($tenantId, 'Team Head');
        $advisorRole = $this->roleId($tenantId, 'Property Advisor');

        $names = [
            'Aravind Kumar', 'Divya Menon', 'Karthik Raja', 'Sneha Iyer',
            'Naveen Das', 'Priyanka Shah', 'Rahul Varma', 'Anitha Joseph',
            'Vikram Nair', 'Lakshmi Devi', 'Sanjay Pillai', 'Meera Krishnan',
            'Harish Babu', 'Kavya Reddy', 'Arun Prakash', 'Deepa Sundar',
            'Manoj Gupta', 'Revathi Selvam', 'Farhan Ali', 'Ishita Bose',
        ];
        $teamNames = ['North wing', 'South wing', 'West wing', 'Central wing'];
        $userIds = [];

        foreach ($names as $index => $name) {
            $number = $index + 1;
            $db->table('users')->insert([
                'name'          => $name,
                'email'         => sprintf('seed.%02d@example.com', $number),
                'password_hash' => $hash,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $userIds[] = (int) $db->insertID();
        }

        $teamIds = [];

        foreach ($teamNames as $index => $teamName) {
            $db->table('teams')->insert([
                'tenant_id'    => $tenantId,
                'name'         => $teamName,
                'head_user_id' => $userIds[$index],
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
            $teamIds[] = (int) $db->insertID();
        }

        foreach ($userIds as $index => $userId) {
            $isHead = $index < 4;
            $teamId = $teamIds[$isHead ? $index : ($index - 4) % 4];
            $db->table('tenant_users')->insert([
                'tenant_id'  => $tenantId,
                'user_id'    => $userId,
                'role_id'    => $isHead ? $headRole : $advisorRole,
                'created_at' => $now,
            ]);
            $db->table('staff_profiles')->insert([
                'tenant_id'             => $tenantId,
                'user_id'               => $userId,
                'designation'           => $isHead ? 'team_head' : 'property_advisor',
                'phone'                 => sprintf('98400%05d', 20000 + $index),
                'phone_number'          => sprintf('98400%05d', 20000 + $index),
                'profile'               => $isHead ? 'Team Head' : 'Property Advisor',
                'status'                => 'active',
                'employee_code'         => sprintf('EMP%03d', 100 + $index),
                'joining_on'            => '2026-04-01',
                'city'                  => 'Coimbatore',
                'state'                 => 'Tamil Nadu',
                'attendance_applicable' => 1,
                'lead_eligible'         => 1,
                'incentive_eligible'    => 1,
                'team_id'               => $teamId,
                'group_id'              => $teamId,
                'user_status'           => 1,
                'active'                => 1,
                'created_at'            => $now,
                'updated_at'            => $now,
            ]);
            $db->table('team_memberships')->insert([
                'tenant_id'  => $tenantId,
                'team_id'    => $teamId,
                'user_id'    => $userId,
                'from_on'    => '2026-04-01',
                'created_at' => $now,
            ]);
        }

        $projects = [
            ['Grand Town', 'Saravanampatti'],
            ['Palm Grove', 'Peelamedu'],
            ['Lake View', 'Vadavalli'],
            ['Green Field', 'Singanallur'],
            ['Royal Square', 'RS Puram'],
        ];
        $projectIds = [];

        foreach ($projects as [$name, $location]) {
            $db->table('projects')->insert([
                'tenant_id'    => $tenantId,
                'name'         => $name,
                'location'     => $location . ', Coimbatore',
                'status'       => 'ongoing',
                'project_type' => 'Plotted layout',
                'total_plots'  => 25,
                'launch_on'    => '2026-06-01',
                'notes'        => 'Demo layout for testing.',
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
            $projectIds[] = (int) $db->insertID();
        }

        $facings = ['North', 'South', 'East', 'West', 'South-West'];
        $customers = ['Ramesh K', 'Geetha S', 'Imran P', 'Sowmya R', 'Balaji M', 'Nithya V', 'Prakash T', 'Ayesha K', 'Dinesh L', 'Kiran J', 'Revathi A', 'Mohammed S', 'Padma N'];
        $statuses = array_merge(
            array_fill(0, 10, 'available'),
            array_fill(0, 3, 'advance'),
            array_fill(0, 4, 'booked'),
            array_fill(0, 3, 'sold'),
            array_fill(0, 2, 'owner_use'),
            array_fill(0, 2, 'future'),
            ['registered'],
        );

        foreach ($projectIds as $projectIndex => $projectId) {
            $headId = $userIds[$projectIndex % 4];

            foreach ($statuses as $plotIndex => $status) {
                $needsCustomer = in_array($status, ['advance', 'booked', 'sold', 'registered'], true);
                $slot = $plotIndex - 9;
                $bookingOn = null;
                $advanceOn = null;
                $registeredOn = $status === 'registered' ? '2026-09-20' : null;
                $advance = 0;
                $bookingAmount = 0;

                if ($needsCustomer) {
                    $bookingAmount = 350000000 + ($plotIndex * 1000000);
                    $advance = $status === 'sold' || $status === 'registered' ? $bookingAmount : 50000000;

                    if ($status === 'booked' && $slot === 4) {
                        $bookingOn = null;
                    } elseif ($status === 'advance' && $slot === 3) {
                        $bookingOn = '2026-08-01';
                        $advanceOn = '2026-08-01';
                    } elseif ($status === 'booked' && $slot === 7) {
                        $bookingOn = '2026-08-15';
                        $advanceOn = '2026-08-15';
                    } elseif ($slot % 2 === 0) {
                        $bookingOn = '2026-10-01';
                        $advanceOn = '2026-10-01';
                    } else {
                        $bookingOn = '2026-10-27';
                        $advanceOn = '2026-10-27';
                    }
                }

                $associate = $userIds[4 + (($projectIndex * 3 + $plotIndex) % 16)];
                $db->table('plots')->insert([
                    'tenant_id'                => $tenantId,
                    'project_id'               => $projectId,
                    'plot_no'                  => sprintf('%02d', $plotIndex + 1),
                    'block'                    => 'A',
                    'phase'                    => 'Phase 1',
                    'area_sqft'                => 1200 + (($plotIndex % 5) * 150),
                    'facing'                   => $facings[$plotIndex % count($facings)],
                    'price_minor'              => $bookingAmount > 0 ? $bookingAmount : 280000000,
                    'status'                   => $status,
                    'unit'                     => 'sqft',
                    'customer_name'            => $needsCustomer ? $customers[$plotIndex % count($customers)] : null,
                    'customer_phone'           => $needsCustomer ? sprintf('98410%05d', ($projectIndex * 25) + $plotIndex) : null,
                    'associate_user_id'        => $needsCustomer ? $associate : null,
                    'head_user_id'             => $needsCustomer ? $headId : null,
                    'booking_on'               => $bookingOn,
                    'advance_minor'            => $advance,
                    'advance_received_on'      => $advanceOn,
                    'booking_amount_minor'     => $bookingAmount,
                    'expected_registration_on' => $advanceOn,
                    'registered_on'            => $registeredOn,
                    'remarks'                  => $status === 'registered' ? 'Registration completed' : null,
                    'created_at'               => $now,
                    'updated_at'               => $now,
                ]);
            }
        }

        $stages = ['New', 'Qualified', 'Follow Up', 'Proposal Sent', 'Site Visit Scheduled', 'Negotiation', 'Booked', 'Lost'];
        $stageCodes = ['new', 'contacted', 'followup', 'followup', 'site_visit', 'negotiation', 'booked', 'lost'];
        $sources = ['Walk-in', 'Personal', 'WhatsApp', 'Assigned'];
        $firstNames = ['Anand', 'Bhavani', 'Chitra', 'Dharun', 'Elango', 'Fathima', 'Gopal', 'Hema', 'Iniyan', 'Janani'];
        $purposes = ['Introduction', 'Follow up', 'Site visit reminder', 'Negotiation', 'Closure'];
        $priorities = ['LOW', 'MEDIUM', 'HIGH'];

        for ($i = 0; $i < 30; $i++) {
            $stageIndex = $i % count($stages);
            $projectId = $projectIds[$i % count($projectIds)];
            $advisor = $userIds[4 + ($i % 16)];
            $head = $userIds[$i % 4];
            $phone = sprintf('9840011%03d', $i + 1);
            $first = $firstNames[$i % count($firstNames)];
            $last = 'Demo ' . ($i + 1);
            $full = $first . ' ' . $last;
            $db->table('leads')->insert([
                'tenant_id'          => $tenantId,
                'name'               => $full,
                'phone'              => $phone,
                'phone_number'       => $phone,
                'phone_key'          => $phone,
                'email'              => sprintf('lead%02d@example.com', $i + 1),
                'source'             => $sources[$i % count($sources)],
                'lead_source'        => $sources[$i % count($sources)],
                'project_id'         => $projectId,
                'project_name'       => $projects[$i % count($projects)][0],
                'owner_id'           => $advisor,
                'status'             => $stageCodes[$stageIndex],
                'lead_stage'         => $stages[$stageIndex],
                'client_name_prefix' => 'Mr.',
                'client_first_name'  => $first,
                'client_last_name'   => $last,
                'assigned_to'        => $advisor,
                'assigned_type'      => 'USER',
                'assigned_name'      => $names[4 + ($i % 16)],
                'assigned_by'        => (int) $owner['id'],
                'assigned_date'      => $now,
                'client_city'        => 'Coimbatore',
                'client_state'       => 'Tamil Nadu',
                'description'        => 'Demo lead for layout testing.',
                'interested_projects'=> (string) $projectId,
                'inserted_by'        => (int) $owner['id'],
                'team_id'            => $teamIds[$i % 4],
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
            $leadId = (int) $db->insertID();
            $bucket = $i % 4;
            $when = match ($bucket) {
                0 => date('Y-m-d H:i:s', strtotime('+2 days 10:00')),
                1 => date('Y-m-d H:i:s', strtotime('-3 days 11:00')),
                2 => date('Y-m-d H:i:s', strtotime('-1 day 15:00')),
                default => date('Y-m-d H:i:s', strtotime('+5 days 16:00')),
            };
            $state = match ($bucket) {
                1 => 'COMPLETED',
                3 => 'CANCELLED',
                default => 'SCHEDULED',
            };
            $done = $state === 'COMPLETED' ? $when : null;
            $subject = $purposes[$i % count($purposes)];
            $db->table('followups')->insert([
                'tenant_id'          => $tenantId,
                'lead_id'            => $leadId,
                'user_id'            => $advisor,
                'body'               => $subject . ' with ' . $full,
                'next_at'            => $when,
                'done_at'            => $done,
                'status'             => $state === 'COMPLETED' ? 'done' : 'pending',
                'followup_type'      => 'call',
                'assigned_user_id'   => $advisor,
                'call_subject'       => substr($subject, 0, 30),
                'call_purpose'       => $subject,
                'call_description'   => $subject . ' with ' . $full,
                'call_scheduled_at'  => $when,
                'current_state'      => $state,
                'assigned_to'        => $advisor,
                'assigned_by'        => $head,
                'completed_date'     => $done,
                'inserted_by'        => (int) $owner['id'],
                'created_at'         => $now,
            ]);
            $db->table('site_visits')->insert([
                'tenant_id'            => $tenantId,
                'lead_id'              => $leadId,
                'project_id'           => $projectId,
                'staff_id'             => $advisor,
                'scheduled_at'         => $when,
                'status'               => $state === 'COMPLETED' ? 'completed' : ($state === 'CANCELLED' ? 'cancelled' : 'scheduled'),
                'note'                 => 'Site walk for ' . $full,
                'description'          => 'Site walk for ' . $full,
                'current_state'        => $state,
                'assigned_to'          => $advisor,
                'visit_type'           => 'Site visit',
                'scheduled_date_time'  => $when,
                'completed_date'       => $done,
                'inserted_by'          => (int) $owner['id'],
                'assigned_by'          => $head,
                'created_by'           => (int) $owner['id'],
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
            $db->table('lead_tasks')->insert([
                'tenant_id'     => $tenantId,
                'lead_id'       => $leadId,
                'assigned_to'   => $advisor,
                'assigned_by'   => $head,
                'subject'       => 'Follow ' . $first,
                'priority'      => $priorities[$i % 3],
                'task_type'     => 'LEAD',
                'description'   => 'Demo task for ' . $full,
                'current_state' => $state,
                'scheduled_at'  => $when,
                'completed_date'=> $done,
                'inserted_by'   => (int) $owner['id'],
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        $marks = ['present', 'late', 'leave', 'absent'];

        foreach ($userIds as $index => $userId) {
            $status = $marks[$index % 4];
            $db->table('attendance')->insert([
                'tenant_id'  => $tenantId,
                'user_id'    => $userId,
                'work_date'  => $today,
                'check_in'   => in_array($status, ['present', 'late'], true) ? $today . ($status === 'late' ? ' 10:20:00' : ' 09:05:00') : null,
                'status'     => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        echo "Loaded 5 projects, 125 plots, 20 users, 30 leads, follow-ups, visits, and tasks.\n";
    }

    private function roleId(int $tenantId, string $name): int
    {
        $role = $this->db->table('roles')
            ->where('tenant_id', $tenantId)
            ->where('name', $name)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($role === null) {
            throw new \RuntimeException($name . ' role is missing.');
        }

        return (int) $role['id'];
    }
}
