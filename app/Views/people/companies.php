<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $failed   = old('form') === 'company';
    $recordId = (int) (old('record_id') ?: ($editing['id'] ?? 0));
    $action   = $recordId > 0 ? site_url('app/companies/' . $recordId) : site_url('app/companies');
    $pick = static function (string $field, string $fallback = '') use ($editing, $failed): string {
        if ($failed) {
            return (string) old($field);
        }
        if (is_array($editing) && array_key_exists($field, $editing) && $editing[$field] !== null) {
            return (string) $editing[$field];
        }
        return $fallback;
    };
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Book</p>
        <h1>Companies</h1>
    </div>
    <?php if (in_array('companies.create', $can, true)) : ?>
        <button class="primary" type="button" data-modal="company-modal" data-action="<?= site_url('app/companies') ?>" data-title="New company" data-record_id="" data-name="" data-phone="" data-website="" data-owner_id="<?= (int) session()->get('user_id') ?>"><?= icon('plus') ?> New company</button>
    <?php endif; ?>
</header>
<?= $this->include('people/tabs') ?>
<form class="search" method="get" action="<?= site_url('app/companies') ?>">
    <?= icon('search') ?>
    <input name="q" value="<?= esc($query) ?>" placeholder="Search company or phone">
</form>
<?php if ($companies === []) : ?>
    <section class="panel"><div class="empty"><?= icon('inbox') ?><p><?= $query === '' ? 'No companies yet.' : 'No companies match that search.' ?></p></div></section>
<?php else : ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Company</th><th>Phone</th><th>Owner</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($companies as $company) : ?>
                <tr>
                    <td><strong><?= esc($company['name']) ?></strong><?php if ($company['website']) : ?><div class="fine"><?= esc($company['website']) ?></div><?php endif; ?></td>
                    <td><?= esc($company['phone'] ?: '—') ?></td>
                    <td><?= esc($company['owner_name'] ?? '—') ?></td>
                    <td class="actions-cell wrap">
                        <?php if (in_array('companies.update', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="company-modal" data-action="<?= site_url('app/companies/' . $company['id']) ?>" data-title="Edit company" data-record_id="<?= (int) $company['id'] ?>" data-name="<?= esc($company['name'], 'attr') ?>" data-phone="<?= esc((string) $company['phone'], 'attr') ?>" data-website="<?= esc((string) $company['website'], 'attr') ?>" data-owner_id="<?= (int) ($company['owner_id'] ?? 0) ?>"><?= icon('square-pen') ?> Edit</button>
                        <?php endif; ?>
                        <?php if (in_array('activities.create', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="log-modal" data-title="Log · <?= esc($company['name'], 'attr') ?>" data-subject_type="company" data-subject_id="<?= (int) $company['id'] ?>"><?= icon('messages-square') ?></button>
                        <?php endif; ?>
                        <?php if (in_array('files.attach', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="file-modal" data-title="Attach · <?= esc($company['name'], 'attr') ?>" data-subject_type="company" data-subject_id="<?= (int) $company['id'] ?>"><?= icon('paperclip') ?></button>
                        <?php endif; ?>
                        <?php if (in_array('companies.delete', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="confirm-delete" data-action="<?= site_url('app/companies/' . $company['id'] . '/delete') ?>" data-title="Remove <?= esc($company['name'], 'attr') ?>?"><?= icon('trash-2') ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php if (in_array('companies.create', $can, true) || in_array('companies.update', $can, true)) : ?>
<dialog id="company-modal" class="modal" <?= ! empty($open) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= $action ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="company">
        <input type="hidden" name="record_id" data-field="record_id" value="<?= $recordId ?: '' ?>">
        <header class="modal-head">
            <h2 data-modal-title><?= $recordId ? 'Edit company' : 'New company' ?></h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>Name <input name="name" data-field="name" value="<?= esc($pick('name')) ?>" required></label>
            <label>Phone <input name="phone" data-field="phone" value="<?= esc($pick('phone')) ?>"></label>
            <label>Website <input name="website" data-field="website" value="<?= esc($pick('website')) ?>" placeholder="https://"></label>
            <label>Owner
                <select name="owner_id" data-field="owner_id">
                    <?php foreach ($members as $member) : ?>
                        <option value="<?= (int) $member['user_id'] ?>" <?= (string) $pick('owner_id', (string) session()->get('user_id')) === (string) $member['user_id'] ? 'selected' : '' ?>><?= esc($member['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('landmark') ?> Save company</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
<?= $this->include('partials/quick_actions') ?>
<?= $this->endSection() ?>
