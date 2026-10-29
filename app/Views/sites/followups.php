<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow">Sites</p>
        <h1>Follow-ups</h1>
        <p class="lede">Open items stay at the top. Marking one done is a person clicking Done.</p>
    </div>
    <?php if (in_array('followups.create', $can, true)) : ?>
        <button class="primary" type="button" data-modal="followup-modal" data-title="Log a follow-up" data-lead_id="" data-body="" data-next_at=""><?= icon('plus') ?> Log follow-up</button>
    <?php endif; ?>
</header>
<?php if ($followups === []) : ?>
    <section class="panel"><div class="empty"><?= icon('inbox') ?><p>No follow-ups yet.</p></div></section>
<?php else : ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Lead</th><th>Note</th><th>Next</th><th>State</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($followups as $row) : ?>
                <tr>
                    <td><strong><?= esc($row['lead_name']) ?></strong><div class="fine"><?= esc(person_label((string) ($row['user_name'] ?? ''), (int) ($row['assigned_user_id'] ?: $row['user_id']), $designationByUser ?? [])) ?></div></td>
                    <td><?= esc($row['body']) ?></td>
                    <td><?= $row['next_at'] ? esc(date('d M Y, H:i', strtotime((string) $row['next_at']))) : '—' ?></td>
                    <td><?= $row['done_at'] ? '<span class="badge">Done</span>' : '<span class="badge mute">Open</span>' ?></td>
                    <td class="actions-cell">
                        <?php if ($row['done_at'] === null && in_array('followups.create', $can, true)) : ?>
                            <form method="post" action="<?= site_url('app/followups/' . $row['id'] . '/done') ?>">
                                <?= csrf_field() ?>
                                <button class="ghost" type="submit"><?= icon('square-check') ?> Done</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php if (in_array('followups.create', $can, true)) : ?>
<dialog id="followup-modal" class="modal" <?= ! empty($open) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= site_url('app/followups') ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="followup">
        <header class="modal-head">
            <h2 data-modal-title>Log a follow-up</h2>
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
            <label>What happened <textarea name="body" data-field="body" required><?= esc((string) old('body')) ?></textarea></label>
            <label>Next follow-up <input type="datetime-local" name="next_at" data-field="next_at" value="<?= esc((string) old('next_at')) ?>"></label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('clipboard-list') ?> Log follow-up</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
<?= $this->endSection() ?>
