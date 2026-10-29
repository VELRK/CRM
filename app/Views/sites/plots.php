<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $failed   = old('form') === 'plot';
    $recordId = (int) (old('record_id') ?: ($editing['id'] ?? 0));
    $action   = $recordId > 0 ? site_url('app/plots/' . $recordId) : site_url('app/plots');
    $plain = static function (int $minor): string {
        return $minor % 100 === 0 ? (string) ($minor / 100) : number_format($minor / 100, 2, '.', '');
    };
    $pick = static function (string $field, string $fallback = '') use ($editing, $failed, $plain): string {
        if ($failed) {
            return (string) old($field);
        }
        if (! is_array($editing)) {
            return $fallback;
        }
        if ($field === 'price') {
            return $plain((int) ($editing['price_minor'] ?? 0));
        }
        if (array_key_exists($field, $editing) && $editing[$field] !== null) {
            return (string) $editing[$field];
        }
        return $fallback;
    };
    $labels = ['available' => 'Available', 'advance' => 'Advance / Token', 'hold' => 'Hold', 'booked' => 'Booked', 'sold' => 'Sold', 'owner_use' => 'Owner use', 'future' => 'Future', 'registered' => 'Registered', 'blocked' => 'Blocked'];
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Sites</p>
        <h1>Plots</h1>
    </div>
    <?php if (in_array('plots.create', $can, true)) : ?>
        <button class="primary" type="button" data-modal="plot-modal" data-action="<?= site_url('app/plots') ?>" data-title="New plot" data-record_id="" data-project_id="<?= (int) $projectId ?>" data-plot_no="" data-block="" data-area_sqft="" data-facing="" data-unit="sqft" data-price="" data-status="available"><?= icon('plus') ?> New plot</button>
    <?php endif; ?>
    <?php if (in_array('exports.download', $can, true)) : ?>
        <a class="ghost" href="<?= site_url('app/plots/export') . ($projectId ? '?project=' . (int) $projectId : '') ?>"><?= icon('file-text') ?> Download</a>
    <?php endif; ?>
