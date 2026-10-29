<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $editing = $contact !== null;
    $value = static function (string $field, $fallback = '') use ($contact) {
        $old = old($field);
        if ($old !== null && $old !== '') {
            return $old;
        }
        return is_array($contact) ? ($contact[$field] ?? $fallback) : $fallback;
    };
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Contact</p>
        <h1><?= $editing ? esc($contact['name']) : 'New contact' ?></h1>
    </div>
    <?php if ($editing && in_array('deals.create', $can, true)) : ?>
        <a class="primary" href="<?= site_url('app/pipeline/deals/new') ?>?contact=<?= (int) $contact['id'] ?>"><?= icon('handshake') ?> New deal</a>
    <?php endif; ?>
</header>
<?= $this->include('people/tabs') ?>
<form method="post" action="<?= site_url($editing ? 'app/people/' . $contact['id'] : 'app/people') ?>" class="panel stack">
    <?= csrf_field() ?>
    <label>Name <input name="name" value="<?= esc($value('name')) ?>" required></label>
    <div class="split">
        <label>Phone <input name="phone" value="<?= esc($value('phone')) ?>"></label>
        <label>Email <input type="email" name="email" value="<?= esc($value('email')) ?>"></label>
    </div>
    <label>Tags <input name="tags" value="<?= esc($value('tags')) ?>" placeholder="buyer, renewal"></label>
    <?php if (in_array('contacts.assign', $can, true)) : ?>
        <label>Owner
            <select name="owner_id">
                <?php foreach ($members as $member) : ?>
                    <option value="<?= (int) $member['user_id'] ?>" <?= (int) ($value('owner_id') ?: session()->get('user_id')) === (int) $member['user_id'] ? 'selected' : '' ?>><?= esc($member['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php endif; ?>
    <?php if ($companies !== []) : ?>
        <fieldset class="checks">
            <legend>Companies</legend>
            <?php foreach ($companies as $company) : ?>
                <label class="check">
                    <input type="checkbox" name="company_ids[]" value="<?= (int) $company['id'] ?>" <?= in_array((int) $company['id'], $linked, true) ? 'checked' : '' ?>>
                    <span><?= esc($company['name']) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
    <?php endif; ?>
    <div class="actions">
        <button class="primary" type="submit"><?= icon('user') ?> <?= $editing ? 'Save contact' : 'Add contact' ?></button>
        <a class="quiet" href="<?= site_url('app/people') ?>">Back</a>
    </div>
</form>
<?php if ($editing && $deals !== []) : ?>
    <section class="list" style="margin-top: 16px;">
        <?php foreach ($deals as $deal) : ?>
            <a class="row" href="<?= site_url('app/pipeline/deals/' . $deal['id']) ?>">
                <span class="well"><?= icon('handshake') ?></span>
                <span class="row-copy">
                    <strong><?= esc($deal['title']) ?></strong>
                    <em><?= esc($deal['stage_name']) ?></em>
                </span>
                <strong><?= esc(\App\Libraries\BookService::minorToRupees((int) $deal['amount_minor'])) ?></strong>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php if ($editing && in_array('contacts.delete', $can, true)) : ?>
    <form method="post" action="<?= site_url('app/people/' . $contact['id'] . '/delete') ?>" class="danger-row" onsubmit="return confirm('Remove this contact?');">
        <?= csrf_field() ?>
        <button class="danger" type="submit"><?= icon('trash-2') ?> Remove contact</button>
    </form>
<?php endif; ?>
<?= $this->include('partials/record_desk') ?>
<?= $this->endSection() ?>
