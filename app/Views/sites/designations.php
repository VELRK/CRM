<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow">People</p>
        <h1>Designations</h1>
        <p class="lede">These titles appear on staff, teams, leads, visits, attendance and incentives.</p>
    </div>
    <?php if (in_array('workspace.settings', $can, true)) : ?>
        <button class="primary" type="button" data-modal="designation-modal" data-action="<?= site_url('app/designations') ?>" data-title="New designation" data-code="" data-label="" data-sort_order="10" data-active="1"><?= icon('plus') ?> New designation</button>
    <?php endif; ?>
</header>
<?php if ($items === []) : ?>
    <section class="panel"><div class="empty"><?= icon('inbox') ?><p>No designations yet.</p></div></section>
<?php else : ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Title</th><th>Code</th><th>Order</th><th>Active</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $item) : ?>
                <tr>
                    <td><strong><?= esc($item['label']) ?></strong></td>
                    <td><?= esc($item['code']) ?></td>
                    <td><?= (int) $item['sort_order'] ?></td>
                    <td><?= (int) $item['active'] === 1 ? 'Yes' : 'No' ?></td>
                    <td class="actions-cell">
                        <?php if (in_array('workspace.settings', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="designation-modal" data-action="<?= site_url('app/designations/' . $item['id']) ?>" data-title="Edit designation" data-code="<?= esc($item['code'], 'attr') ?>" data-label="<?= esc($item['label'], 'attr') ?>" data-sort_order="<?= (int) $item['sort_order'] ?>" data-active="<?= (int) $item['active'] ?>"><?= icon('square-pen') ?> Edit</button>
                            <button class="ghost" type="button" data-modal="confirm-delete" data-action="<?= site_url('app/designations/' . $item['id'] . '/delete') ?>" data-title="Remove <?= esc($item['label'], 'attr') ?>?"><?= icon('trash-2') ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<dialog id="designation-modal" class="modal" <?= ! empty($open) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= site_url('app/designations') ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="designation">
        <header class="modal-head">
            <h2 data-modal-title>Designation</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>Title <input name="label" data-field="label" required placeholder="Property advisor"></label>
            <label>Code <input name="code" data-field="code" required placeholder="property_advisor"></label>
            <label>Order <input name="sort_order" data-field="sort_order" value="10"></label>
            <label class="check"><input type="checkbox" name="active" value="1" data-field="active" checked> <span>Active</span></label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('briefcase') ?> Save</button>
        </footer>
    </form>
</dialog>
<?= $this->endSection() ?>
