<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Atrium') ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
    <script>
        (function () {
            var saved = localStorage.getItem('atrium-theme');
            var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.dataset.theme = theme;
        }());
    </script>
</head>
<body>
<?php
    $allowed = $can ?? [];
    $has     = static fn (string $key): bool => in_array($key, $allowed, true);
    $groups  = [
        'Menu' => [
            ['home', 'Home', 'layout-dashboard', null, 'app'],
        ],
        'Sites' => [
            ['staff', 'Staff', 'briefcase', 'staff.view', 'app/staff'],
            ['designations', 'Designations', 'briefcase', 'workspace.settings', 'app/designations'],
            ['teams', 'Teams', 'users', 'teams.view', 'app/teams'],
            ['projects', 'Projects', 'land-plot', 'projects.view', 'app/projects'],
            ['plots', 'Plots', 'map', 'plots.view', 'app/plots'],
            ['leads', 'Leads', 'contact', 'leads.view', 'app/leads'],
            ['followups', 'Follow-ups', 'clipboard-list', 'followups.view', 'app/followups'],
            ['visits', 'Site visits', 'map-pin', 'visits.view', 'app/visits'],
            ['attendance', 'Attendance', 'user-check', 'attendance.view', 'app/attendance'],
            ['incentives', 'Incentives', 'receipt', 'incentives.view', 'app/incentives'],
        ],
        'Masters' => [
            ['campaign-sources', 'Campaign source', 'file-text', 'masters.view', 'desk/index.html#campaign-sources'],
            ['lead-sources', 'Lead source', 'contact', 'masters.view', 'desk/index.html#lead-sources'],
            ['lead-stages', 'Lead stage', 'kanban', 'masters.view', 'desk/index.html#lead-stages'],
            ['followup-purposes', 'Follow-up purpose', 'clipboard-list', 'masters.view', 'desk/index.html#followup-purposes'],
            ['call-feedback', 'Call feedback', 'phone', 'masters.view', 'desk/index.html#call-feedback'],
            ['visit-feedback', 'Visit feedback', 'map-pin', 'masters.view', 'desk/index.html#visit-feedback'],
        ],
        'Insight' => [
            ['reports', 'Reports', 'chart-column', 'reports.view', 'app/reports'],
            ['desk', 'React desk', 'layout-dashboard', null, 'desk/index.html'],
            ['audit', 'Activity', 'scroll-text', 'team.view', 'app/audit'],
            ['push', 'Push alerts', 'bell', 'notifications.manage', 'desk/index.html#push'],
            ['whatsapp', 'WhatsApp', 'messages-square', 'whatsapp.manage', 'app/whatsapp'],
            ['lists', 'Lists', 'file-text', 'workspace.settings', 'app/lists'],
        ],
        'Access' => [
            ['roles', 'Roles', 'shield', 'team.view', 'app/roles'],
            ['team', 'Team', 'users', 'team.view', 'app/team'],
        ],
        'System' => [
            ['billing', 'Billing', 'credit-card', 'billing.manage', 'app/billing'],
            ['settings', 'Settings', 'settings', 'workspace.settings', 'app/settings'],
        ],
    ];
    $name    = (string) session()->get('user_name');
    $initial = mb_strtoupper(mb_substr($name, 0, 1));
?>
<div class="shell">
    <aside class="sidebar">
        <a class="brand" href="<?= site_url('app') ?>">
            <span class="brand-mark"><?= icon('building-2') ?></span>
            <span>
                <strong>Atrium</strong>
                <em><?= esc(session()->get('workspace_name')) ?></em>
            </span>
        </a>
        <?php foreach ($groups as $label => $items) : ?>
            <?php
                $visible = array_filter($items, static fn (array $item): bool => $item[3] === null || $has($item[3]));
                if ($visible === []) {
                    continue;
                }
            ?>
            <div class="nav-label"><?= esc($label) ?></div>
            <nav>
                <?php foreach ($visible as [$id, $text, $iconName, $permission, $href]) : ?>
                    <?php
                        $hash = '';
                        if (str_contains($href, '#')) {
                            [$href, $hash] = explode('#', $href, 2);
                            $hash = '#' . $hash;
                        }
                    ?>
                    <a class="<?= ($active ?? '') === $id ? 'active' : '' ?>" href="<?= site_url($href) . $hash ?>">
                        <?= icon($iconName) ?>
                        <span><?= esc($text) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endforeach; ?>
    </aside>
    <div class="workspace">
        <header class="topbar">
            <form class="top-search" method="get" action="<?= site_url('app/search') ?>">
                <?= icon('search') ?>
                <input name="q" value="<?= esc($query ?? '') ?>" placeholder="Search people, companies, deals, invoices">
            </form>
            <div class="top-actions">
                <button class="icon-button" type="button" data-theme-toggle aria-label="Toggle color theme"><?= icon('moon') ?></button>
                <a class="icon-button bell" href="<?= site_url('app/notices') ?>" aria-label="Notices">
                    <?= icon('bell') ?>
                    <?php if (($unread ?? 0) > 0) : ?><span><?= (int) $unread ?></span><?php endif; ?>
                </a>
                <div class="who">
                    <span class="avatar"><?= esc($initial) ?></span>
                    <span>
                        <strong><?= esc($name) ?></strong>
                        <em><?= esc(session()->get('role_name')) ?></em>
                    </span>
                </div>
                <form method="post" action="<?= site_url('logout') ?>">
                    <?= csrf_field() ?>
                    <button class="ghost" type="submit"><?= icon('log-out') ?> Sign out</button>
                </form>
            </div>
        </header>
        <main>
            <?php if ($message = session()->getFlashdata('message')) : ?>
                <p class="banner"><?= esc($message) ?></p>
            <?php endif; ?>
            <?php if ($error = session()->getFlashdata('error')) : ?>
                <p class="banner danger"><?= esc($error) ?></p>
            <?php endif; ?>
            <?= $this->renderSection('content') ?>
        </main>
    </div>
</div>
<dialog id="confirm-delete" class="modal">
    <form method="post" data-modal-form>
        <?= csrf_field() ?>
        <header class="modal-head">
            <h2 data-modal-title>Remove this record?</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body">
            <p>This removes it from the workspace.</p>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="danger" type="submit"><?= icon('trash-2') ?> Remove</button>
        </footer>
    </form>
</dialog>
<script src="<?= base_url('js/app.js') ?>"></script>
</body>
</html>
