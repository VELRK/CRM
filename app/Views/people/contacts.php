<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $failed   = old('form') === 'contact';
    $recordId = (int) (old('record_id') ?: ($editing['id'] ?? 0));
    $action   = $recordId > 0 ? site_url('app/people/' . $recordId) : site_url('app/people');
    $linkedNow = $failed ? array_map('intval', (array) old('company_ids')) : ($editing ? ($companyMap[(int) $editing['id']] ?? []) : []);
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
        <h1>People</h1>
    </div>
    <?php if (in_array('contacts.create', $can, true)) : ?>
        <button class="primary" type="button" data-modal="contact-modal" data-action="<?= site_url('app/people') ?>" data-title="New contact" data-record_id="" data-name="" data-phone="" data-email="" data-tags="" data-owner_id="<?= (int) session()->get('user_id') ?>" data-companies=""><?= icon('plus') ?> New contact</button>
    <?php endif; ?>
</header>
<?= $this->include('people/tabs') ?>
<form class="search" method="get" action="<?= site_url('app/people') ?>">
    <?= icon('search') ?>
    <input name="q" value="<?= esc($query) ?>" placeholder="Search name, phone, email, tags">
</form>
<?php if ($contacts === []) : ?>
    <section class="panel"><div class="empty"><?= icon('inbox') ?><p><?= $query === '' ? 'No contacts yet.' : 'No contacts match that search.' ?></p></div></section>
<?php else : ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Name</th><th>Phone</th><th>Owner</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($contacts as $contact) :
                $companiesFor = implode(',', $companyMap[(int) $contact['id']] ?? []);
            ?>
                <tr>
                    <td><strong><?= esc($contact['name']) ?></strong><?php if ($contact['email']) : ?><div class="fine"><?= esc($contact['email']) ?></div><?php endif; ?></td>
                    <td><?= esc($contact['phone'] ?: '—') ?></td>
                    <td><?= esc($contact['owner_name'] ?: '—') ?></td>
                    <td class="actions-cell wrap">
                        <?php if (in_array('contacts.update', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="contact-modal" data-action="<?= site_url('app/people/' . $contact['id']) ?>" data-title="Edit contact" data-record_id="<?= (int) $contact['id'] ?>" data-name="<?= esc($contact['name'], 'attr') ?>" data-phone="<?= esc((string) $contact['phone'], 'attr') ?>" data-email="<?= esc((string) $contact['email'], 'attr') ?>" data-tags="<?= esc((string) $contact['tags'], 'attr') ?>" data-owner_id="<?= (int) $contact['owner_id'] ?>" data-companies="<?= esc($companiesFor, 'attr') ?>"><?= icon('square-pen') ?> Edit</button>
                        <?php endif; ?>
                        <?php if (in_array('activities.create', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="log-modal" data-title="Log · <?= esc($contact['name'], 'attr') ?>" data-subject_type="contact" data-subject_id="<?= (int) $contact['id'] ?>"><?= icon('messages-square') ?></button>
                        <?php endif; ?>
                        <?php if (in_array('files.attach', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="file-modal" data-title="Attach · <?= esc($contact['name'], 'attr') ?>" data-subject_type="contact" data-subject_id="<?= (int) $contact['id'] ?>"><?= icon('paperclip') ?></button>
                        <?php endif; ?>
                        <?php if (in_array('contacts.delete', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="confirm-delete" data-action="<?= site_url('app/people/' . $contact['id'] . '/delete') ?>" data-title="Remove <?= esc($contact['name'], 'attr') ?>?"><?= icon('trash-2') ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php if (in_array('contacts.create', $can, true) || in_array('contacts.update', $can, true)) : ?>
<dialog id="contact-modal" class="modal" <?= ! empty($open) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= $action ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="contact">
        <input type="hidden" name="record_id" data-field="record_id" value="<?= $recordId ?: '' ?>">
        <header class="modal-head">
            <h2 data-modal-title><?= $recordId ? 'Edit contact' : 'New contact' ?></h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>Name <input name="name" data-field="name" value="<?= esc($pick('name')) ?>" required></label>
            <div class="split">
                <label>Phone <input name="phone" data-field="phone" value="<?= esc($pick('phone')) ?>"></label>
                <label>Email <input type="email" name="email" data-field="email" value="<?= esc($pick('email')) ?>"></label>
            </div>
            <label>Tags <input name="tags" data-field="tags" value="<?= esc($pick('tags')) ?>" placeholder="buyer, renewal"></label>
            <?php if (in_array('contacts.assign', $can, true)) : ?>
                <label>Owner
                    <select name="owner_id" data-field="owner_id">
                        <?php foreach ($members as $member) : ?>
                            <option value="<?= (int) $member['user_id'] ?>" <?= (string) $pick('owner_id', (string) session()->get('user_id')) === (string) $member['user_id'] ? 'selected' : '' ?>><?= esc($member['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <?php if ($companies !== []) : ?>
                <fieldset class="checks">
                    <legend>Companies</legend>
                    <?php foreach ($companies as $company) : ?>
                        <label class="check">
                            <input type="checkbox" name="company_ids[]" value="<?= (int) $company['id'] ?>" data-field="companies" <?= in_array((int) $company['id'], $linkedNow, true) ? 'checked' : '' ?>>
                            <span><?= esc($company['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endif; ?>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('user') ?> Save contact</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
<?= $this->include('partials/quick_actions') ?>
<?= $this->endSection() ?>
