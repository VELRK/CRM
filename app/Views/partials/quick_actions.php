<?php if (in_array('activities.create', $can, true)) : ?>
<dialog id="log-modal" class="modal">
    <form method="post" action="<?= site_url('app/activities') ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="subject_type" data-field="subject_type">
        <input type="hidden" name="subject_id" data-field="subject_id">
        <header class="modal-head">
            <h2 data-modal-title>Log</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <div class="split">
                <label>Kind
                    <select name="kind">
                        <option value="note">Note</option>
                        <option value="call">Call</option>
                        <option value="meeting">Meeting</option>
                    </select>
                </label>
                <label>When <input type="datetime-local" name="happened_at"></label>
            </div>
            <label>What happened <textarea name="body" required></textarea></label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('messages-square') ?> Log</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
<?php if (in_array('files.attach', $can, true)) : ?>
<dialog id="file-modal" class="modal">
    <form method="post" action="<?= site_url('app/files') ?>" data-modal-form enctype="multipart/form-data" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="subject_type" data-field="subject_type">
        <input type="hidden" name="subject_id" data-field="subject_id">
        <header class="modal-head">
            <h2 data-modal-title>Attach a file</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>File <input type="file" name="upload" required></label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('paperclip') ?> Attach</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
