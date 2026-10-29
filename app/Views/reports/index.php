<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $tone = ['#1F6B5A', '#C4A574', '#3E6F8C', '#8D6A45', '#9B3A3A', '#6E9B8A', '#5C5348', '#D08A4C'];
    $split = $board['follow_split'];
    $pending = $board['pending_incentive'];
    $visitCount = static function (array $rows, array $names): int {
        $total = 0;
        foreach ($rows as $row) {
            if (in_array((string) $row['status'], $names, true)) {
                $total += (int) $row['total'];
            }
        }
        return $total;
    };
    $funnel = static function (array $rows) use ($tone): string {
        $shown = array_values(array_filter($rows, static fn (array $row): bool => (int) ($row['total'] ?? 0) > 0));
        if ($shown === []) {
            return '<div class="empty"><p>Nothing to chart yet.</p></div>';
        }
        $max = 1;
        foreach ($shown as $row) {
            $max = max($max, (int) $row['total']);
        }
        $html = '<div class="funnel">';
        foreach ($shown as $index => $row) {
            $width = max(36, (int) round((int) $row['total'] / $max * 100));
            $html .= '<div class="funnel-step" style="width:' . $width . '%;background:' . $tone[$index % count($tone)] . '"><span>' . esc((string) $row['label']) . '</span><strong>' . (int) $row['total'] . '</strong></div>';
        }
        return $html . '</div>';
    };
    $meters = static function (array $rows, string $labelKey, string $valueKey) use ($tone): string {
        $shown = array_values(array_filter($rows, static fn (array $row): bool => (int) ($row[$valueKey] ?? 0) > 0));
        if ($shown === []) {
            return '<div class="empty"><p>Nothing to chart yet.</p></div>';
        }
        $max = 1;
        foreach ($shown as $row) {
            $max = max($max, (int) $row[$valueKey]);
        }
        $html = '';
        foreach ($shown as $index => $row) {
            $width = max(2, (int) round((int) $row[$valueKey] / $max * 100));
            $html .= '<div class="meter"><span>' . esc((string) $row[$labelKey]) . '</span><span class="meter-track"><i class="meter-fill" style="width:' . $width . '%;background:' . $tone[$index % count($tone)] . '"></i></span><strong>' . (int) $row[$valueKey] . '</strong></div>';
        }
        return $html;
    };
    $points = [];
    $intakeMax = 1;
    foreach ($board['intake'] as $point) {
        $intakeMax = max($intakeMax, (int) $point['total']);
    }
    $width = 560;
    $count = count($board['intake']);
    $step = $count > 1 ? ($width - 16) / ($count - 1) : 0;
    foreach ($board['intake'] as $index => $point) {
        $x = 8 + ($index * $step);
        $y = 18 + (1 - ((int) $point['total'] / $intakeMax)) * 112;
        $points[] = [round($x, 1), round($y, 1), $point];
    }
    $line = implode(' ', array_map(static fn (array $point): string => $point[0] . ',' . $point[1], $points));
    $lastX = $points === [] ? 8 : $points[count($points) - 1][0];
    $area = '8,146 ' . $line . ' ' . $lastX . ',146';
    $visitRows = [];
    foreach ($board['visit_status'] as $row) {
        $visitRows[] = ['label' => ucfirst(str_replace('_', ' ', (string) $row['status'])), 'total' => (int) $row['total']];
    }
    $attendanceRows = [];
    foreach ($board['attendance'] as $row) {
        $attendanceRows[] = ['label' => ucfirst((string) $row['status']), 'total' => (int) $row['total']];
    }
    $incentiveRows = [];
    foreach ($board['incentives'] as $row) {
        $incentiveRows[] = ['label' => ucfirst(str_replace('_', ' ', (string) $row['status'])), 'total' => (int) $row['amount_minor'], 'count' => (int) $row['total']];
    }
    $conversion = [
        ['label' => 'Leads', 'total' => (int) $board['conversion']['leads']],
        ['label' => 'Visited', 'total' => (int) $board['conversion']['visited']],
        ['label' => 'Booked', 'total' => (int) $board['conversion']['booked']],
    ];
    $followRows = [
        ['label' => 'Done', 'total' => (int) $split['done']],
        ['label' => 'Upcoming', 'total' => (int) $split['upcoming']],
        ['label' => 'Overdue', 'total' => (int) $split['overdue']],
    ];
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Insight</p>
        <h1>Reports</h1>
        <p class="lede"><?= esc((string) $scope['label']) ?> view. Charts follow this role. Plot inventory stays the whole workspace, and sales figures follow the same people as the other charts.</p>
    </div>
    <?php if (in_array('exports.download', $can, true)) : ?>
        <div class="head-actions">
            <a class="ghost" href="<?= site_url('app/reports/excel') ?>">Excel</a>
            <a class="ghost" href="<?= site_url('app/reports/pdf') ?>">PDF</a>
        </div>
    <?php endif; ?>
