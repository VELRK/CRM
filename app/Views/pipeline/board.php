<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $move = in_array('deals.move_stage', $can, true);
    $failed = old('form') === 'deal';
    $recordId = (int) (old('record_id') ?: ($editing['id'] ?? 0));
    $action = $recordId > 0 ? site_url('app/pipeline/deals/' . $recordId) : site_url('app/pipeline/deals');
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
        if ($field === 'amount') {
            return $plain((int) ($editing['amount_minor'] ?? 0));
        }
        if (array_key_exists($field, $editing) && $editing[$field] !== null) {
            return (string) $editing[$field];
        }
        return $fallback;
    };
?>
<header class="page-head">
    <div>
        <p class="eyebrow"><?= esc($board['pipeline']['name'] ?? 'Pipeline') ?></p>
        <h1>Pipeline</h1>
        <p class="lede">Drag a deal into a stage. Opening or editing it stays on this board.</p>
    </div>
    <?php if (in_array('deals.create', $can, true)) : ?>
        <button class="primary" type="button" data-modal="deal-modal" data-action="<?= site_url('app/pipeline/deals') ?>" data-title="New deal" data-record_id="" data-title_field="" data-amount="" data-stage_id="<?= (int) ($board['stages'][0]['id'] ?? 0) ?>" data-company_id="" data-contact_id="" data-owner_id="<?= (int) session()->get('user_id') ?>"><?= icon('plus') ?> New deal</button>
    <?php endif; ?>
</header>
<form class="search" method="get" action="<?= site_url('app/pipeline') ?>">
    <?= icon('search') ?>
    <input name="q" value="<?= esc($query) ?>" placeholder="Search deal or company">
</form>
<div class="board">
    <?php foreach ($board['stages'] as $stage) : ?>
        <section class="column" data-stage="<?= (int) $stage['id'] ?>">
            <h2><?= esc($stage['name']) ?> <span><?= count($board['deals'][(int) $stage['id']] ?? []) ?></span></h2>
            <?php foreach ($board['deals'][(int) $stage['id']] ?? [] as $deal) :
                $rupees = $plain((int) $deal['amount_minor']);
            ?>
                <form class="deal" method="post" action="<?= site_url('app/pipeline/deals/' . $deal['id'] . '/stage') ?>" <?= $move ? 'draggable="true"' : '' ?>>
                    <?= csrf_field() ?>
                    <input type="hidden" name="stage_id" value="<?= (int) $deal['stage_id'] ?>">
                    <strong><?= esc($deal['title']) ?></strong>
                    <em><?= esc($deal['company_name'] ?? $deal['owner_name'] ?? '') ?></em>
                    <strong><?= esc(\App\Libraries\BookService::minorToRupees((int) $deal['amount_minor'])) ?></strong>
                    <div class="actions-cell wrap">
                        <?php if (in_array('deals.update', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="deal-modal" data-action="<?= site_url('app/pipeline/deals/' . $deal['id']) ?>" data-title="Edit deal" data-record_id="<?= (int) $deal['id'] ?>" data-title_field="<?= esc($deal['title'], 'attr') ?>" data-amount="<?= esc($rupees, 'attr') ?>" data-stage_id="<?= (int) $deal['stage_id'] ?>" data-company_id="<?= (int) $deal['company_id'] ?: '' ?>" data-contact_id="<?= (int) $deal['contact_id'] ?: '' ?>" data-owner_id="<?= (int) $deal['owner_id'] ?: '' ?>"><?= icon('square-pen') ?></button>
                        <?php endif; ?>
                        <?php if (in_array('activities.create', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="log-modal" data-title="Log · <?= esc($deal['title'], 'attr') ?>" data-subject_type="deal" data-subject_id="<?= (int) $deal['id'] ?>"><?= icon('messages-square') ?></button>
                        <?php endif; ?>
                        <?php if (in_array('files.attach', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="file-modal" data-title="Attach · <?= esc($deal['title'], 'attr') ?>" data-subject_type="deal" data-subject_id="<?= (int) $deal['id'] ?>"><?= icon('paperclip') ?></button>
                        <?php endif; ?>
                        <?php if (in_array('deals.delete', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="confirm-delete" data-action="<?= site_url('app/pipeline/deals/' . $deal['id'] . '/delete') ?>" data-title="Remove this deal?"><?= icon('trash-2') ?></button>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
</div>
<?php if (in_array('deals.create', $can, true) || in_array('deals.update', $can, true)) : ?>
<dialog id="deal-modal" class="modal" <?= ! empty($open) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= $action ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="deal">
        <input type="hidden" name="record_id" data-field="record_id" value="<?= $recordId ?: '' ?>">
        <header class="modal-head">
            <h2 data-modal-title><?= $recordId ? 'Edit deal' : 'New deal' ?></h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>Title <input name="title" data-field="title_field" value="<?= esc($pick('title')) ?>" required></label>
            <div class="split">
                <label>Amount (₹) <input name="amount" data-field="amount" inputmode="decimal" value="<?= esc($pick('amount')) ?>"></label>
                <label>Stage
                    <select name="stage_id" data-field="stage_id">
                        <?php foreach ($board['stages'] as $stage) : ?>
                            <option value="<?= (int) $stage['id'] ?>" <?= (string) $pick('stage_id') === (string) $stage['id'] ? 'selected' : '' ?>><?= esc($stage['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="split">
                <label>Company
                    <select name="company_id" data-field="company_id">
                        <option value="">None</option>
                        <?php foreach ($companies as $company) : ?>
                            <option value="<?= (int) $company['id'] ?>" <?= (string) $pick('company_id') === (string) $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Contact
                    <select name="contact_id" data-field="contact_id">
                        <option value="">None</option>
                        <?php foreach ($contacts as $contact) : ?>
                            <option value="<?= (int) $contact['id'] ?>" <?= (string) $pick('contact_id') === (string) $contact['id'] ? 'selected' : '' ?>><?= esc($contact['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
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
            <button class="primary" type="submit"><?= icon('handshake') ?> Save deal</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
<?= $this->include('partials/quick_actions') ?>
<?php if ($move) : ?>
<script>
document.querySelectorAll('.column').forEach(function (column) {
    column.addEventListener('dragover', function (event) { event.preventDefault(); column.classList.add('over'); });
    column.addEventListener('dragleave', function () { column.classList.remove('over'); });
    column.addEventListener('drop', function (event) {
        event.preventDefault();
        column.classList.remove('over');
        var form = document.querySelector('.deal.dragging');
        if (!form) return;
        form.querySelector('[name="stage_id"]').value = column.dataset.stage;
        form.submit();
    });
});
document.querySelectorAll('.deal[draggable="true"]').forEach(function (card) {
    card.addEventListener('dragstart', function (event) {
        if (event.target.closest('button')) {
            event.preventDefault();
            return;
        }
        card.classList.add('dragging');
    });
    card.addEventListener('dragend', function () { card.classList.remove('dragging'); });
});
</script>
<?php endif; ?>
<?= $this->endSection() ?>
