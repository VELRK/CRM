<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $locked  = (int) $role['is_owner'] === 1;
    $system  = (int) $role['is_system'] === 1;
    $manage  = in_array('roles.manage', $can, true);
    $picked  = array_flip($selected);
?>
<header class="page-head">
    <div>
        <p class="eyebrow"><?= $locked ? 'Locked' : ($system ? 'Starter role' : 'Custom role') ?></p>
        <h1><?= esc($role['name']) ?></h1>
        <p class="lede">
            <?= $locked
                ? 'Owner keeps every permission, including billing.'
                : 'Turn on the access this role should have. Saving applies it to everyone on the role.' ?>
        </p>
    </div>
</header>
<form method="post" action="<?= site_url('app/roles/' . $role['id'] . '/access') ?>" class="stack">
    <?= csrf_field() ?>
    <?php foreach ($groups as $group => $items) : ?>
        <section class="panel">
            <h2><?= esc($group) ?></h2>
            <div class="checks">
                <?php foreach ($items as $item) : ?>
                    <label class="check">
                        <input
                            type="checkbox"
                            name="permissions[]"
                            value="<?= esc($item['key']) ?>"
                            <?= isset($picked[$item['key']]) ? 'checked' : '' ?>
                            <?= ($locked || ! $manage) ? 'disabled' : '' ?>
                        >
                        <span><?= esc($item['label']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
    <?php if ($manage && ! $locked) : ?>
        <div class="actions">
            <button class="primary" type="submit"><?= icon('key-round') ?> Save access</button>
            <a class="quiet" href="<?= site_url('app/roles') ?>">Back to roles</a>
        </div>
    <?php else : ?>
        <p><a class="quiet" href="<?= site_url('app/roles') ?>">Back to roles</a></p>
    <?php endif; ?>
</form>
<?php if ($manage && ! $system && ! $locked) : ?>
    <form method="post" action="<?= site_url('app/roles/' . $role['id'] . '/delete') ?>" class="danger-row" onsubmit="return confirm('Remove this role?');">
        <?= csrf_field() ?>
        <button class="danger" type="submit"><?= icon('trash-2') ?> Remove role</button>
    </form>
<?php endif; ?>
<?= $this->endSection() ?>
