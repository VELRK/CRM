<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow">Contact</p>
        <h1><?= esc($contact['name']) ?></h1>
        <p class="lede"><?= esc(trim(($contact['phone'] ?? '') . ' · ' . ($contact['email'] ?? ''), ' ·')) ?></p>
    </div>
</header>
<?= $this->include('people/tabs') ?>
<section class="panel">
    <p>Owner: <?= esc($contact['owner_name'] ?? 'Unassigned') ?></p>
    <?php if (! empty($contact['tags'])) : ?><p>Tags: <?= esc($contact['tags']) ?></p><?php endif; ?>
</section>
<?php if ($companies !== []) : ?>
    <section class="list" style="margin-top: 16px;">
        <?php foreach ($companies as $company) : ?>
            <a class="row" href="<?= site_url('app/companies/' . $company['id']) ?>">
                <span class="well"><?= icon('landmark') ?></span>
                <span class="row-copy"><strong><?= esc($company['name']) ?></strong></span>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?= $this->include('partials/record_desk') ?>
<?= $this->endSection() ?>
