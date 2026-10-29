<?= $this->extend('auth/layout') ?>
<?= $this->section('content') ?>
<h1>Join the workspace</h1>
<p class="lede">Set your name and password. Your role was chosen on the invite.</p>
<form method="post" action="<?= site_url('join/' . $token) ?>" class="stack">
    <?= csrf_field() ?>
    <label>Your name <input name="name" required></label>
    <label>Password <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
    <button class="primary" type="submit"><?= icon('log-in') ?> Join</button>
</form>
<?= $this->endSection() ?>
