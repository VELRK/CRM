<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Atrium') ?></title>
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
</head>
<body class="auth-body">
    <main class="auth-card">
        <p class="eyebrow"><?= icon('building-2') ?> Atrium</p>
        <?php if ($error = session()->getFlashdata('error')) : ?>
            <p class="banner danger"><?= esc($error) ?></p>
        <?php endif; ?>
        <?= $this->renderSection('content') ?>
    </main>
</body>
</html>
