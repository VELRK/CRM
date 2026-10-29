<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $manage = in_array('roles.manage', $can, true);
    $inviteOk = in_array('team.invite', $can, true);
?>
<header class="page-head">
    <div>
        <p class="eyebrow">People</p>
        <h1>Team</h1>
        <p class="lede">Each person has one role. Changing it replaces their access.</p>
    </div>
    <?php if ($inviteOk) : ?>
        <button class="primary" type="button" data-modal="invite-modal" data-title="Invite someone"><?= icon('plus') ?> Invite</button>
    <?php endif; ?>
</header>
<?php if (! empty($invite)) : ?>
    <p class="banner">Invite link, valid for 7 days: <a href="<?= esc($invite) ?>"><?= esc($invite) ?></a></p>
<?php endif; ?>
<section class="list">
    <?php foreach ($members as $member) : ?>
        <div class="row static">
            <span class="well"><?= icon('user') ?></span>
            <span class="row-copy">
                <strong><?= esc(person_label((string) $member['name'], (int) $member['user_id'], $designationByUser ?? [])) ?></strong>
                <em><?= esc($member['email']) ?></em>
            </span>
            <?php if ($manage) : ?>
                <form method="post" action="<?= site_url('app/team/' . $member['id'] . '/role') ?>" class="inline">
                    <?= csrf_field() ?>
                    <select name="role_id" onchange="this.form.submit()">
                        <?php foreach ($roles as $role) : ?>
                            <option value="<?= (int) $role['id'] ?>" <?= (int) $role['id'] === (int) $member['role_id'] ? 'selected' : '' ?>>
                                <?= esc($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php else : ?>
                <em><?= esc($member['role_name']) ?></em>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>
<?php if ($inviteOk) : ?>
<dialog id="invite-modal" class="modal" <?= old('email') ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= site_url('app/team/invites') ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <header class="modal-head">
            <h2 data-modal-title>Invite someone</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>Email <input type="email" name="email" value="<?= esc((string) old('email')) ?>" required></label>
            <label>Role
                <select name="role_id" required>
                    <?php foreach ($roles as $role) : ?>
                        <option value="<?= (int) $role['id'] ?>" <?= (string) old('role_id') === (string) $role['id'] ? 'selected' : '' ?>><?= esc($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('plus') ?> Create invite</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
<?= $this->endSection() ?>
