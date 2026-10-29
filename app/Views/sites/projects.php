<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $failed   = old('form') === 'project';
    $recordId = (int) (old('record_id') ?: ($editing['id'] ?? 0));
    $action   = $recordId > 0 ? site_url('app/projects/' . $recordId) : site_url('app/projects');
    $pick = static function (string $field, string $fallback = '') use ($editing, $failed): string {
        if ($failed) {
            return (string) old($field);
        }
        if (is_array($editing) && array_key_exists($field, $editing) && $editing[$field] !== null) {
            return (string) $editing[$field];
        }
        return $fallback;
    };
    $labels = ['upcoming' => 'Upcoming', 'ongoing' => 'Ongoing', 'completed' => 'Completed'];
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Sites</p>
        <h1>Projects</h1>
    </div>
    <?php if (in_array('projects.create', $can, true)) : ?>
        <button class="primary" type="button" data-modal="project-modal" data-action="<?= site_url('app/projects') ?>" data-title="New project" data-record_id="" data-name="" data-location="" data-status="upcoming" data-project_type="" data-total_plots="" data-launch_on="" data-notes=""><?= icon('plus') ?> New project</button>
    <?php endif; ?>
</header>
<?php if ($projects === []) : ?>
    <section class="panel"><div class="empty"><?= icon('inbox') ?><p>No projects yet.</p></div></section>
<?php else : ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Project</th><th>Location</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($projects as $project) : ?>
                <tr>
                    <td><strong><?= esc($project['name']) ?></strong></td>
                    <td><?= esc($project['location'] ?: '—') ?></td>
                    <td><span class="badge <?= $project['status'] === 'active' ? '' : 'mute' ?>"><?= esc($labels[$project['status']] ?? $project['status']) ?></span></td>
                    <td class="actions-cell">
                        <?php if (in_array('projects.update', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="project-modal" data-action="<?= site_url('app/projects/' . $project['id']) ?>" data-title="Edit project" data-record_id="<?= (int) $project['id'] ?>" data-name="<?= esc($project['name'], 'attr') ?>" data-location="<?= esc((string) $project['location'], 'attr') ?>" data-status="<?= esc($project['status'], 'attr') ?>" data-project_type="<?= esc((string) ($project['project_type'] ?? ''), 'attr') ?>" data-total_plots="<?= esc((string) ($project['total_plots'] ?? ''), 'attr') ?>" data-launch_on="<?= esc((string) ($project['launch_on'] ?? ''), 'attr') ?>" data-notes="<?= esc((string) $project['notes'], 'attr') ?>"><?= icon('square-pen') ?> Edit</button>
                        <?php endif; ?>
                        <?php if (in_array('projects.delete', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="confirm-delete" data-action="<?= site_url('app/projects/' . $project['id'] . '/delete') ?>" data-title="Remove this project?"><?= icon('trash-2') ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php if (in_array('projects.create', $can, true) || in_array('projects.update', $can, true)) : ?>
<dialog id="project-modal" class="modal" <?= ! empty($open) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= $action ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="project">
        <input type="hidden" name="record_id" data-field="record_id" value="<?= $recordId ?: '' ?>">
        <header class="modal-head">
            <h2 data-modal-title><?= $recordId ? 'Edit project' : 'New project' ?></h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>Name <input name="name" data-field="name" value="<?= esc($pick('name')) ?>" required></label>
            <label>Location <input name="location" data-field="location" value="<?= esc($pick('location')) ?>"></label>
            <div class="split">
                <label>Type <input name="project_type" data-field="project_type" value="<?= esc($pick('project_type')) ?>" placeholder="Plotted layout"></label>
                <label>Total plots <input name="total_plots" data-field="total_plots" inputmode="numeric" value="<?= esc($pick('total_plots')) ?>"></label>
            </div>
            <div class="split">
                <label>Launch date <input type="date" name="launch_on" data-field="launch_on" value="<?= esc($pick('launch_on')) ?>"></label>
                <label>Status
                    <select name="status" data-field="status">
                        <?php foreach ($labels as $value => $label) : ?>
                            <option value="<?= $value ?>" <?= $pick('status', 'upcoming') === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>Notes <textarea name="notes" data-field="notes"><?= esc($pick('notes')) ?></textarea></label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('land-plot') ?> Save project</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
<?= $this->endSection() ?>
