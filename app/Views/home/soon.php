<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow">Next</p>
        <h1><?= esc($title) ?></h1>
    </div>
</header>
<section class="panel">
    <div class="empty">
        <?= icon($icon) ?>
        <p>This page is part of the book of business. Sign-in, roles, and access are in place first.</p>
    </div>
</section>
<?= $this->endSection() ?>
