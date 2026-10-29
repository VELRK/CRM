<?= $this->extend('auth/layout') ?>
<?= $this->section('content') ?>
<h1>Create a workspace</h1>
<p class="lede">You become the Owner. Five starter roles are added, and you can add more.</p>
<form method="post" action="<?= site_url('register') ?>" class="stack">
    <?= csrf_field() ?>
    <label>Your name <input name="name" value="<?= esc(old('name')) ?>" required></label>
    <label>Email <input type="email" name="email" value="<?= esc(old('email')) ?>" required></label>
    <label>Password <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
    <label>Workspace <input name="workspace" value="<?= esc(old('workspace')) ?>" required placeholder="Northwind"></label>
    <button class="primary" type="submit"><?= icon('building-2') ?> Create workspace</button>
</form>
<p class="fine">Already have an account? <a href="<?= site_url('login') ?>">Sign in</a></p>
<?= $this->endSection() ?>
