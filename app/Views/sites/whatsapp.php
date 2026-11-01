<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow">Messages</p>
        <h1>WhatsApp</h1>
        <p class="lede">Messages go through AskEva, using the template name saved here. A follow-up, visit, or task reminder uses the template named below and also sends a phone push to the assigned person.</p>
    </div>
    <?php if (in_array('whatsapp.manage', $can, true)) : ?>
        <button class="primary" type="button" data-modal="template-modal" data-title="New template" data-name="" data-language="en" data-body=""><?= icon('plus') ?> Template</button>
    <?php endif; ?>
</header>
<?php if (in_array('whatsapp.manage', $can, true)) : ?>
<form method="post" action="<?= site_url('app/whatsapp/settings') ?>" class="panel stack">
    <?= csrf_field() ?>
    <label>Business number <input name="whatsapp_phone" value="<?= esc($phone) ?>"></label>
    <label>AskEva token <input name="whatsapp_token" type="password" placeholder="Leave blank to keep the saved token"></label>
    <label>Follow-up reminder template <input name="whatsapp_followup_reminder" value="<?= esc($followupReminder ?? '') ?>" placeholder="Exact AskEva template name"></label>
    <label>Visit reminder template <input name="whatsapp_visit_reminder" value="<?= esc($visitReminder ?? '') ?>" placeholder="Exact AskEva template name"></label>
    <label>Task reminder template <input name="whatsapp_task_reminder" value="<?= esc($taskReminder ?? '') ?>" placeholder="Exact AskEva template name"></label>
    <button class="ghost" type="submit">Save connection</button>
</form>
<?php endif; ?>
<div class="table-wrap">
    <table class="data">
        <thead><tr><th>Template</th><th>Language</th><th>Message</th></tr></thead>
        <tbody>
        <?php foreach ($templates as $template) : ?>
            <tr>
                <td><strong><?= esc($template['name']) ?></strong></td>
                <td><?= esc($template['language']) ?></td>
                <td><?= esc($template['body']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<dialog id="template-modal" class="modal" <?= ! empty($open) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= site_url('app/whatsapp/templates') ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="template">
        <input type="hidden" name="active" value="1">
        <header class="modal-head">
            <h2 data-modal-title>Template</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>Name <input name="name" data-field="name" required></label>
            <label>Language <input name="language" data-field="language" value="en"></label>
            <label>Message <textarea name="body" data-field="body" required placeholder="Hello {{name}}"></textarea></label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('messages-square') ?> Save template</button>
        </footer>
    </form>
</dialog>
<?= $this->endSection() ?>
