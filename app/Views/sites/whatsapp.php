<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow">Messages</p>
        <h1>WhatsApp</h1>
        <p class="lede">Templates are stored per workspace. A send from a lead is saved on that lead. Delivery stays off until Askeva is connected, so nothing is sent automatically.</p>
    </div>
    <button class="primary" type="button" data-modal="template-modal" data-title="New template" data-name="" data-language="en" data-body=""><?= icon('plus') ?> Template</button>
</header>
<form method="post" action="<?= site_url('app/whatsapp/settings') ?>" class="panel stack">
    <?= csrf_field() ?>
    <label>Business number <input name="whatsapp_phone" value="<?= esc($phone) ?>"></label>
    <label>Askeva token <input name="whatsapp_token" type="password" placeholder="Leave blank to keep the saved token"></label>
    <button class="ghost" type="submit">Save connection</button>
</form>
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
