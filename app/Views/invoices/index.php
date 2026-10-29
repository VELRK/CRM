<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $field = static function (string $key, $default = '') use ($invoice) {
        $old = old($key);
        if ($old !== null) {
            return $old;
        }
        if (is_array($invoice) && array_key_exists($key, $invoice) && $invoice[$key] !== null) {
            return $invoice[$key];
        }
        return $default;
    };
    $action = is_array($invoice) ? site_url('app/invoices/' . $invoice['id']) : site_url('app/invoices');
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
    $current = null;
    if (is_array($invoice)) {
        foreach ($invoices as $row) {
            if ((int) $row['id'] === (int) $invoice['id']) {
                $current = $row;
            }
        }
    }
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Money</p>
        <h1>Invoices</h1>
    </div>
    <?php if (in_array('invoices.create', $can, true)) : ?>
        <button class="primary" type="button" data-modal="invoice-editor" data-action="<?= site_url('app/invoices') ?>" data-title="New invoice" data-contact_id="" data-company_id="" data-deal_id="" data-due_on="" data-notes="" data-lines="[]"><?= icon('plus') ?> New invoice</button>
    <?php endif; ?>
</header>
<?php if ($invoices === []) : ?>
    <section class="panel"><div class="empty"><?= icon('inbox') ?><p>No invoices yet.</p></div></section>
<?php else : ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Number</th><th>For</th><th>Status</th><th class="num">Balance</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($invoices as $row) :
                $badge = $row['status'] === 'paid' ? '' : ($row['status'] === 'partial' ? 'warn' : 'mute');
            ?>
                <tr>
                    <td><strong><?= esc($row['number']) ?></strong></td>
                    <td><?= esc($row['contact_name'] ?: ($row['company_name'] ?: '—')) ?></td>
                    <td><span class="badge <?= esc($badge) ?>"><?= esc(ucfirst((string) $row['status'])) ?></span></td>
                    <td class="num"><?= esc(\App\Libraries\DeskService::money((int) $row['balance_minor'], (string) $row['currency'])) ?></td>
                    <td class="actions-cell">
                        <?php if (in_array('invoices.update', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="invoice-editor" data-action="<?= site_url('app/invoices/' . $row['id']) ?>" data-title="<?= esc($row['number'], 'attr') ?>" data-contact_id="<?= esc($idAttr($row['contact_id'] ?? 0), 'attr') ?>" data-company_id="<?= esc($idAttr($row['company_id'] ?? 0), 'attr') ?>" data-deal_id="<?= esc($idAttr($row['deal_id'] ?? 0), 'attr') ?>" data-due_on="<?= esc((string) ($row['due_on'] ?? ''), 'attr') ?>" data-notes="<?= esc((string) ($row['notes'] ?? ''), 'attr') ?>" data-lines="<?= $lineJson($lineMap[$row['id']] ?? []) ?>" data-pay="<?= esc(site_url('app/invoices/' . $row['id'] . '/payments'), 'attr') ?>"><?= icon('square-pen') ?> Edit</button>
                        <?php endif; ?>
                        <?php if (in_array('invoices.send', $can, true) && $row['sent_at'] === null) : ?>
                            <form method="post" action="<?= site_url('app/invoices/' . $row['id'] . '/send') ?>" onsubmit="return confirm('Mark this invoice sent?');">
                                <?= csrf_field() ?>
                                <button class="ghost" type="submit"><?= icon('mail') ?> Send</button>
                            </form>
                        <?php endif; ?>
                        <?php if (in_array('invoices.delete', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="confirm-delete" data-action="<?= site_url('app/invoices/' . $row['id'] . '/delete') ?>" data-title="Remove <?= esc($row['number'], 'attr') ?>?"><?= icon('trash-2') ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<dialog id="invoice-editor" class="modal wide" <?= ! empty($open) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= $action ?>" data-modal-form>
        <?= csrf_field() ?>
        <header class="modal-head">
            <h2 data-modal-title><?= is_array($invoice) ? esc($invoice['number']) : 'New invoice' ?></h2>
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
            <div class="split">
                <label>Deal
                    <select name="deal_id" data-field="deal_id">
                        <option value="">None</option>
                        <?php foreach ($deals as $deal) : ?>
                            <option value="<?= (int) $deal['id'] ?>" <?= (int) $field('deal_id') === (int) $deal['id'] ? 'selected' : '' ?>><?= esc($deal['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Due <input type="date" name="due_on" data-field="due_on" value="<?= esc((string) $field('due_on')) ?>"></label>
            </div>
            <?= $this->include('partials/money_lines') ?>
            <label>Notes <textarea name="notes" data-field="notes"><?= esc((string) $field('notes')) ?></textarea></label>
            <?php if (is_array($current)) : ?>
                <p>Balance <?= esc(\App\Libraries\DeskService::money((int) $current['balance_minor'], (string) $current['currency'])) ?> of <?= esc(\App\Libraries\DeskService::money((int) $current['total_minor'], (string) $current['currency'])) ?>.</p>
            <?php endif; ?>
        </div>
        <footer class="modal-foot">
            <?php if (in_array('payments.record', $can, true)) : ?>
                <button class="ghost" type="button" id="record-payment" data-modal="payment-modal" data-title="Record payment" <?= is_array($invoice) ? '' : 'hidden' ?>><?= icon('receipt') ?> Record payment</button>
            <?php endif; ?>
            <button class="ghost" type="button" data-close>Close</button>
            <?php if (in_array(is_array($invoice) ? 'invoices.update' : 'invoices.create', $can, true)) : ?>
                <button class="primary" type="submit"><?= icon('receipt') ?> Save invoice</button>
            <?php endif; ?>
        </footer>
    </form>
</dialog>
<?php if (in_array('payments.record', $can, true)) : ?>
<dialog id="payment-modal" class="modal">
    <form method="post" action="<?= is_array($invoice) ? site_url('app/invoices/' . $invoice['id'] . '/payments') : site_url('app/invoices') ?>" data-modal-form>
        <?= csrf_field() ?>
        <header class="modal-head">
            <h2 data-modal-title>Record payment</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>Amount (<?= ($currency ?? 'INR') === 'USD' ? '$' : '₹' ?>) <input name="amount" inputmode="decimal" required></label>
            <label>Paid on <input type="date" name="paid_on" value="<?= esc(date('Y-m-d')) ?>" required></label>
            <label>Note <input name="note"></label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit">Record payment</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
<?php if (is_array($invoice)) : ?>
    <?php $recordType = 'invoice'; $recordId = (int) $invoice['id']; $activities = []; $recordTasks = []; ?>
    <?= $this->include('partials/record_desk') ?>
<?php endif; ?>
<?= $this->endSection() ?>
