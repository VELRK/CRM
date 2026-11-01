<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<header class="page-head">
    <div>
        <p class="eyebrow">Workspace</p>
        <h1>Settings</h1>
    </div>
</header>
<form method="post" action="<?= site_url('app/settings') ?>" class="panel stack narrow" enctype="multipart/form-data">
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
    <h2>WhatsApp</h2>
    <p class="lede">Messages go through AskEva. Leave the token blank to keep the one already saved. <?= ! empty($whatsapp['tokenSaved']) ? 'A token is saved.' : 'No token is saved yet.' ?></p>
    <label>Business number <input name="whatsapp_phone" value="<?= esc($whatsapp['phone'] ?? '') ?>"></label>
    <label>AskEva token <input name="whatsapp_token" type="password" placeholder="Leave blank to keep the saved token" autocomplete="off"></label>
    <label>Welcome template <input name="whatsapp_welcome" value="<?= esc($whatsapp['welcome'] ?? '') ?>" placeholder="Exact AskEva template name, sent to the lead"></label>
    <label>Lead assign template <input name="whatsapp_lead_assign" value="<?= esc($whatsapp['leadAssign'] ?? '') ?>" placeholder="Exact AskEva template name, sent to the new owner"></label>
    <label>Follow-up assign template <input name="whatsapp_followup_assign" value="<?= esc($whatsapp['followupAssign'] ?? '') ?>" placeholder="Exact AskEva template name, sent to the assignee"></label>
    <label>Follow-up reminder template <input name="whatsapp_followup_reminder" value="<?= esc($whatsapp['followup'] ?? '') ?>" placeholder="Exact AskEva template name"></label>
    <label>Visit assign template <input name="whatsapp_visit_assign" value="<?= esc($whatsapp['visitAssign'] ?? '') ?>" placeholder="Exact AskEva template name, sent to the assignee"></label>
    <label>Visit reminder template <input name="whatsapp_visit_reminder" value="<?= esc($whatsapp['visit'] ?? '') ?>" placeholder="Exact AskEva template name"></label>
    <label>Task assign template <input name="whatsapp_task_assign" value="<?= esc($whatsapp['taskAssign'] ?? '') ?>" placeholder="Exact AskEva template name, sent to the assignee"></label>
    <label>Task reminder template <input name="whatsapp_task_reminder" value="<?= esc($whatsapp['task'] ?? '') ?>" placeholder="Exact AskEva template name"></label>
    <label>Deal approval template <input name="whatsapp_deal_approve" value="<?= esc($whatsapp['dealApprove'] ?? '') ?>" placeholder="Exact AskEva template name, sent when a manager approves a deal"></label>
    <button class="primary" type="submit"><?= icon('settings') ?> Save</button>
</form>
<?= $this->endSection() ?>
