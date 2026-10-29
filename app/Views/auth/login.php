<?= $this->extend('auth/layout') ?>
<?= $this->section('content') ?>
<h1>Sign in</h1>
<p class="lede">The book of business for this workspace.</p>
<form method="post" action="<?= site_url('login') ?>" class="stack">
    <?= csrf_field() ?>
    <label>Email <input type="email" name="email" value="<?= esc(old('email')) ?>" required autocomplete="username"></label>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
    <button class="primary" type="submit"><?= icon('log-in') ?> Continue</button>
</form>
<p class="fine">New workspace? <a href="<?= site_url('register') ?>">Create one</a></p>
<p class="fine">Mobile team and React desk: <a href="<?= base_url('desk/index.html') ?>">Open the API desk</a></p>
<?= $this->endSection() ?>
