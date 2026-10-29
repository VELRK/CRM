<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $field = static function (string $key, $default = '') use ($quote) {
        $old = old($key);
        if ($old !== null) {
            return $old;
        }
        if (is_array($quote) && array_key_exists($key, $quote) && $quote[$key] !== null) {
            return $quote[$key];
        }
        return $default;
    };
    $action = is_array($quote) ? site_url('app/quotes/' . $quote['id']) : site_url('app/quotes');
    $summary = \App\Libraries\DeskService::summarize($items ?? []);
    $lineJson = static function (array $rows): string {
        $payload = [];
        foreach ($rows as $row) {
            $minor = (int) ($row['price_minor'] ?? 0);
            $payload[] = [
                'description' => (string) ($row['description'] ?? ''),
                'qty'         => (string) ($row['qty'] ?? '1'),
                'price'       => $minor % 100 === 0 ? (string) ($minor / 100) : number_format($minor / 100, 2, '.', ''),
                'tax'         => (string) ($row['tax_rate'] ?? '18'),
                'product_id'  => (string) ($row['product_id'] ?? ''),
            ];
        }
        return esc((string) json_encode($payload, JSON_UNESCAPED_UNICODE), 'attr');
    };
    $idAttr = static function ($value): string {
        $value = (int) $value;
        return $value > 0 ? (string) $value : '';
    };
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Money</p>
        <h1>Quotes</h1>
    </div>
    <?php if (in_array('quotes.create', $can, true)) : ?>
        <button class="primary" type="button" data-modal="quote-editor" data-action="<?= site_url('app/quotes') ?>" data-title="New quote" data-contact_id="" data-company_id="" data-deal_id="" data-notes="" data-lines="[]"><?= icon('plus') ?> New quote</button>
    <?php endif; ?>
</header>
<?php if ($quotes === []) : ?>
    <section class="panel"><div class="empty"><?= icon('inbox') ?><p>No quotes yet. Build one, then send it yourself.</p></div></section>
<?php else : ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Number</th><th>For</th><th>Status</th><th class="num">Total</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($quotes as $row) : ?>
                <tr>
                    <td><strong><?= esc($row['number']) ?></strong></td>
                    <td><?= esc($row['contact_name'] ?: ($row['company_name'] ?: '—')) ?></td>
                    <td><span class="badge <?= $row['status'] === 'draft' ? 'mute' : '' ?>"><?= esc(ucfirst((string) $row['status'])) ?></span></td>
                    <td class="num"><?= esc(\App\Libraries\DeskService::money((int) $row['total_minor'], (string) $row['currency'])) ?></td>
                    <td class="actions-cell">
                        <?php if (in_array('quotes.update', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="quote-editor" data-action="<?= site_url('app/quotes/' . $row['id']) ?>" data-title="<?= esc($row['number'], 'attr') ?>" data-contact_id="<?= esc($idAttr($row['contact_id'] ?? 0), 'attr') ?>" data-company_id="<?= esc($idAttr($row['company_id'] ?? 0), 'attr') ?>" data-deal_id="<?= esc($idAttr($row['deal_id'] ?? 0), 'attr') ?>" data-notes="<?= esc((string) ($row['notes'] ?? ''), 'attr') ?>" data-lines="<?= $lineJson($lineMap[$row['id']] ?? []) ?>"><?= icon('square-pen') ?> Edit</button>
                        <?php endif; ?>
                        <?php if (in_array('quotes.send', $can, true) && $row['sent_at'] === null) : ?>
                            <form method="post" action="<?= site_url('app/quotes/' . $row['id'] . '/send') ?>" onsubmit="return confirm('Mark this quote sent?');">
                                <?= csrf_field() ?>
                                <button class="ghost" type="submit"><?= icon('mail') ?> Send</button>
                            </form>
                        <?php endif; ?>
                        <?php if (in_array('invoices.create', $can, true)) : ?>
                            <form method="post" action="<?= site_url('app/quotes/' . $row['id'] . '/invoice') ?>">
                                <?= csrf_field() ?>
                                <button class="ghost" type="submit"><?= icon('receipt') ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if (in_array('quotes.delete', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="confirm-delete" data-action="<?= site_url('app/quotes/' . $row['id'] . '/delete') ?>" data-title="Remove <?= esc($row['number'], 'attr') ?>?"><?= icon('trash-2') ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<dialog id="quote-editor" class="modal wide" <?= ! empty($open) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= $action ?>" data-modal-form>
        <?= csrf_field() ?>
        <header class="modal-head">
            <h2 data-modal-title><?= is_array($quote) ? esc($quote['number']) : 'New quote' ?></h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <div class="split">
                <label>Contact
                    <select name="contact_id" data-field="contact_id">
                        <option value="">None</option>
                        <?php foreach ($contacts as $contact) : ?>
                            <option value="<?= (int) $contact['id'] ?>" <?= (int) $field('contact_id') === (int) $contact['id'] ? 'selected' : '' ?>><?= esc($contact['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Company
                    <select name="company_id" data-field="company_id">
                        <option value="">None</option>
                        <?php foreach ($companies as $company) : ?>
                            <option value="<?= (int) $company['id'] ?>" <?= (int) $field('company_id') === (int) $company['id'] ? 'selected' : '' ?>><?= esc($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>Deal
                <select name="deal_id" data-field="deal_id">
                    <option value="">None</option>
                    <?php foreach ($deals as $deal) : ?>
                        <option value="<?= (int) $deal['id'] ?>" <?= (int) $field('deal_id') === (int) $deal['id'] ? 'selected' : '' ?>><?= esc($deal['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?= $this->include('partials/money_lines') ?>
            <label>Notes <textarea name="notes" data-field="notes"><?= esc((string) $field('notes')) ?></textarea></label>
            <?php if ($summary['total'] > 0) : ?>
                <p>Total <?= esc(\App\Libraries\DeskService::money($summary['total'], (string) ($quote['currency'] ?? $currency))) ?>, including tax.</p>
            <?php endif; ?>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Close</button>
            <?php if (in_array(is_array($quote) ? 'quotes.update' : 'quotes.create', $can, true)) : ?>
                <button class="primary" type="submit"><?= icon('file-text') ?> Save quote</button>
            <?php endif; ?>
        </footer>
    </form>
</dialog>
<?php if (is_array($quote)) : ?>
    <?php $recordType = 'quote'; $recordId = (int) $quote['id']; $activities = []; $recordTasks = []; ?>
    <?= $this->include('partials/record_desk') ?>
<?php endif; ?>
<?= $this->endSection() ?>
