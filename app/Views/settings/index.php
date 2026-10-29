<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow">Workspace</p>
        <h1>Settings</h1>
    </div>
</header>
<form method="post" action="<?= site_url('app/settings') ?>" class="panel stack narrow">
    <?= csrf_field() ?>
    <label>Workspace name <input name="name" value="<?= esc($name) ?>" required></label>
    <div class="split">
        <label>Currency
            <select name="currency">
                <option value="INR" <?= ($currency ?? 'INR') === 'INR' ? 'selected' : '' ?>>INR</option>
                <option value="USD" <?= ($currency ?? '') === 'USD' ? 'selected' : '' ?>>USD</option>
            </select>
        </label>
        <label>Fiscal year starts
            <select name="fiscal_month">
                <?php foreach ([1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'] as $number => $label) : ?>
                    <option value="<?= $number ?>" <?= (int) ($fiscalMonth ?? 4) === $number ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <button class="primary" type="submit"><?= icon('settings') ?> Save</button>
</form>
<?= $this->endSection() ?>
