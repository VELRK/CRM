<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow">People</p>
        <h1>Attendance</h1>
    </div>
    <?php if (in_array('attendance.mark', $can, true) || in_array('attendance.correct', $can, true)) : ?>
        <button class="primary" type="button" data-modal="attendance-modal" data-title="Mark attendance"><?= icon('plus') ?> Mark</button>
    <?php endif; ?>
</header>
<?php
$monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$days = (int) date('t', strtotime($month . '-01'));
$marks = ['present' => 'P', 'late' => 'Lt', 'leave' => 'L', 'absent' => 'A'];
$byUser = [];
foreach ($rows as $row) {
    $byUser[(int) $row['user_id']][substr((string) $row['work_date'], 0, 10)] = $row;
}
$people = array_values(array_filter($staff, static function (array $person): bool {
    $status = (string) ($person['status'] ?? '');
    if ($status === 'inactive' || $status === 'relieved') {
        return false;
    }
    return (string) ($person['attendance_applicable'] ?? '1') !== '0';
}));
$isThisMonth = $month === date('Y-m');
$todayNum = (int) date('j');
?>
<form class="search" method="get" action="<?= site_url('app/attendance') ?>">
    <label>Year
        <select name="year" onchange="this.form.submit()">
            <?php for ($y = (int) date('Y') - 1; $y <= (int) date('Y') + 1; $y++) : ?>
                <option value="<?= $y ?>" <?= (int) $year === $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </label>
    <label>Month
        <select name="month" onchange="this.form.submit()">
            <?php foreach ($monthNames as $index => $label) : ?>
                <option value="<?= $index + 1 ?>" <?= (int) $monthNum === $index + 1 ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <a class="ghost" href="<?= site_url('app/attendance') ?>">Today</a>
</form>
<div class="attend-split">
<div class="panel">
    <h2><?= esc($monthNames[$monthNum - 1] . ' ' . $year) ?></h2>
    <p class="lede">P present, Lt late, L leave, A absent. The highlighted column is today.</p>
    <div class="table-wrap attend-sheet">
        <table class="data attend-grid">
            <thead>
                <tr>
                    <th class="attend-name">User</th>
                    <?php for ($day = 1; $day <= $days; $day++) : ?>
                        <th class="<?= $isThisMonth && $day === $todayNum ? 'today-col' : '' ?>"><?= $day ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($people as $person) : ?>
                <tr>
                    <td class="attend-name">
                        <strong><?= esc((string) $person['name']) ?></strong>
                        <em><?= esc((string) ($person['role_name'] ?? '')) ?></em>
                    </td>
                    <?php for ($day = 1; $day <= $days; $day++) :
                        $date = sprintf('%s-%02d', $month, $day);
                        $mark = $byUser[(int) $person['user_id']][$date] ?? null;
                        $letter = $mark ? ($marks[(string) $mark['status']] ?? '·') : '';
                        ?>
                        <td class="<?= $isThisMonth && $day === $todayNum ? 'today-col' : '' ?>">
                            <span class="day-mark <?= $mark ? esc((string) $mark['status']) : '' ?>"><?= esc($letter) ?></span>
                        </td>
                    <?php endfor; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<aside class="panel">
    <h2>Today</h2>
    <p class="lede"><?= esc(date('d M Y')) ?></p>
    <?php foreach ($people as $person) :
        $mark = $todayByUser[(int) $person['user_id']] ?? null;
        $in = $mark && $mark['check_in'] ? date('H:i', strtotime((string) $mark['check_in'])) : '';
        $out = $mark && $mark['check_out'] ? date('H:i', strtotime((string) $mark['check_out'])) : '';
        ?>
        <div class="row">
            <div>
                <strong><?= esc((string) $person['name']) ?></strong>
                <em><?= esc((string) ($person['role_name'] ?? '')) ?></em>
                <?php if ($in !== '' || $out !== '') : ?><small><?= esc($in !== '' ? $in : '—') ?><?= $out !== '' ? ' – ' . esc($out) : '' ?></small><?php endif; ?>
            </div>
            <span class="badge <?= $mark ? '' : 'mute' ?>"><?= $mark ? esc(ucfirst((string) $mark['status'])) : 'Not marked' ?></span>
        </div>
    <?php endforeach; ?>
</aside>
</div>
<dialog id="attendance-modal" class="modal" <?= ! empty($open) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= site_url('app/attendance') ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="attendance">
        <input type="hidden" name="lat">
        <input type="hidden" name="lng">
        <input type="hidden" name="accuracy">
        <header class="modal-head">
            <h2 data-modal-title>Mark attendance</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <?php if (in_array('attendance.correct', $can, true)) : ?>
                <input type="hidden" name="correct" value="1">
                <label>Staff
                    <select name="user_id" required>
                        <?php foreach ($staff as $person) : ?>
                            <option value="<?= (int) $person['user_id'] ?>"><?= esc(person_label((string) $person['name'], (int) $person['user_id'], $designationByUser ?? [])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label>Date <input type="date" name="work_date" value="<?= esc(date('Y-m-d')) ?>" required></label>
            <?php if (! empty($locations)) : ?>
                <label>Location
                    <select name="location_id">
                        <option value="">Leave or absence needs no location</option>
                        <?php foreach ($locations as $place) : ?>
                            <option value="<?= (int) $place['id'] ?>"><?= esc((string) $place['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label>Status
                <select name="status">
                    <option value="present">Present</option>
                    <option value="late">Late</option>
                    <option value="leave">Leave</option>
                    <option value="absent">Absent</option>
                </select>
            </label>
            <label>Action
                <select name="action">
                    <option value="in">Check in</option>
                    <option value="out">Check out</option>
                </select>
            </label>
            <label>Note <input name="note"></label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('user-check') ?> Save</button>
        </footer>
    </form>
</dialog>
<script>
document.querySelector('#attendance-modal form')?.addEventListener('submit', function (event) {
    var form = event.currentTarget;
    if (!navigator.geolocation) return;
    event.preventDefault();
    navigator.geolocation.getCurrentPosition(function (position) {
        form.lat.value = position.coords.latitude;
        form.lng.value = position.coords.longitude;
        if (form.accuracy) form.accuracy.value = position.coords.accuracy;
        form.submit();
    }, function () { form.submit(); }, { timeout: 4000 });
});
</script>
<?= $this->endSection() ?>
