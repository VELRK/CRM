<?php

namespace App\Libraries;

/**
 * Fixed permission catalog. A role can only be given keys from this list.
 */
class AccessCatalog
{
    public const PLAN_CAPS = [
        'starter'    => 8,
        'growth'     => 25,
        'enterprise' => 50,
    ];

    public const CONTACT_CAPS = [
        'starter'    => 2000,
        'growth'     => 25000,
        'enterprise' => 250000,
    ];

    public const PIPELINE_CAPS = [
        'starter'    => 1,
        'growth'     => 5,
        'enterprise' => 100,
    ];

    public const USER_CAPS = [
        'starter'    => 3,
        'growth'     => 15,
        'enterprise' => 100,
    ];

    /** @var array<string, int> */
    public const STORAGE_CAPS = [
        'starter'    => 2147483648,
        'growth'     => 21474836480,
        'enterprise' => 107374182400,
    ];

    public static function allows(string $plan, string $feature): bool
    {
        return match ($feature) {
            'money', 'reports' => in_array($plan, ['growth', 'enterprise'], true),
            'audit'            => $plan === 'enterprise',
            default            => true,
        };
    }

    /**
     * @return array<string, list<array{key: string, label: string}>>
     */
    public static function groups(): array
    {
        return [
            'People' => [
                ['key' => 'contacts.view', 'label' => 'View contacts'],
                ['key' => 'contacts.create', 'label' => 'Add contacts'],
                ['key' => 'contacts.update', 'label' => 'Edit contacts'],
                ['key' => 'contacts.delete', 'label' => 'Delete contacts'],
                ['key' => 'contacts.assign', 'label' => 'Set a contact owner'],
            ],
            'Companies' => [
                ['key' => 'companies.view', 'label' => 'View companies'],
                ['key' => 'companies.create', 'label' => 'Add companies'],
                ['key' => 'companies.update', 'label' => 'Edit companies'],
                ['key' => 'companies.delete', 'label' => 'Delete companies'],
            ],
            'Pipeline' => [
                ['key' => 'deals.view', 'label' => 'View deals'],
                ['key' => 'deals.create', 'label' => 'Add deals'],
                ['key' => 'deals.update', 'label' => 'Edit deals'],
                ['key' => 'deals.delete', 'label' => 'Delete deals'],
                ['key' => 'deals.move_stage', 'label' => 'Move a deal stage'],
            ],
            'Work' => [
                ['key' => 'tasks.view', 'label' => 'View tasks'],
                ['key' => 'tasks.create', 'label' => 'Add tasks'],
                ['key' => 'tasks.update', 'label' => 'Edit tasks'],
                ['key' => 'tasks.delete', 'label' => 'Delete tasks'],
                ['key' => 'activities.create', 'label' => 'Log a note or call'],
            ],
            'Catalog' => [
                ['key' => 'products.view', 'label' => 'View products'],
                ['key' => 'products.create', 'label' => 'Add products'],
                ['key' => 'products.update', 'label' => 'Edit products'],
                ['key' => 'products.delete', 'label' => 'Delete products'],
            ],
            'Quotes' => [
                ['key' => 'quotes.view', 'label' => 'View quotes'],
                ['key' => 'quotes.create', 'label' => 'Add quotes'],
                ['key' => 'quotes.update', 'label' => 'Edit quotes'],
                ['key' => 'quotes.delete', 'label' => 'Delete quotes'],
                ['key' => 'quotes.send', 'label' => 'Send a quote'],
            ],
            'Invoices' => [
                ['key' => 'invoices.view', 'label' => 'View invoices'],
                ['key' => 'invoices.create', 'label' => 'Add invoices'],
                ['key' => 'invoices.update', 'label' => 'Edit invoices'],
                ['key' => 'invoices.delete', 'label' => 'Delete invoices'],
                ['key' => 'invoices.send', 'label' => 'Send an invoice'],
                ['key' => 'payments.record', 'label' => 'Record a payment'],
            ],
            'Files' => [
                ['key' => 'files.view', 'label' => 'View files'],
                ['key' => 'files.attach', 'label' => 'Attach files'],
                ['key' => 'files.delete', 'label' => 'Delete files'],
            ],
            'Reports' => [
                ['key' => 'reports.view', 'label' => 'View reports'],
            ],
            'Staff' => [
                ['key' => 'staff.view', 'label' => 'View staff'],
                ['key' => 'staff.update', 'label' => 'Edit staff profiles'],
            ],
            'Projects' => [
                ['key' => 'projects.view', 'label' => 'View projects'],
                ['key' => 'projects.create', 'label' => 'Add projects'],
                ['key' => 'projects.update', 'label' => 'Edit projects'],
                ['key' => 'projects.delete', 'label' => 'Delete projects'],
            ],
            'Plots' => [
                ['key' => 'plots.view', 'label' => 'View plot inventory'],
                ['key' => 'plots.create', 'label' => 'Add plots'],
                ['key' => 'plots.update', 'label' => 'Edit plots'],
                ['key' => 'plots.delete', 'label' => 'Delete plots'],
            ],
            'Leads' => [
                ['key' => 'leads.view', 'label' => 'View leads'],
                ['key' => 'leads.create', 'label' => 'Add leads'],
                ['key' => 'leads.update', 'label' => 'Edit leads'],
                ['key' => 'leads.delete', 'label' => 'Delete leads'],
                ['key' => 'leads.transfer', 'label' => 'Transfer a lead'],
                ['key' => 'leads.restore', 'label' => 'Restore a removed lead'],
            ],
            'Follow-ups' => [
                ['key' => 'followups.view', 'label' => 'View follow-ups'],
                ['key' => 'followups.create', 'label' => 'Log a follow-up'],
            ],
            'Site visits' => [
                ['key' => 'visits.view', 'label' => 'View site visits'],
                ['key' => 'visits.create', 'label' => 'Schedule a site visit'],
                ['key' => 'visits.attend', 'label' => 'Mark visit attendance'],
            ],
            'Teams' => [
                ['key' => 'teams.view', 'label' => 'View teams'],
                ['key' => 'teams.manage', 'label' => 'Create and edit teams'],
            ],
            'Attendance' => [
                ['key' => 'attendance.view', 'label' => 'View attendance'],
                ['key' => 'attendance.mark', 'label' => 'Check in and check out'],
                ['key' => 'attendance.correct', 'label' => 'Correct attendance'],
            ],
            'Sales' => [
                ['key' => 'sales.view', 'label' => 'View plot sales'],
                ['key' => 'incentives.view', 'label' => 'View incentives'],
                ['key' => 'incentives.approve', 'label' => 'Approve or pay incentives'],
            ],
            'WhatsApp' => [
                ['key' => 'whatsapp.manage', 'label' => 'Manage WhatsApp templates'],
            ],
            'Push alerts' => [
                ['key' => 'notifications.manage', 'label' => 'Manage push alerts'],
            ],
            'Downloads' => [
                ['key' => 'exports.download', 'label' => 'Download lists'],
            ],
            'Team' => [
                ['key' => 'team.view', 'label' => 'View the team'],
                ['key' => 'team.invite', 'label' => 'Invite people'],
                ['key' => 'team.deactivate', 'label' => 'Deactivate people'],
                ['key' => 'roles.manage', 'label' => 'Add roles and allocate access'],
            ],
            'Workspace' => [
                ['key' => 'workspace.settings', 'label' => 'Edit workspace settings'],
                ['key' => 'billing.manage', 'label' => 'Change the plan'],
            ],
        ];
    }

