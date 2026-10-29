<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow"><?= esc(ucfirst((string) ($planCode ?? 'starter'))) ?></p>
        <h1><?= esc($module) ?></h1>
    </div>
</header>
<section class="panel">
    <div class="empty">
        <?= icon('credit-card') ?>
        <p><?= esc($module) ?> is included on a higher plan. The records stay in this workspace when you change plan.</p>
        <?php if (in_array('billing.manage', $can, true)) : ?>
            <a class="primary" href="<?= site_url('app/billing') ?>"><?= icon('credit-card') ?> Review plans</a>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