</header>
<section class="kpis">
    <article>
        <span class="well"><?= icon('contact') ?></span>
        <div><strong><?= (int) $board['leads'] ?></strong><span>Leads</span><em><?= (int) $board['conversion']['visited'] ?> reached a visit</em></div>
    </article>
    <article>
        <span class="well"><?= icon('map-pin') ?></span>
        <div><strong><?= $visitCount($board['visit_status'], ['scheduled']) ?></strong><span>Visits scheduled</span><em><?= $visitCount($board['visit_status'], ['completed', 'attended']) ?> completed · <?= $visitCount($board['visit_status'], ['no_show']) ?> no-show</em></div>
    </article>
    <article>
        <span class="well"><?= icon('clipboard-list') ?></span>
        <div><strong><?= (int) $split['done'] ?></strong><span>Follow-ups done</span><em><?= (int) $split['overdue'] ?> overdue</em></div>
    </article>
    <article>
        <span class="well"><?= icon('receipt') ?></span>
        <div><strong><?= (int) $pending['total'] ?></strong><span>Incentives pending</span><em><?= esc(\App\Libraries\DeskService::money((int) $pending['amount_minor'], 'INR')) ?></em></div>
    </article>
</section>
<section class="chart-grid">
    <article class="panel chart-card chart-wide">
        <header class="chart-head"><div><h2>Lead intake</h2><p>Line of new leads over the last 14 days</p></div></header>
        <svg class="area-chart" viewBox="0 0 560 168" role="img" aria-label="Lead intake line">
            <defs>
                <linearGradient id="report-intake-fill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#1F6B5A" stop-opacity="0.28"/>
                    <stop offset="100%" stop-color="#1F6B5A" stop-opacity="0"/>
                </linearGradient>
            </defs>
            <polygon points="<?= esc($area, 'attr') ?>" fill="url(#report-intake-fill)"/>
            <polyline points="<?= esc($line, 'attr') ?>" fill="none" stroke="#1F6B5A" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
            <?php foreach ($points as $index => $point) : ?>
                <circle cx="<?= $point[0] ?>" cy="<?= $point[1] ?>" r="<?= (int) $point[2]['total'] > 0 ? 3.5 : 2 ?>" fill="#1F6B5A"/>
                <?php if ($index % 2 === 0) : ?>
                    <text x="<?= $point[0] ?>" y="162" text-anchor="middle" class="chart-axis"><?= esc(date('d M', strtotime((string) $point[2]['day']))) ?></text>
                <?php endif; ?>
            <?php endforeach; ?>
        </svg>
    </article>
    <article class="panel chart-card">
        <header class="chart-head"><div><h2>Lead sources</h2><p>Where the leads in this view came from</p></div></header>
        <?= $meters($board['sources'], 'label', 'total') ?>
    </article>
    <article class="panel chart-card">
        <header class="chart-head"><div><h2>Status funnel</h2><p>Lead, then each later stage</p></div></header>
        <?= $funnel($board['funnel']) ?>
    </article>
    <article class="panel chart-card">
        <header class="chart-head"><div><h2>Conversion</h2><p>Lead to visit to booking</p></div></header>
        <?= $funnel($conversion) ?>
    </article>
    <article class="panel chart-card">
        <header class="chart-head"><div><h2>Follow-up compliance</h2><p>Done against overdue and still upcoming</p></div></header>
        <?= $meters($followRows, 'label', 'total') ?>
    </article>
    <article class="panel chart-card">
        <header class="chart-head"><div><h2>Site visits</h2><p>Scheduled, completed and no-show</p></div></header>
        <?= $meters($visitRows, 'label', 'total') ?>
    </article>
    <article class="panel chart-card">
        <header class="chart-head"><div><h2>Attendance</h2><p>Days marked this month</p></div></header>
        <?= $meters($attendanceRows, 'label', 'total') ?>
    </article>
    <article class="panel chart-card">
        <header class="chart-head"><div><h2>Incentives</h2><p>Pending is still waiting to be approved or paid</p></div></header>
        <?php if ($incentiveRows === []) : ?>
            <div class="empty"><p>No incentives yet.</p></div>
        <?php else : ?>
            <?php foreach ($incentiveRows as $index => $row) : ?>
                <?php $max = max(array_column($incentiveRows, 'total') ?: [1]); ?>
                <div class="meter">
                    <span><?= esc($row['label']) ?></span>
                    <span class="meter-track"><i class="meter-fill" style="width:<?= max(2, (int) round($row['total'] / max(1, $max) * 100)) ?>%;background:<?= $tone[$index % count($tone)] ?>"></i></span>
                    <strong><?= esc(\App\Libraries\DeskService::money((int) $row['total'], 'INR')) ?></strong>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </article>
