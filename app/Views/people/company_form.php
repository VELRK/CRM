<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $editing = $company !== null;
    $value = static function (string $field, $fallback = '') use ($company) {
        $old = old($field);
        if ($old !== null && $old !== '') {
            return $old;
        }
        return is_array($company) ? ($company[$field] ?? $fallback) : $fallback;
    };
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Company</p>
        <h1><?= $editing ? esc($company['name']) : 'New company' ?></h1>
    </div>
    <?php if ($editing && in_array('deals.create', $can, true)) : ?>
        <a class="primary" href="<?= site_url('app/pipeline/deals/new') ?>?company=<?= (int) $company['id'] ?>"><?= icon('handshake') ?> New deal</a>
    <?php endif; ?>
</header>
<?= $this->include('people/tabs') ?>
<form method="post" action="<?= site_url($editing ? 'app/companies/' . $company['id'] : 'app/companies') ?>" class="panel stack narrow">
    <?= csrf_field() ?>
    <label>Name <input name="name" value="<?= esc($value('name')) ?>" required></label>
    <label>Phone <input name="phone" value="<?= esc($value('phone')) ?>"></label>
    <label>Website <input name="website" value="<?= esc($value('website')) ?>" placeholder="https://"></label>
    <label>Owner
        <select name="owner_id">
            <?php foreach ($members as $member) : ?>
                <option value="<?= (int) $member['user_id'] ?>" <?= (int) ($value('owner_id') ?: session()->get('user_id')) === (int) $member['user_id'] ? 'selected' : '' ?>><?= esc($member['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="actions">
        <button class="primary" type="submit"><?= icon('landmark') ?> <?= $editing ? 'Save company' : 'Add company' ?></button>
        <a class="quiet" href="<?= site_url('app/companies') ?>">Back</a>
    </div>
</form>
<?php if ($editing && $contacts !== []) : ?>
    <section class="list" style="margin-top: 16px;">
        <?php foreach ($contacts as $contact) : ?>
            <a class="row" href="<?= site_url('app/people/' . $contact['id']) ?>">
                <span class="well"><?= icon('user') ?></span>
                <span class="row-copy">
                    <strong><?= esc($contact['name']) ?></strong>
                    <em><?= esc($contact['phone'] ?? $contact['email'] ?? '') ?></em>
                </span>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php if ($editing && $deals !== []) : ?>
    <section class="list" style="margin-top: 16px;">
        <?php foreach ($deals as $deal) : ?>
            <a class="row" href="<?= site_url('app/pipeline/deals/' . $deal['id']) ?>">
                <span class="well"><?= icon('handshake') ?></span>
                <span class="row-copy"><strong><?= esc($deal['title']) ?></strong><em><?= esc($deal['stage_name']) ?></em></span>
                <strong><?= esc(\App\Libraries\BookService::minorToRupees((int) $deal['amount_minor'])) ?></strong>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php if ($editing && in_array('companies.delete', $can, true)) : ?>
    <form method="post" action="<?= site_url('app/companies/' . $company['id'] . '/delete') ?>" class="danger-row" onsubmit="return confirm('Remove this company?');">
        <?= csrf_field() ?>
        <button class="danger" type="submit"><?= icon('trash-2') ?> Remove company</button>
    </form>
<?php endif; ?>
<?= $this->include('partials/record_desk') ?>
<?= $this->endSection() ?>
