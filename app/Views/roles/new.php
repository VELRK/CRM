<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow">Access</p>
        <h1>Add a role</h1>
        <p class="lede">Name it, then choose what that role can do.</p>
    </div>
</header>
<form method="post" action="<?= site_url('app/roles') ?>" class="panel stack narrow">
    <?= csrf_field() ?>
    <label>Role name <input name="name" value="<?= esc(old('name')) ?>" required placeholder="Finance"></label>
    <div class="actions">
        <button class="primary" type="submit"><?= icon('plus') ?> Add role</button>
        <a class="quiet" href="<?= site_url('app/roles') ?>">Cancel</a>
    </div>
</form>
<?= $this->endSection() ?>
