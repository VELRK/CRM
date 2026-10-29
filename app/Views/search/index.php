<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow">Search</p>
        <h1><?= $query === '' ? 'Search' : esc($query) ?></h1>
    </div>
</header>
<?php
    $groups = [
        ['Leads', 'contact', 'app/leads?edit=', $results['leads'] ?? [], 'name'],
    ];
    $any = false;
?>
<?php foreach ($groups as [$label, $iconName, $href, $rows, $field]) : ?>
    <?php if ($rows === []) { continue; } $any = true; ?>
    <section class="panel" style="margin-bottom: 12px;">
        <h2><?= esc($label) ?></h2>
        <div class="list">
            <?php foreach ($rows as $row) : ?>
                <a class="row" href="<?= site_url($href . $row['id']) ?>">
                    <span class="well"><?= icon($iconName) ?></span>
                    <span class="row-copy"><strong><?= esc($row[$field]) ?></strong></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
<?php if (! $any) : ?>
    <section class="panel"><div class="empty"><?= icon('inbox') ?><p><?= $query === '' ? 'Type in the search bar.' : 'Nothing matches that search.' ?></p></div></section>
<?php endif; ?>
<?= $this->endSection() ?>
