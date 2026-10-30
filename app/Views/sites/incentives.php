<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow">Sales</p>
        <h1>Incentives</h1>
        <p class="lede">A sale is recorded when a plot is marked booked, sold or registered. The incentive is the commission entered on that plot.</p>
    </div>
</header>
<?php if ($incentives === []) : ?>
    <section class="panel"><div class="empty"><?= icon('inbox') ?><p>No incentives yet.</p></div></section>
<?php else : ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Staff</th><th>Plot</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($incentives as $row) : ?>
                <tr>
                    <td><strong><?= esc(person_label((string) $row['user_name'], (int) $row['user_id'], $designationByUser ?? [])) ?></strong><div class="fine"><?= esc($row['rule_note'] ?? '') ?></div></td>
                    <td><?= esc(($row['project_name'] ?? '') . ' · ' . ($row['plot_no'] ?? '')) ?></td>
                    <td class="num"><?= esc(\App\Libraries\DeskService::money((int) $row['amount_minor'], 'INR')) ?></td>
                    <td><span class="badge"><?= esc(str_replace('_', ' ', (string) $row['status'])) ?></span></td>
                    <td class="actions-cell">
                        <?php if (in_array('incentives.approve', $can, true) && ! in_array($row['status'], ['paid', 'cancelled', 'not_eligible'], true)) : ?>
                            <form method="post" action="<?= site_url('app/incentives/' . $row['id']) ?>">
                                <?= csrf_field() ?>
                                <select name="status" onchange="this.form.submit()">
                                    <option value="">Update</option>
                                    <option value="approved">Approved</option>
                                    <option value="paid">Paid</option>
                                    <option value="on_hold">On hold</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
