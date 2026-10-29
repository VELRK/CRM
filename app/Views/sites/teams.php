<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow">People</p>
        <h1>Teams</h1>
        <p class="lede">Each team has one head. Membership is kept with a from-date and a to-date.</p>
    </div>
    <?php if (in_array('teams.manage', $can, true)) : ?>
        <button class="primary" type="button" data-modal="team-modal" data-action="<?= site_url('app/teams') ?>" data-title="New team" data-name="" data-head_user_id="" data-members=""><?= icon('plus') ?> New team</button>
    <?php endif; ?>
</header>
<?php if ($teams === []) : ?>
    <section class="panel"><div class="empty"><?= icon('inbox') ?><p>No teams yet.</p></div></section>
<?php else : ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Team</th><th>Head</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($teams as $team) : ?>
                <tr>
                    <td><strong><?= esc($team['name']) ?></strong></td>
                    <td><?= esc($team['head_name'] ? person_label((string) $team['head_name'], (int) $team['head_user_id'], $designationByUser ?? []) : '—') ?></td>
                    <td class="actions-cell">
                        <?php if (in_array('teams.manage', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="team-modal" data-action="<?= site_url('app/teams/' . $team['id']) ?>" data-title="Edit team" data-name="<?= esc($team['name'], 'attr') ?>" data-head_user_id="<?= (int) $team['head_user_id'] ?>" data-members="<?= esc($team['member_ids'], 'attr') ?>"><?= icon('square-pen') ?> Edit</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php if (in_array('teams.manage', $can, true)) : ?>
<dialog id="team-modal" class="modal" <?= ! empty($open) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= site_url('app/teams') ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="team">
        <header class="modal-head">
            <h2 data-modal-title>Team</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>Name <input name="name" data-field="name" required></label>
            <label>Team head
                <select name="head_user_id" data-field="head_user_id">
                    <option value="">Choose</option>
                    <?php foreach ($staff as $person) : ?>
                        <option value="<?= (int) $person['user_id'] ?>"><?= esc(person_label((string) $person['name'], (int) $person['user_id'], $designationByUser ?? [])) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <fieldset class="checks">
                <legend>Members</legend>
                <?php foreach ($staff as $person) : ?>
                    <label class="check"><input type="checkbox" name="member_ids[]" value="<?= (int) $person['user_id'] ?>" data-field="members"> <span><?= esc(person_label((string) $person['name'], (int) $person['user_id'], $designationByUser ?? [])) ?></span></label>
                <?php endforeach; ?>
            </fieldset>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('users') ?> Save team</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
<?= $this->endSection() ?>