</header>
<form class="search" method="get" action="<?= site_url('app/plots') ?>">
    <label>Project
        <select name="project" onchange="this.form.submit()">
            <option value="">All projects</option>
            <?php foreach ($projects as $project) : ?>
                <option value="<?= (int) $project['id'] ?>" <?= (int) $projectId === (int) $project['id'] ? 'selected' : '' ?>><?= esc($project['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
</form>
<?php if ($plots === []) : ?>
    <section class="panel"><div class="empty"><?= icon('inbox') ?><p>No plots in this list.</p></div></section>
<?php else : ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Project</th><th>Plot</th><th>Block</th><th>Area</th><th>Facing</th><th class="num">Price</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($plots as $plot) : ?>
                <tr>
                    <td><?= esc($plot['project_name']) ?></td>
                    <td><strong><?= esc($plot['plot_no']) ?></strong></td>
                    <td><?= esc($plot['block'] !== '' ? $plot['block'] : '—') ?></td>
                    <td><?= $plot['area_sqft'] !== null ? esc(rtrim(rtrim((string) $plot['area_sqft'], '0'), '.')) . ' sqft' : '—' ?></td>
                    <td><?= esc($plot['facing'] ?: '—') ?></td>
                    <td class="num"><?= esc(\App\Libraries\DeskService::money((int) $plot['price_minor'], 'INR')) ?></td>
                    <td><span class="badge <?= $plot['status'] === 'available' ? '' : 'mute' ?>"><?= esc($labels[$plot['status']] ?? $plot['status']) ?></span></td>
                    <td class="actions-cell">
                        <?php if (in_array('plots.update', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="plot-modal" data-action="<?= site_url('app/plots/' . $plot['id']) ?>" data-title="Edit plot" data-record_id="<?= (int) $plot['id'] ?>" data-project_id="<?= (int) $plot['project_id'] ?>" data-plot_no="<?= esc($plot['plot_no'], 'attr') ?>" data-block="<?= esc((string) $plot['block'], 'attr') ?>" data-area_sqft="<?= esc($plot['area_sqft'] !== null ? rtrim(rtrim((string) $plot['area_sqft'], '0'), '.') : '', 'attr') ?>" data-facing="<?= esc((string) $plot['facing'], 'attr') ?>" data-price="<?= esc($plain((int) $plot['price_minor']), 'attr') ?>" data-status="<?= esc($plot['status'], 'attr') ?>"><?= icon('square-pen') ?> Edit</button>
                        <?php endif; ?>
                        <?php if (in_array('plots.delete', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="confirm-delete" data-action="<?= site_url('app/plots/' . $plot['id'] . '/delete') ?>" data-title="Remove plot <?= esc($plot['plot_no'], 'attr') ?>?"><?= icon('trash-2') ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php if (in_array('plots.create', $can, true) || in_array('plots.update', $can, true)) : ?>
<dialog id="plot-modal" class="modal" <?= ! empty($open) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= $action ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="plot">
        <input type="hidden" name="record_id" data-field="record_id" value="<?= $recordId ?: '' ?>">
        <header class="modal-head">
            <h2 data-modal-title><?= $recordId ? 'Edit plot' : 'New plot' ?></h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>Project
                <select name="project_id" data-field="project_id" required>
                    <option value="">Choose</option>
                    <?php foreach ($projects as $project) : ?>
                        <option value="<?= (int) $project['id'] ?>" <?= (string) $pick('project_id') === (string) $project['id'] ? 'selected' : '' ?>><?= esc($project['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="split">
                <label>Plot no. <input name="plot_no" data-field="plot_no" value="<?= esc($pick('plot_no')) ?>" required></label>
                <label>Block <input name="block" data-field="block" value="<?= esc($pick('block')) ?>"></label>
            </div>
            <div class="split">
                <label>Area (sqft) <input name="area_sqft" data-field="area_sqft" inputmode="decimal" value="<?= esc($pick('area_sqft')) ?>"></label>
                <label>Facing <input name="facing" data-field="facing" value="<?= esc($pick('facing')) ?>" placeholder="East"></label>
            </div>
            <div class="split">
                <label>Unit <input name="unit" data-field="unit" value="<?= esc($pick('unit', 'sqft')) ?>"></label>
                <label>Price per unit (₹) <input name="price_per_unit" data-field="price_per_unit" inputmode="decimal" value="<?= esc($pick('price_per_unit')) ?>"></label>
            </div>
            <div class="split">
                <label>Total price (₹) <input name="price" data-field="price" inputmode="decimal" value="<?= esc($pick('price')) ?>"></label>
                <label>Status
                    <select name="status" data-field="status">
                        <?php foreach ($labels as $value => $label) : ?>
                            <option value="<?= $value ?>" <?= $pick('status', 'available') === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>Status remark <input name="status_remark" data-field="status_remark" value="<?= esc($pick('status_remark')) ?>"></label>
            <fieldset>
                <legend>Sale credit</legend>
                <p class="fine">Required when the status becomes booked, sold or registered.</p>
                <label>Lead
                    <select name="lead_id" data-field="lead_id">
                        <option value="">None</option>
                        <?php foreach ($leads as $lead) : ?>
                            <option value="<?= (int) $lead['id'] ?>"><?= esc($lead['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Credited staff
                    <select name="credited_user_id" data-field="credited_user_id">
                        <option value="">None</option>
                        <?php foreach ($staff as $person) : ?>
                            <option value="<?= (int) $person['user_id'] ?>"><?= esc(person_label((string) $person['name'], (int) $person['user_id'], $designationByUser ?? [])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Sale value (₹) <input name="sale_value" data-field="sale_value" inputmode="decimal" placeholder="Uses the plot price when blank"></label>
            </fieldset>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('map') ?> Save plot</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
<?= $this->endSection() ?>
