<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow">Workspace</p>
        <h1>Billing</h1>
        <p class="lede">Enterprise is the edition to sell. Starter and Growth are the same app with lower limits. Prices are per workspace, per month, GST extra.</p>
    </div>
</header>
<div class="plans">
    <?php
        $cards = [
            ['starter', 'Starter', '₹1,499', '3 users, 2,000 contacts, home figures.'],
            ['growth', 'Growth', '₹4,999', 'Quotes, invoices, and full reports.'],
            ['enterprise', 'Enterprise', '₹14,999', 'Audit log, 100 users. Recommended.'],
        ];
    ?>
    <?php foreach ($cards as [$code, $label, $price, $copy]) : ?>
        <form method="post" action="<?= site_url('app/billing') ?>" class="panel plan <?= $plan === $code ? 'current' : '' ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="plan_code" value="<?= esc($code) ?>">
            <p class="eyebrow"><?= esc($label) ?></p>
            <strong><?= esc($price) ?></strong>
            <p><?= esc($copy) ?></p>
            <button class="primary" type="submit"><?= $plan === $code ? 'Current plan' : 'Use ' . $label ?></button>
        </form>
    <?php endforeach; ?>
</div>
<?= $this->endSection() ?>
