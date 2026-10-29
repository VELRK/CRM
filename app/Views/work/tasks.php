<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $stamp = static function (?string $value): string {
        if ($value === null || $value === '') {
            return '';
        }
        return substr(str_replace(' ', 'T', $value), 0, 16);
    };
    $failedId = (int) old('record_id');
    $action = $failedId > 0 ? site_url('app/work/tasks/' . $failedId) : site_url('app/work/tasks');
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Daily work</p>
        <h1>Tasks</h1>
    </div>
    <?php if (in_array('tasks.create', $can, true)) : ?>
        <button class="primary" type="button" data-modal="task-modal" data-action="<?= site_url('app/work/tasks') ?>" data-title="New task" data-record_id="" data-title_field="" data-notes="" data-due_at="" data-remind_at="" data-owner_id="<?= (int) session()->get('user_id') ?>"><?= icon('plus') ?> New task</button>
    <?php endif; ?>
</header>
<?= $this->include('partials/work_tabs') ?>
<?php if ($tasks === []) : ?>
    <section class="panel"><div class="empty"><?= icon('inbox') ?><p>No tasks yet. Add one when a person should do it.</p></div></section>
<?php else : ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Task</th><th>Due</th><th>Owner</th><th>State</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tasks as $task) : ?>
                <tr>
                    <td><strong><?= esc($task['title']) ?></strong></td>
                    <td><?= $task['due_at'] ? esc(date('d M Y, H:i', strtotime((string) $task['due_at']))) : '—' ?></td>
                    <td><?= esc($task['owner_name'] ?? '') ?></td>
                    <td><?= $task['done_at'] ? '<span class="badge">Done</span>' : '<span class="badge mute">Open</span>' ?></td>
                    <td class="actions-cell">
                        <?php if (in_array('tasks.update', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="task-modal" data-action="<?= site_url('app/work/tasks/' . $task['id']) ?>" data-title="Edit task" data-record_id="<?= (int) $task['id'] ?>" data-title_field="<?= esc($task['title'], 'attr') ?>" data-notes="<?= esc((string) ($task['notes'] ?? ''), 'attr') ?>" data-due_at="<?= esc($stamp($task['due_at']), 'attr') ?>" data-remind_at="<?= esc($stamp($task['remind_at']), 'attr') ?>" data-owner_id="<?= (int) $task['owner_id'] ?>"><?= icon('square-pen') ?> Edit</button>
                            <form method="post" action="<?= site_url('app/work/tasks/' . $task['id'] . '/done') ?>">
                                <?= csrf_field() ?>
                                <button class="ghost" type="submit"><?= $task['done_at'] ? 'Reopen' : 'Done' ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if (in_array('tasks.delete', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="confirm-delete" data-action="<?= site_url('app/work/tasks/' . $task['id'] . '/delete') ?>" data-title="Remove this task?"><?= icon('trash-2') ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<dialog id="task-modal" class="modal" <?= ! empty($open) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= $action ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="record_id" data-field="record_id" value="<?= $failedId ?: '' ?>">
        <header class="modal-head">
            <h2 data-modal-title><?= $failedId ? 'Edit task' : 'New task' ?></h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>Title <input name="title" data-field="title_field" value="<?= esc((string) old('title')) ?>" required></label>
            <div class="split">
                <label>Due <input type="datetime-local" name="due_at" data-field="due_at" value="<?= esc((string) old('due_at')) ?>"></label>
                <label>Remind <input type="datetime-local" name="remind_at" data-field="remind_at" value="<?= esc((string) old('remind_at')) ?>"></label>
            </div>
            <label>Owner
                <select name="owner_id" data-field="owner_id">
                    <?php foreach ($members as $member) : ?>
                        <option value="<?= (int) $member['user_id'] ?>" <?= (int) (old('owner_id') ?: session()->get('user_id')) === (int) $member['user_id'] ? 'selected' : '' ?>><?= esc($member['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Notes <textarea name="notes" data-field="notes"><?= esc((string) old('notes')) ?></textarea></label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('square-check') ?> Save task</button>
        </footer>
    </form>
</dialog>
<?= $this->endSection() ?>