    /**
     * Each module, and each action inside it, is allowed only when the role has that permission.
     * The owner is allowed every action.
     *
     * @param list<string> $permissions
     * @return list<array{module: string, allowed: bool, actions: list<array{key: string, label: string, allowed: bool}>}>
     */
    public static function modules(array $permissions, bool $isOwner): array
    {
        $held = array_flip($permissions);
        $modules = [];

        foreach (self::groups() as $name => $items) {
            $actions = [];
            $allowed = false;

            foreach ($items as $item) {
                $yes = $isOwner || isset($held[$item['key']]);
                $actions[] = [
                    'key'     => $item['key'],
                    'label'   => $item['label'],
                    'allowed' => $yes,
                ];
                $allowed = $allowed || $yes;
            }

            $modules[] = [
                'module'  => $name,
                'allowed' => $allowed,
                'actions' => $actions,
            ];
        }

        return $modules;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        $keys = [];

        foreach (self::groups() as $items) {
            foreach ($items as $item) {
                $keys[] = $item['key'];
            }
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    public static function forStarter(string $name): array
    {
        $all = self::keys();

        return match ($name) {
            'Owner' => $all,
            'Admin' => array_values(array_filter(
                $all,
                static fn (string $key): bool => $key !== 'billing.manage',
            )),
            'Manager' => [
                'contacts.view', 'contacts.create', 'contacts.update', 'contacts.assign',
                'companies.view', 'companies.create', 'companies.update',
                'deals.view', 'deals.create', 'deals.update', 'deals.move_stage',
                'tasks.view', 'tasks.create', 'tasks.update',
                'activities.create',
                'products.view',
                'quotes.view', 'quotes.create', 'quotes.update', 'quotes.send',
                'invoices.view', 'invoices.create', 'invoices.update', 'invoices.send',
                'payments.record',
                'files.view', 'files.attach',
                'reports.view',
                'team.view',
                'staff.view', 'staff.update',
                'projects.view', 'projects.create', 'projects.update',
                'plots.view', 'plots.create', 'plots.update',
                'leads.view', 'leads.create', 'leads.update', 'leads.transfer',
                'followups.view', 'followups.create',
                'visits.view', 'visits.create', 'visits.attend',
                'teams.view', 'attendance.view', 'sales.view', 'incentives.view', 'exports.download',
            ],
            'Team Head' => [
                'staff.view', 'team.view', 'teams.view',
                'projects.view', 'plots.view',
                'leads.view', 'leads.update', 'leads.transfer',
                'followups.view', 'followups.create',
                'visits.view', 'visits.create', 'visits.attend',
                'attendance.view', 'reports.view', 'sales.view', 'incentives.view', 'exports.download', 'files.view',
            ],
            'Property Advisor' => [
                'staff.view', 'projects.view', 'plots.view',
                'leads.view', 'leads.create', 'leads.update',
                'followups.view', 'followups.create',
                'visits.view', 'visits.attend',
                'attendance.view', 'attendance.mark',
                'reports.view', 'exports.download',
                'files.view', 'files.attach',
            ],
            'Member' => [
                'contacts.view', 'contacts.create', 'contacts.update',
                'companies.view',
                'deals.view', 'deals.create', 'deals.update', 'deals.move_stage',
                'tasks.view', 'tasks.create', 'tasks.update',
                'activities.create',
                'quotes.view', 'quotes.create', 'quotes.update',
                'files.view', 'files.attach',
                'staff.view',
                'projects.view', 'plots.view',
                'leads.view', 'leads.create', 'leads.update',
                'followups.view', 'followups.create',
                'visits.view', 'visits.create', 'visits.attend',
                'attendance.view', 'attendance.mark',
            ],
            'Viewer' => array_values(array_filter(
                $all,
                static fn (string $key): bool => str_ends_with($key, '.view'),
            )),
            default => [],
        };
    }
}
