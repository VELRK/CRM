<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow">Company</p>
        <h1><?= esc($company['name']) ?></h1>
        <p class="lede"><?= esc($company['phone'] ?? '') ?></p>
    </div>
</header>
<?= $this->include('people/tabs') ?>
<section class="panel">
    <p>Owner: <?= esc($company['owner_name'] ?? 'Unassigned') ?></p>
</section>
<?= $this->include('partials/record_desk') ?>
<?= $this->endSection() ?>
