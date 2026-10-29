<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow">Inbox</p>
        <h1>Notices</h1>
    </div>
    <?php if ($notices !== []) : ?>
        <form method="post" action="<?= site_url('app/notices/read') ?>">
            <?= csrf_field() ?>
            <button class="ghost" type="submit">Mark all read</button>
        </form>
    <?php endif; ?>
</header>
<?php if ($notices === []) : ?>
    <section class="panel"><div class="empty"><?= icon('inbox') ?><p>No notices. A reminder appears here only after you set it.</p></div></section>
<?php else : ?>
    <section class="list">
        <?php foreach ($notices as $notice) : ?>
            <form method="post" action="<?= site_url('app/notices/' . $notice['id'] . '/read') ?>">
                <?= csrf_field() ?>
                <button class="row" type="submit" style="width: 100%; text-align: left;">
                    <span class="well"><?= icon('bell') ?></span>
                    <span class="row-copy">
                        <strong><?= esc($notice['title']) ?></strong>
                        <em><?= esc(trim(($notice['body'] ?? '') . ' · ' . date('d M Y, H:i', strtotime((string) $notice['created_at'])), ' ·')) ?></em>
                    </span>
                    <?= $notice['read_at'] ? '' : '<span class="badge">New</span>' ?>
                </button>
            </form>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?= $this->endSection() ?>
