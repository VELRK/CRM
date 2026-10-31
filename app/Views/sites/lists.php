<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow">Settings</p>
        <h1>Lists</h1>
        <p class="lede">Lead statuses, plot statuses and sources are edited here. They are not hard-coded in the screens.</p>
    </div>
    <?php if (in_array('workspace.settings', $can, true)) : ?>
        <button class="primary" type="button" data-modal="lookup-modal" data-action="<?= site_url('app/lists') ?>" data-title="Add list item" data-kind="lead_source" data-code="" data-label="" data-sort_order="10"><?= icon('plus') ?> Add</button>
    <?php endif; ?>
</header>
<div class="table-wrap">
    <table class="data">
        <thead><tr><th>Type</th><th>Code</th><th>Label</th><th>Order</th><th>Active</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items as $item) : ?>
            <tr>
                <td><?= esc(str_replace('_', ' ', (string) $item['kind'])) ?></td>
                <td><?= esc($item['code']) ?></td>
                <td><strong><?= esc($item['label']) ?></strong></td>
                <td><?= (int) $item['sort_order'] ?></td>
                <td><?= (int) $item['active'] === 1 ? 'Yes' : 'No' ?></td>
                <td class="actions-cell">
                    <?php if (in_array('workspace.settings', $can, true)) : ?>
                        <button class="ghost" type="button" data-modal="lookup-modal" data-action="<?= site_url('app/lists/' . $item['id']) ?>" data-title="Edit list item" data-kind="<?= esc($item['kind'], 'attr') ?>" data-code="<?= esc($item['code'], 'attr') ?>" data-label="<?= esc($item['label'], 'attr') ?>" data-sort_order="<?= (int) $item['sort_order'] ?>"><?= icon('square-pen') ?></button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<dialog id="lookup-modal" class="modal" <?= ! empty($open) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= site_url('app/lists') ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="lookup">
        <input type="hidden" name="active" value="1">
        <header class="modal-head">
            <h2 data-modal-title>List item</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>Type
                <select name="kind" data-field="kind">
                    <option value="lead_status">Lead status</option>
                    <option value="plot_status">Plot status</option>
                    <option value="lead_source">Lead source</option>
                </select>
            </label>
            <label>Code <input name="code" data-field="code" required></label>
            <label>Label <input name="label" data-field="label" required></label>
            <label>Order <input name="sort_order" data-field="sort_order" value="10"></label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit">Save</button>
        </footer>
    </form>
</dialog>
<?= $this->endSection() ?>
