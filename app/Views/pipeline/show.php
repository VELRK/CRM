<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow"><?= esc($deal['stage_name']) ?></p>
        <h1><?= esc($deal['title']) ?></h1>
        <p class="lede"><?= esc(\App\Libraries\BookService::minorToRupees((int) $deal['amount_minor'])) ?></p>
    </div>
</header>
<p><a class="quiet" href="<?= site_url('app/pipeline') ?>">Back to board</a></p>
<?= $this->include('partials/record_desk') ?>
<?= $this->endSection() ?>
