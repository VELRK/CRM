<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $editing = $deal !== null;
    $picked = static function (string $field, int $fallback = 0) use ($deal) {
        $old = old($field);
        if ($old !== null && $old !== '') {
            return (int) $old;
        }
        if ($deal !== null && isset($deal[$field])) {
            return (int) $deal[$field];
        }
        return $fallback;
    };
    $amount = old('amount');
    if ($amount === null && $editing) {
        $minor = (int) $deal['amount_minor'];
        $amount = $minor % 100 === 0 ? (string) ($minor / 100) : number_format($minor / 100, 2, '.', '');
    }
    $companyId = $picked('company_id', $prefillCompany);
    $contactId = $picked('contact_id', $prefillContact);
    $stageId = $picked('stage_id', (int) ($stages[0]['id'] ?? 0));
    $ownerId = $picked('owner_id', (int) session()->get('user_id'));
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Deal</p>
        <h1><?= $editing ? esc($deal['title']) : 'New deal' ?></h1>
    </div>
</header>
<form method="post" action="<?= site_url($editing ? 'app/pipeline/deals/' . $deal['id'] : 'app/pipeline/deals') ?>" class="panel stack">
    <?= csrf_field() ?>
    <label>Title <input name="title" value="<?= esc(old('title') ?: ($deal['title'] ?? '')) ?>" required></label>
    <div class="split">
        <label>Amount (₹) <input name="amount" inputmode="decimal" value="<?= esc((string) $amount) ?>"></label>
        <label>Stage
            <select name="stage_id" <?= $editing && ! in_array('deals.move_stage', $can, true) ? 'disabled' : '' ?>>
                <?php foreach ($stages as $stage) : ?>
                    <option value="<?= (int) $stage['id'] ?>" <?= $stageId === (int) $stage['id'] ? 'selected' : '' ?>><?= esc($stage['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <?php if ($editing && ! in_array('deals.move_stage', $can, true)) : ?>
        <input type="hidden" name="stage_id" value="<?= $stageId ?>">
    <?php endif; ?>
    <div class="split">
        <label>Company
            <select name="company_id">
                <option value="">None</option>
                <?php foreach ($companies as $company) : ?>
                    <option value="<?= (int) $company['id'] ?>" <?= $companyId === (int) $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Contact
            <select name="contact_id">
                <option value="">None</option>
                <?php foreach ($contacts as $contact) : ?>
                    <option value="<?= (int) $contact['id'] ?>" <?= $contactId === (int) $contact['id'] ? 'selected' : '' ?>><?= esc($contact['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <label>Owner
        <select name="owner_id">
            <?php foreach ($members as $member) : ?>
                <option value="<?= (int) $member['user_id'] ?>" <?= $ownerId === (int) $member['user_id'] ? 'selected' : '' ?>><?= esc($member['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if (! $editing || in_array('deals.update', $can, true)) : ?>
        <div class="actions">
            <button class="primary" type="submit"><?= icon('handshake') ?> <?= $editing ? 'Save deal' : 'Add deal' ?></button>
            <a class="quiet" href="<?= site_url('app/pipeline') ?>">Back to board</a>
        </div>
    <?php endif; ?>
</form>
<?php if ($editing && in_array('deals.delete', $can, true)) : ?>
    <form method="post" action="<?= site_url('app/pipeline/deals/' . $deal['id'] . '/delete') ?>" class="danger-row" onsubmit="return confirm('Remove this deal?');">
        <?= csrf_field() ?>
        <button class="danger" type="submit"><?= icon('trash-2') ?> Remove deal</button>
    </form>
<?php endif; ?>
<?= $this->include('partials/record_desk') ?>
<?= $this->endSection() ?>
