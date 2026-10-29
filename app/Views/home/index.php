<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $tone = ['#1F6B5A', '#C4A574', '#3E6F8C', '#8D6A45', '#9B3A3A', '#6E9B8A', '#5C5348', '#D08A4C'];
    $split = $board['follow_split'];
    $intakeTotal = array_sum(array_column($board['intake'], 'total'));
    $visitDone = 0;
    foreach ($board['visit_status'] as $row) {
        if (in_array($row['status'], ['completed', 'attended'], true)) {
            $visitDone += (int) $row['total'];
        }
    }
    $plotTotal = array_sum(array_map(static fn (array $row): int => (int) $row['total'], $board['plots']));
    $bookedPlots = 0;
    foreach ($board['plots'] as $row) {
        if (in_array($row['status'], ['booked', 'sold', 'registered'], true)) {
            $bookedPlots += (int) $row['total'];
        }
    }
    $maxOf = static function (array $rows, string $key): int {
        $max = 1;
        foreach ($rows as $row) {
            $max = max($max, (int) ($row[$key] ?? 0));
        }
        return $max;
    };
    $rate = static function (int $part, int $whole): string {
        if ($whole < 1) {
            return '0%';
        }
        return (string) (int) round($part / $whole * 100) . '%';
    };
    $donut = static function (array $rows, string $valueKey) use ($tone): string {
        $total = 0;
        foreach ($rows as $row) {
            $total += (int) ($row[$valueKey] ?? 0);
        }
        if ($total < 1) {
            return '';
        }
        $radius = 46;
        $circ = 2 * M_PI * $radius;
        $offset = 0.0;
        $marks = '';
        $index = 0;
        foreach ($rows as $row) {
            $value = (int) ($row[$valueKey] ?? 0);
            if ($value < 1) {
                continue;
            }
            $length = $value / $total * $circ;
            $color = $tone[$index % count($tone)];
            $marks .= '<circle cx="70" cy="70" r="' . $radius . '" fill="none" stroke="' . $color . '" stroke-width="13" stroke-dasharray="' . round($length, 2) . ' ' . round($circ - $length, 2) . '" stroke-dashoffset="' . round(-$offset, 2) . '" transform="rotate(-90 70 70)"/>';
            $offset += $length;
            $index++;
        }
        return '<svg class="donut" viewBox="0 0 140 140" role="img" aria-label="' . $total . ' in total"><circle cx="70" cy="70" r="' . $radius . '" fill="none" stroke="currentColor" stroke-width="13" opacity="0.08"/>' . $marks . '<text x="70" y="66" text-anchor="middle" class="donut-total">' . $total . '</text><text x="70" y="84" text-anchor="middle" class="donut-caption">total</text></svg>';
    };
    $legend = static function (array $rows, string $labelKey, string $valueKey) use ($tone): string {
        $html = '<div class="legend">';
        $index = 0;
        foreach ($rows as $row) {
            if ((int) ($row[$valueKey] ?? 0) < 1) {
                continue;
            }
            $color = $tone[$index % count($tone)];
            $html .= '<div class="legend-row"><i style="background:' . $color . '"></i><span>' . esc((string) $row[$labelKey]) . '</span><strong>' . (int) $row[$valueKey] . '</strong></div>';
            $index++;
        }
        return $html . '</div>';
    };
    $meters = static function (array $rows, string $labelKey, string $valueKey, ?callable $caption = null) use ($maxOf, $tone): string {
        if ($rows === []) {
            return '<div class="empty"><p>Nothing to chart yet.</p></div>';
        }
        $max = $maxOf($rows, $valueKey);
        $html = '';
        foreach ($rows as $index => $row) {
            $value = (int) $row[$valueKey];
            $width = max(2, (int) round($value / $max * 100));
            $note = $caption === null ? (string) $value : $caption($row);
            $html .= '<div class="meter"><span>' . esc((string) $row[$labelKey]) . '</span><span class="meter-track"><i class="meter-fill" style="width:' . $width . '%;background:' . $tone[$index % count($tone)] . '"></i></span><strong>' . esc($note) . '</strong></div>';
        }
        return $html;
    };
    $points = [];
    $intakeMax = $maxOf($board['intake'], 'total');
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
    $steps = [
        ['Leads', (int) $board['conversion']['leads'], 'Every open record'],
        ['Visited', (int) $board['conversion']['visited'], $rate((int) $board['conversion']['visited'], (int) $board['conversion']['leads']) . ' of leads'],
        ['Booked', (int) $board['conversion']['booked'], (int) $board['conversion']['visited'] < 1 ? 'No visits yet' : $rate((int) $board['conversion']['booked'], (int) $board['conversion']['visited']) . ' of visits'],
    ];
    $stepMax = max(1, $steps[0][1]);
    $followRows = [
        ['label' => 'Done', 'total' => $split['done']],
        ['label' => 'Upcoming', 'total' => $split['upcoming']],
        ['label' => 'Overdue', 'total' => $split['overdue']],
    ];
    $attendanceRows = [];
    foreach ($board['attendance'] as $row) {
        $attendanceRows[] = ['label' => ucfirst((string) $row['status']), 'total' => (int) $row['total']];
    }
    $visitRows = [];
    foreach ($board['visit_status'] as $row) {
        $visitRows[] = ['label' => ucfirst(str_replace('_', ' ', (string) $row['status'])), 'total' => (int) $row['total']];
    }
    $incentiveRows = [];
    foreach ($board['incentives'] as $row) {
        $incentiveRows[] = [
            'label' => ucfirst(str_replace('_', ' ', (string) $row['status'])),
            'total' => (int) $row['amount_minor'],
            'count' => (int) $row['total'],
        ];
    }
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Today</p>
        <h1>Good to see you, <?= esc(session()->get('user_name')) ?></h1>
        <p class="lede"><?= esc((string) ($scope['label'] ?? 'Company')) ?>. Pipeline, inventory and follow-up pace for this view.</p>
    </div>
    <?php if (in_array('leads.create', $can, true)) : ?>
        <a class="primary" href="<?= site_url('app/leads?new=1') ?>"><?= icon('plus') ?> New lead</a>
    <?php endif; ?>
