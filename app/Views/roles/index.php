<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $manage = in_array('roles.manage', $can, true); ?>
<header class="page-head">
    <div>
        <p class="eyebrow">Access</p>
        <h1>Roles</h1>
        <p class="lede"><?= count($roles) ?> of <?= (int) $cap ?> roles. Access is allocated to the role, then people are placed on it.</p>
    </div>
    <?php if ($manage) : ?>
        <button class="primary" type="button" data-modal="role-modal" data-title="Add role" data-name=""><?= icon('plus') ?> Add role</button>
    <?php endif; ?>
</header>
<div class="table-wrap">
    <table class="data">
        <thead><tr><th>Role</th><th>People</th><th>Access</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($roles as $role) : ?>
            <tr>
                <td>
                    <strong><?= esc($role['name']) ?></strong>
                    <div class="fine"><?= (int) $role['is_owner'] === 1 ? 'Locked' : ((int) $role['is_system'] === 1 ? 'Starter' : 'Custom') ?></div>
                </td>
                <td><?= (int) $role['member_count'] ?></td>
                <td><?= (int) $role['is_owner'] === 1 ? 'Every permission' : (int) $role['access_count'] ?></td>
                <td class="actions-cell">
                    <?php if ($manage && (int) $role['is_owner'] !== 1) : ?>
                        <button class="ghost" type="button" data-modal="access-modal" data-action="<?= site_url('app/roles/' . $role['id'] . '/access') ?>" data-title="Access · <?= esc($role['name'], 'attr') ?>" data-permissions="<?= esc($role['keys'], 'attr') ?>"><?= icon('key-round') ?> Access</button>
                    <?php endif; ?>
                    <?php if ($manage && (int) $role['is_system'] !== 1 && (int) $role['is_owner'] !== 1) : ?>
                        <button class="ghost" type="button" data-modal="confirm-delete" data-action="<?= site_url('app/roles/' . $role['id'] . '/delete') ?>" data-title="Remove <?= esc($role['name'], 'attr') ?>?"><?= icon('trash-2') ?></button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if ($manage) : ?>
<dialog id="role-modal" class="modal" <?= ! empty($openNew) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= site_url('app/roles') ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <header class="modal-head">
            <h2 data-modal-title>Add role</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body">
            <label>Name <input name="name" data-field="name" value="<?= esc((string) old('name')) ?>" required></label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('shield') ?> Add role</button>
        </footer>
    </form>
</dialog>
<dialog id="access-modal" class="modal wide" <?= (int) $accessId > 0 ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= (int) $accessId > 0 ? site_url('app/roles/' . $accessId . '/access') : site_url('app/roles') ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <header class="modal-head">
            <h2 data-modal-title>Access</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <?php $picked = array_flip($selected); ?>
            <?php foreach ($groups as $group => $items) : ?>
                <section>
                    <h2><?= esc($group) ?></h2>
                    <div class="checks">
                        <?php foreach ($items as $item) : ?>
                            <label class="check">
                                <input type="checkbox" name="permissions[]" value="<?= esc($item['key']) ?>" data-field="permissions" <?= isset($picked[$item['key']]) ? 'checked' : '' ?>>
                                <span><?= esc($item['label']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('key-round') ?> Save access</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
<?= $this->endSection() ?>
