<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $markId = (int) old('visit_id');
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Sites</p>
        <h1>Site visits</h1>
        <p class="lede">Schedule a visit, then mark who attended. Nothing is marked on its own.</p>
    </div>
    <?php if (in_array('visits.create', $can, true)) : ?>
        <button class="primary" type="button" data-modal="visit-modal" data-title="Schedule a visit" data-lead_id="" data-staff_id="" data-scheduled_at="" data-project_id="" data-plot_id="" data-note=""><?= icon('plus') ?> Schedule</button>
    <?php endif; ?>
</header>
<?php if ($visits === []) : ?>
    <section class="panel"><div class="empty"><?= icon('inbox') ?><p>No site visits yet.</p></div></section>
<?php else : ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>When</th><th>Lead</th><th>Staff</th><th>Place</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($visits as $visit) :
                $place = trim((string) ($visit['project_name'] ?? '') . (($visit['plot_no'] ?? '') !== '' ? ' · ' . $visit['plot_no'] : ''));
            ?>
                <tr>
                    <td><?= esc(date('d M Y, H:i', strtotime((string) $visit['scheduled_at']))) ?></td>
                    <td><strong><?= esc($visit['lead_name']) ?></strong></td>
                    <td><?= esc($visit['staff_name'] ? person_label((string) $visit['staff_name'], (int) $visit['staff_id'], $designationByUser ?? []) : '—') ?></td>
                    <td><?= esc($place !== '' ? $place : '—') ?></td>
                    <td><span class="badge <?= $visit['status'] === 'planned' ? 'mute' : '' ?>"><?= esc(ucfirst((string) $visit['status'])) ?></span></td>
                    <td class="actions-cell">
                        <?php if (in_array('visits.attend', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="attendance-modal" data-action="<?= site_url('app/visits/' . $visit['id'] . '/attendance') ?>" data-title="Attendance · <?= esc($visit['lead_name'], 'attr') ?>" data-visit_id="<?= (int) $visit['id'] ?>" data-status="<?= $visit['status'] === 'missed' ? 'missed' : 'attended' ?>" data-note="<?= esc((string) $visit['note'], 'attr') ?>"><?= icon('user-check') ?> Attendance</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php if (in_array('visits.create', $can, true)) : ?>
<dialog id="visit-modal" class="modal" <?= ! empty($open) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= site_url('app/visits') ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="visit">
        <header class="modal-head">
            <h2 data-modal-title>Schedule a visit</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>Lead
                <select name="lead_id" data-field="lead_id" required>
                    <option value="">Choose</option>
                    <?php foreach ($leads as $lead) : ?>
                        <option value="<?= (int) $lead['id'] ?>" <?= (string) old('lead_id') === (string) $lead['id'] ? 'selected' : '' ?>><?= esc($lead['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="split">
                <label>Staff
                    <select name="staff_id" data-field="staff_id" required>
                        <option value="">Choose</option>
                        <?php foreach ($staff as $person) : ?>
                            <option value="<?= (int) $person['user_id'] ?>" <?= (string) old('staff_id') === (string) $person['user_id'] ? 'selected' : '' ?>><?= esc(person_label((string) $person['name'], (int) $person['user_id'], $designationByUser ?? [])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>When <input type="datetime-local" name="scheduled_at" data-field="scheduled_at" value="<?= esc((string) old('scheduled_at')) ?>" required></label>
            </div>
            <div class="split">
                <label>Project
                    <select name="project_id" data-field="project_id" data-project-filter>
                        <option value="">None</option>
                        <?php foreach ($projects as $project) : ?>
                            <option value="<?= (int) $project['id'] ?>" <?= (string) old('project_id') === (string) $project['id'] ? 'selected' : '' ?>><?= esc($project['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Plot
                    <select name="plot_id" data-field="plot_id" data-plot-options>
                        <option value="">None</option>
                        <?php foreach ($plots as $plot) : ?>
                            <option value="<?= (int) $plot['id'] ?>" data-project="<?= (int) $plot['project_id'] ?>" <?= (string) old('plot_id') === (string) $plot['id'] ? 'selected' : '' ?>><?= esc($plot['project_name'] . ' · ' . $plot['plot_no']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>Note <input name="note" data-field="note" value="<?= esc((string) old('note')) ?>"></label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('map-pin') ?> Schedule</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
<?php if (in_array('visits.attend', $can, true)) : ?>
<dialog id="attendance-modal" class="modal" <?= ! empty($markOpen) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= $markId > 0 ? site_url('app/visits/' . $markId . '/attendance') : site_url('app/visits') ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="attendance">
        <input type="hidden" name="visit_id" data-field="visit_id" value="<?= $markId ?: '' ?>">
        <header class="modal-head">
            <h2 data-modal-title>Attendance</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>Result
                <select name="status" data-field="status">
                    <option value="attended" <?= old('status') === 'missed' ? '' : 'selected' ?>>Attended</option>
                    <option value="missed" <?= old('status') === 'missed' ? 'selected' : '' ?>>Missed</option>
                </select>
            </label>
            <label class="check"><input type="checkbox" name="lead_attended" value="1" <?= old('lead_attended') ? 'checked' : '' ?>> <span>Lead showed up</span></label>
            <label class="check"><input type="checkbox" name="staff_present" value="1" <?= old('staff_present') ? 'checked' : '' ?>> <span>Staff was present</span></label>
            <label>Note <textarea name="note" data-field="note"><?= esc((string) old('note')) ?></textarea></label>
            <p class="fine">These boxes apply when the visit was attended. A missed visit records neither.</p>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('user-check') ?> Save attendance</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
<?= $this->endSection() ?>
