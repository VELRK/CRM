<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $month = $calendar['month'];
    $first = strtotime($month . '-01');
    $days  = (int) date('t', $first);
    $pad   = (int) date('N', $first) - 1;
    $prev  = date('Y-m', strtotime($month . '-01 -1 month'));
    $next  = date('Y-m', strtotime($month . '-01 +1 month'));
    $onDay = [];
    foreach ($calendar['tasks'] as $task) {
        $onDay[substr((string) $task['due_at'], 0, 10)][] = ['label' => $task['title'], 'kind' => 'task'];
    }
    foreach ($calendar['meetings'] as $meeting) {
        $onDay[substr((string) $meeting['happened_at'], 0, 10)][] = ['label' => $meeting['body'], 'kind' => 'meeting'];
    }
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Daily work</p>
        <h1><?= esc(date('F Y', $first)) ?></h1>
    </div>
    <div class="actions">
        <a class="ghost" href="<?= site_url('app/work/calendar?month=' . $prev) ?>">Previous</a>
        <a class="ghost" href="<?= site_url('app/work/calendar?month=' . $next) ?>">Next</a>
        <?php if (in_array('activities.create', $can, true)) : ?>
            <button class="primary" type="button" data-modal="meeting-modal" data-title="New meeting"><?= icon('plus') ?> New meeting</button>
        <?php endif; ?>
    </div>
</header>
<?= $this->include('partials/work_tabs') ?>
<div class="calendar">
    <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $label) : ?>
        <div class="dow"><?= $label ?></div>
    <?php endforeach; ?>
    <?php for ($i = 0; $i < $pad; $i++) : ?><div class="day pad"></div><?php endfor; ?>
    <?php for ($day = 1; $day <= $days; $day++) :
        $key = sprintf('%s-%02d', $month, $day);
    ?>
        <section class="day">
            <strong><?= $day ?></strong>
            <?php if (! empty($onDay[$key])) : ?>
                <ul>
                    <?php foreach ($onDay[$key] as $item) : ?>
                        <li class="<?= esc($item['kind']) ?>"><?= esc($item['label']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endfor; ?>
</div>
<dialog id="meeting-modal" class="modal" <?= ! empty($open) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= site_url('app/work/meetings') ?>" data-modal-form>
        <?= csrf_field() ?>
        <header class="modal-head">
            <h2 data-modal-title>New meeting</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>Meeting <input name="body" value="<?= esc((string) old('body')) ?>" required></label>
            <label>When <input type="datetime-local" name="happened_at" value="<?= esc((string) old('happened_at')) ?>" required></label>
            <label>Contact
                <select name="contact_id">
                    <option value="">None</option>
                    <?php foreach ($contacts as $contact) : ?>
                        <option value="<?= (int) $contact['id'] ?>"><?= esc($contact['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('calendar') ?> Add meeting</button>
        </footer>
    </form>
</dialog>
<?= $this->endSection() ?>