</header>
<section class="kpis">
    <article>
        <span class="well"><?= icon('contact') ?></span>
        <div><strong><?= (int) $board['leads'] ?></strong><span>Leads</span><em><?= (int) $intakeTotal ?> in 14 days</em></div>
    </article>
    <article>
        <span class="well"><?= icon('clipboard-list') ?></span>
        <div><strong><?= (int) $board['followups'] ?></strong><span>Open follow-ups</span><em><?= (int) $split['overdue'] ?> overdue</em></div>
    </article>
    <article>
        <span class="well"><?= icon('map-pin') ?></span>
        <div><strong><?= (int) $board['visits'] ?></strong><span>Visits scheduled</span><em><?= (int) $visitDone ?> completed</em></div>
    </article>
    <article>
        <span class="well"><?= icon('map') ?></span>
        <div><strong><?= (int) $board['available'] ?></strong><span>Plots available</span><em><?= (int) $bookedPlots ?> booked or sold</em></div>
    </article>
</section>
<section class="chart-grid">
    <article class="panel chart-card chart-wide">
        <header class="chart-head">
            <div><h2>Lead intake</h2><p>New leads on each of the last 14 days</p></div>
            <strong><?= (int) $intakeTotal ?></strong>
        </header>
        <svg class="area-chart" viewBox="0 0 560 168" role="img" aria-label="Lead intake, <?= (int) $intakeTotal ?> leads in 14 days">
            <defs>
                <linearGradient id="intake-fill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#1F6B5A" stop-opacity="0.28"/>
                    <stop offset="100%" stop-color="#1F6B5A" stop-opacity="0"/>
                </linearGradient>
            </defs>
            <?php foreach ([40, 74, 108] as $guide) : ?>
                <line x1="8" x2="552" y1="<?= $guide ?>" y2="<?= $guide ?>" class="chart-guide"/>
            <?php endforeach; ?>
            <polygon points="<?= esc($area, 'attr') ?>" fill="url(#intake-fill)"/>
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
        <header class="chart-head"><div><h2>Lead pipeline</h2><p>Where current leads sit</p></div></header>
        <?= $meters($board['statuses'], 'label', 'total') ?>
    </article>
    <article class="panel chart-card">
        <header class="chart-head"><div><h2>Plot inventory</h2><p><?= (int) $plotTotal ?> plots across projects</p></div></header>
        <?php if ($plotTotal < 1) : ?>
            <div class="empty"><p>No plots yet.</p></div>
        <?php else : ?>
            <div class="donut-wrap">
                <?= $donut($board['plots'], 'total') ?>
                <?= $legend($board['plots'], 'label', 'total') ?>
            </div>
        <?php endif; ?>
    </article>
    <article class="panel chart-card">
        <header class="chart-head"><div><h2>Conversion</h2><p>Lead, then visit, then booking</p></div></header>
        <div class="steps">
            <?php foreach ($steps as $index => $step) : ?>
                <div class="step">
                    <b><?= (int) $step[1] ?></b>
                    <i style="height: <?= max(12, (int) round($step[1] / $stepMax * 120)) ?>px; background: <?= $tone[$index] ?>"></i>
                    <span><?= esc($step[0]) ?></span>
                    <em><?= esc($step[2]) ?></em>
                </div>
            <?php endforeach; ?>
        </div>
    </article>
    <article class="panel chart-card">
        <header class="chart-head"><div><h2>Follow-up pace</h2><p>Done against what is still open</p></div></header>
        <?php if (array_sum(array_column($followRows, 'total')) < 1) : ?>
            <div class="empty"><p>No follow-ups yet.</p></div>
        <?php else : ?>
            <div class="donut-wrap">
                <?= $donut($followRows, 'total') ?>
                <?= $legend($followRows, 'label', 'total') ?>
            </div>
        <?php endif; ?>
    </article>
    <article class="panel chart-card">
        <header class="chart-head"><div><h2>Lead sources</h2><p>How people first arrived</p></div></header>
        <?= $meters($board['sources'], 'label', 'total') ?>
    </article>
    <article class="panel chart-card">
        <header class="chart-head"><div><h2>Site visits</h2><p>Scheduled through to no-show</p></div></header>
        <?= $meters($visitRows, 'label', 'total') ?>
    </article>
    <article class="panel chart-card">
        <header class="chart-head"><div><h2>Advisors</h2><p>Open lead load by owner</p></div></header>
        <?= $meters($board['owners'], 'name', 'total') ?>
    </article>
    <article class="panel chart-card">
        <header class="chart-head"><div><h2>Incentives</h2><p>Amount waiting in each state</p></div></header>
        <?= $meters($incentiveRows, 'label', 'total', static fn (array $row): string => \App\Libraries\DeskService::money((int) $row['total'], 'INR')) ?>
    </article>
    <article class="panel chart-card">
        <header class="chart-head"><div><h2>Attendance</h2><p>Marks recorded this month</p></div></header>
        <?php if ($attendanceRows === []) : ?>
            <div class="empty"><p>No attendance this month.</p></div>
        <?php else : ?>
            <div class="donut-wrap">
                <?= $donut($attendanceRows, 'total') ?>
                <?= $legend($attendanceRows, 'label', 'total') ?>
            </div>
        <?php endif; ?>
    </article>
    <article class="panel chart-card">
        <header class="chart-head"><div><h2>Due follow-ups</h2><p>Due today or already late</p></div></header>
        <?php if ($board['due'] === []) : ?>
            <div class="empty"><?= icon('inbox') ?><p>Nothing is due today.</p></div>
        <?php else : ?>
            <div class="list">
                <?php foreach ($board['due'] as $row) : ?>
                    <a class="row" href="<?= site_url('app/followups') ?>">
                        <span class="well"><?= icon('clipboard-list') ?></span>
                        <span class="row-copy">
                            <strong><?= esc($row['lead_name']) ?></strong>
                            <em><?= esc($row['body']) ?> · <?= esc(date('d M H:i', strtotime((string) $row['next_at']))) ?></em>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>
<?= $this->endSection() ?>