</section>
<section class="panel report-block">
    <h2>Staff performance</h2>
    <p class="lede">Leads owned, follow-ups logged, visits hosted and bookings credited.</p>
    <div class="table-scroll">
        <table class="data">
            <thead><tr><th>Person</th><th>Leads</th><th>Follow-ups</th><th>Overdue</th><th>Visits</th><th>Bookings</th></tr></thead>
            <tbody>
            <?php if ($board['staff'] === []) : ?>
                <tr><td colspan="6">No people in this view.</td></tr>
            <?php else : ?>
                <?php foreach ($board['staff'] as $row) : ?>
                    <tr>
                        <td><?= esc($row['name']) ?></td>
                        <td><?= (int) $row['leads'] ?></td>
                        <td><?= (int) $row['followups'] ?></td>
                        <td><?= (int) $row['overdue'] ?></td>
                        <td><?= (int) $row['visits'] ?></td>
                        <td><?= (int) $row['bookings'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="panel report-block">
    <h2>Team performance</h2>
    <p class="lede"><?= $scope['level'] === 'own' ? 'Team totals are shown to a team head and to the company.' : 'Each team is the sum of its members.' ?></p>
    <div class="table-scroll">
        <table class="data">
            <thead><tr><th>Team</th><th>Leads</th><th>Follow-ups</th><th>Visits</th><th>Bookings</th></tr></thead>
            <tbody>
            <?php if ($board['teams'] === []) : ?>
                <tr><td colspan="5">No team totals in this view.</td></tr>
            <?php else : ?>
                <?php foreach ($board['teams'] as $row) : ?>
                    <tr>
                        <td><?= esc($row['name']) ?></td>
                        <td><?= (int) $row['leads'] ?></td>
                        <td><?= (int) $row['followups'] ?></td>
                        <td><?= (int) $row['visits'] ?></td>
                        <td><?= (int) $row['bookings'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="panel report-block">
    <h2>Projects</h2>
    <p class="lede">Plot status is the inventory. Sales and value are the bookings in this view.</p>
    <div class="table-scroll">
        <table class="data">
            <thead>
                <tr>
                    <th>Project</th><th>Available</th><th>Hold</th><th>Booked</th><th>Sold</th><th>Registered</th><th>Blocked</th><th>Sales</th><th>Value</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($board['projects'] === []) : ?>
                <tr><td colspan="9">No projects yet.</td></tr>
            <?php else : ?>
                <?php foreach ($board['projects'] as $row) : ?>
                    <tr>
                        <td><?= esc($row['name']) ?></td>
                        <td><?= (int) $row['available'] ?></td>
                        <td><?= (int) $row['hold'] ?></td>
                        <td><?= (int) $row['booked'] ?></td>
                        <td><?= (int) $row['sold'] ?></td>
                        <td><?= (int) $row['registered'] ?></td>
                        <td><?= (int) $row['blocked'] ?></td>
                        <td><?= (int) $row['sales'] ?></td>
                        <td><?= esc(\App\Libraries\DeskService::money((int) $row['sale_value_minor'], 'INR')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?= $this->endSection() ?>
