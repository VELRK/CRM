<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow">Enterprise</p>
        <h1>Audit log</h1>
        <p class="lede">A person did each of these. Nothing here was done by a rule.</p>
    </div>
</header>
<?php if ($rows === []) : ?>
    <section class="panel"><div class="empty"><?= icon('inbox') ?><p>No audit entries yet.</p></div></section>
<?php else : ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row) : ?>
                <tr>
                    <td><?= esc(date('d M Y, H:i', strtotime((string) $row['created_at']))) ?></td>
                    <td><?= esc(person_label((string) ($row['user_name'] ?? ''), (int) ($row['user_id'] ?? 0), $designationByUser ?? [])) ?></td>
                    <td><?= esc($row['action']) ?></td>
                    <td><?= esc($row['detail']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
