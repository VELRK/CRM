<?php if (($recordId ?? 0) < 1) { return; } ?>
<?php $linked = in_array($recordType ?? '', ['contact', 'company', 'deal'], true); ?>
<div class="split-panels" style="margin-top: 16px;">
    <?php if ($linked) : ?>
    <section class="panel stack">
        <h2>Activity</h2>
        <?php if ($linked && in_array('activities.create', $can, true)) : ?>
            <form method="post" action="<?= site_url('app/activities') ?>" class="stack">
                <?= csrf_field() ?>
                <input type="hidden" name="subject_type" value="<?= esc($recordType) ?>">
                <input type="hidden" name="subject_id" value="<?= (int) $recordId ?>">
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
                <button class="primary" type="submit"><?= icon('messages-square') ?> Log</button>
            </form>
        <?php endif; ?>
        <?php if (($activities ?? []) === []) : ?>
            <div class="empty"><?= icon('inbox') ?><p>Nothing logged yet.</p></div>
        <?php else : ?>
            <div class="list">
                <?php foreach ($activities as $activity) : ?>
                    <div class="row static">
                        <span class="well"><?= icon($activity['kind'] === 'call' ? 'phone' : ($activity['kind'] === 'meeting' ? 'calendar' : 'messages-square')) ?></span>
                        <span class="row-copy">
                            <strong><?= esc(ucfirst((string) $activity['kind'])) ?></strong>
                            <em><?= esc($activity['user_name'] ?? '') ?> · <?= esc(date('d M Y, H:i', strtotime((string) $activity['happened_at']))) ?></em>
                        </span>
                    </div>
                    <p><?= esc($activity['body']) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <section class="panel stack">
        <h2>Files and tasks</h2>
        <?php if ($linked && in_array('tasks.create', $can, true)) : ?>
            <form method="post" action="<?= site_url('app/work/tasks') ?>" class="stack">
                <?= csrf_field() ?>
                <input type="hidden" name="return" value="back">
                <input type="hidden" name="<?= esc($recordType) ?>_id" value="<?= (int) $recordId ?>">
                <label>Task <input name="title" required placeholder="Call back"></label>
                <label>Due <input type="datetime-local" name="due_at"></label>
                <button class="ghost" type="submit"><?= icon('square-check') ?> Add task</button>
            </form>
        <?php endif; ?>
        <?php foreach ($recordTasks ?? [] as $task) : ?>
            <a class="row" href="<?= site_url('app/work') ?>">
                <span class="well"><?= icon('square-check') ?></span>
                <span class="row-copy">
                    <strong><?= esc($task['title']) ?></strong>
                    <em><?= $task['done_at'] ? 'Done' : ($task['due_at'] ? esc(date('d M Y, H:i', strtotime((string) $task['due_at']))) : 'No due time') ?></em>
                </span>
            </a>
        <?php endforeach; ?>
        <?php if (in_array('files.attach', $can, true)) : ?>
            <form method="post" action="<?= site_url('app/files') ?>" enctype="multipart/form-data" class="stack">
                <?= csrf_field() ?>
                <input type="hidden" name="subject_type" value="<?= esc($recordType) ?>">
                <input type="hidden" name="subject_id" value="<?= (int) $recordId ?>">
                <label>Attach <input type="file" name="upload" required></label>
                <button class="ghost" type="submit"><?= icon('paperclip') ?> Attach</button>
            </form>
        <?php endif; ?>
        <?php foreach ($recordFiles ?? [] as $file) : ?>
            <div class="row static">
                <span class="well"><?= icon('paperclip') ?></span>
                <span class="row-copy"><strong><?= esc($file['original_name']) ?></strong></span>
                <a href="<?= site_url('app/files/' . $file['id']) ?>">Download</a>
            </div>
        <?php endforeach; ?>
    </section>
</div>
