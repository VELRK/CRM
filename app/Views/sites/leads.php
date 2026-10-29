<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $failed   = old('form') === 'lead';
    $recordId = (int) (old('record_id') ?: ($editing['id'] ?? 0));
    $action   = $recordId > 0 ? site_url('app/leads/' . $recordId) : site_url('app/leads');
    $pick = static function (string $field, string $fallback = '') use ($editing, $failed): string {
        if ($failed) {
            return (string) old($field);
        }
        if (is_array($editing) && array_key_exists($field, $editing) && $editing[$field] !== null) {
            return (string) $editing[$field];
        }
        return $fallback;
    };
    $labels = [
        'new' => 'New', 'contacted' => 'Contacted', 'followup' => 'Follow-up',
        'site_visit' => 'Site visit scheduled', 'site_visit_done' => 'Site visit done',
        'negotiation' => 'Negotiation', 'booked' => 'Booked', 'won' => 'Won', 'lost' => 'Lost', 'not_interested' => 'Not interested',
    ];
    foreach ($statuses ?? [] as $item) {
        $labels[$item['code']] = $item['label'];
    }
    $json = static function (array $value): string {
        return esc((string) json_encode($value, JSON_UNESCAPED_UNICODE), 'attr');
    };
    $followLead = (int) old('lead_id');
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Sites</p>
        <h1>Leads</h1>
    </div>
    <?php if (in_array('leads.create', $can, true)) : ?>
        <button class="primary" type="button" data-modal="lead-modal" data-action="<?= site_url('app/leads') ?>" data-title="New lead" data-record_id="" data-name="" data-phone="" data-phone_secondary="" data-email="" data-location="" data-budget="" data-source="" data-team_id="" data-project_id="" data-plot_id="" data-owner_id="<?= (int) session()->get('user_id') ?>" data-status="new" data-notes="" data-remarks=""><?= icon('plus') ?> New lead</button>
    <?php endif; ?>
    <?php if (in_array('exports.download', $can, true)) : ?>
        <a class="ghost" href="<?= site_url('app/leads/export') . ($query !== '' ? '?q=' . rawurlencode($query) : '') ?>"><?= icon('file-text') ?> Download</a>
    <?php endif; ?>
</header>
<form class="search" method="get" action="<?= site_url('app/leads') ?>">
    <?= icon('search') ?>
    <input name="q" value="<?= esc($query) ?>" placeholder="Search name, phone, or email">
</form>
<?php if ($leads === []) : ?>
    <section class="panel"><div class="empty"><?= icon('inbox') ?><p><?= $query === '' ? 'No leads yet.' : 'No leads match that search.' ?></p></div></section>
<?php else : ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Lead</th><th>Project</th><th>Owner</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($leads as $lead) :
                $plotLabel = trim((string) ($lead['plot_no'] ?? '') . (($lead['block'] ?? '') !== '' ? ' / ' . $lead['block'] : ''));
            ?>
                <tr>
                    <td>
                        <strong><?= esc($lead['name']) ?></strong>
                        <div class="fine"><?= esc($lead['phone']) ?><?= $lead['source'] ? ' · ' . esc($lead['source']) : '' ?></div>
                    </td>
                    <td><?= esc($lead['project_name'] ?: '—') ?><?= $plotLabel !== '' ? ' · ' . esc($plotLabel) : '' ?></td>
                    <td><?= esc($lead['owner_name'] ? person_label((string) $lead['owner_name'], (int) $lead['owner_id'], $designationByUser ?? []) : '—') ?></td>
                    <td><span class="badge <?= in_array($lead['status'], ['won', 'lost'], true) ? 'mute' : '' ?>"><?= esc($labels[$lead['status']] ?? $lead['status']) ?></span></td>
                    <td class="actions-cell wrap">
                        <?php if (in_array('leads.update', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="lead-modal" data-action="<?= site_url('app/leads/' . $lead['id']) ?>" data-title="Edit lead" data-record_id="<?= (int) $lead['id'] ?>" data-name="<?= esc($lead['name'], 'attr') ?>" data-phone="<?= esc($lead['phone'], 'attr') ?>" data-phone_secondary="<?= esc((string) ($lead['phone_secondary'] ?? ''), 'attr') ?>" data-email="<?= esc((string) $lead['email'], 'attr') ?>" data-location="<?= esc((string) ($lead['location'] ?? ''), 'attr') ?>" data-budget="<?= isset($lead['budget_minor']) && $lead['budget_minor'] !== null ? esc((string) (((int) $lead['budget_minor']) / 100), 'attr') : '' ?>" data-source="<?= esc((string) $lead['source'], 'attr') ?>" data-team_id="<?= (int) ($lead['team_id'] ?? 0) ?: '' ?>" data-project_id="<?= (int) $lead['project_id'] ?: '' ?>" data-plot_id="<?= (int) $lead['plot_id'] ?: '' ?>" data-owner_id="<?= (int) $lead['owner_id'] ?: '' ?>" data-status="<?= esc($lead['status'], 'attr') ?>" data-notes="<?= esc((string) $lead['notes'], 'attr') ?>" data-remarks="<?= esc((string) ($lead['remarks'] ?? ''), 'attr') ?>"><?= icon('square-pen') ?></button>
                        <?php endif; ?>
                        <?php if (in_array('followups.create', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="followup-modal" data-action="<?= site_url('app/leads/' . $lead['id'] . '/followups') ?>" data-title="Follow-up · <?= esc($lead['name'], 'attr') ?>" data-lead_id="<?= (int) $lead['id'] ?>" data-body="" data-next_at=""><?= icon('clipboard-list') ?></button>
                        <?php endif; ?>
                        <?php if (in_array('leads.transfer', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="transfer-modal" data-action="<?= site_url('app/leads/' . $lead['id'] . '/transfer') ?>" data-title="Transfer · <?= esc($lead['name'], 'attr') ?>" data-lead_id="<?= (int) $lead['id'] ?>" data-owner_id="<?= (int) $lead['owner_id'] ?>" data-note=""><?= icon('history') ?></button>
                        <?php endif; ?>
                        <?php if (in_array('visits.create', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="visit-modal" data-action="<?= site_url('app/leads/' . $lead['id'] . '/visits') ?>" data-title="Site visit · <?= esc($lead['name'], 'attr') ?>" data-lead_id="<?= (int) $lead['id'] ?>" data-staff_id="" data-scheduled_at="" data-project_id="<?= (int) $lead['project_id'] ?: '' ?>" data-plot_id="<?= (int) $lead['plot_id'] ?: '' ?>" data-note=""><?= icon('map-pin') ?></button>
                        <?php endif; ?>
                        <button class="ghost" type="button" data-modal="history-modal" data-title="History · <?= esc($lead['name'], 'attr') ?>" data-history="<?= $json($lead['history']) ?>"><?= icon('scroll-text') ?></button>
                        <?php if (in_array('leads.delete', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="confirm-delete" data-action="<?= site_url('app/leads/' . $lead['id'] . '/delete') ?>" data-title="Remove <?= esc($lead['name'], 'attr') ?>?"><?= icon('trash-2') ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if (in_array('leads.create', $can, true) || in_array('leads.update', $can, true)) : ?>
<dialog id="lead-modal" class="modal" <?= $open === 'lead' ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= $action ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="lead">
        <input type="hidden" name="record_id" data-field="record_id" value="<?= $recordId ?: '' ?>">
        <header class="modal-head">
            <h2 data-modal-title><?= $recordId ? 'Edit lead' : 'New lead' ?></h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <div class="split">
                <label>Name <input name="name" data-field="name" value="<?= esc($pick('name')) ?>" required></label>
                <label>Phone <input name="phone" data-field="phone" value="<?= esc($pick('phone')) ?>" required></label>
            </div>
            <div class="split">
                <label>Second phone <input name="phone_secondary" data-field="phone_secondary" value="<?= esc($pick('phone_secondary')) ?>"></label>
                <label>Email <input type="email" name="email" data-field="email" value="<?= esc($pick('email')) ?>"></label>
            </div>
            <div class="split">
                <label>Location <input name="location" data-field="location" value="<?= esc($pick('location')) ?>"></label>
                <label>Budget (₹) <input name="budget" data-field="budget" inputmode="decimal" value="<?= esc($pick('budget')) ?>"></label>
            </div>
            <div class="split">
                <label>Source
                    <?php if (($sources ?? []) === []) : ?>
                        <input name="source" data-field="source" value="<?= esc($pick('source')) ?>">
                    <?php else : ?>
                        <select name="source" data-field="source">
                            <?php
                                $knownSources = array_column($sources, 'code');
                                if ($pick('source') !== '' && ! in_array($pick('source'), $knownSources, true)) :
                            ?>
                                <option value="<?= esc($pick('source'), 'attr') ?>" selected><?= esc($pick('source')) ?></option>
                            <?php endif; ?>
                            <?php foreach ($sources as $source) : ?>
                                <option value="<?= esc($source['code'], 'attr') ?>" <?= $pick('source') === $source['code'] ? 'selected' : '' ?>><?= esc($source['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </label>
                <label>Team
                    <select name="team_id" data-field="team_id">
                        <option value="">None</option>
                        <?php foreach ($teams ?? [] as $team) : ?>
                            <option value="<?= (int) $team['id'] ?>" <?= (string) $pick('team_id') === (string) $team['id'] ? 'selected' : '' ?>><?= esc($team['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="split">
                <label>Project
                    <select name="project_id" data-field="project_id" data-project-filter>
                        <option value="">None</option>
                        <?php foreach ($projects as $project) : ?>
                            <option value="<?= (int) $project['id'] ?>" <?= (string) $pick('project_id') === (string) $project['id'] ? 'selected' : '' ?>><?= esc($project['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Plot
                    <select name="plot_id" data-field="plot_id" data-plot-options>
                        <option value="">None</option>
                        <?php foreach ($plots as $plot) : ?>
                            <option value="<?= (int) $plot['id'] ?>" data-project="<?= (int) $plot['project_id'] ?>" <?= (string) $pick('plot_id') === (string) $plot['id'] ? 'selected' : '' ?>><?= esc($plot['project_name'] . ' · ' . $plot['plot_no'] . ($plot['block'] !== '' ? ' / ' . $plot['block'] : '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="split">
                <label>Owner
                    <select name="owner_id" data-field="owner_id">
                        <option value="">Unassigned</option>
                        <?php foreach ($roster as $person) : ?>
                            <option value="<?= (int) $person['user_id'] ?>" <?= (string) $pick('owner_id') === (string) $person['user_id'] ? 'selected' : '' ?>><?= esc(person_label((string) $person['name'], (int) $person['user_id'], $designationByUser ?? [])) ?><?= ($person['status'] ?? 'active') === 'inactive' ? ' (inactive)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Status
                    <select name="status" data-field="status">
                        <?php foreach ($labels as $value => $label) : ?>
                            <option value="<?= $value ?>" <?= $pick('status', 'new') === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>Notes <textarea name="notes" data-field="notes"><?= esc($pick('notes')) ?></textarea></label>
            <label>Remarks <textarea name="remarks" data-field="remarks"><?= esc($pick('remarks')) ?></textarea></label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('contact') ?> Save lead</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>

<?php if (in_array('followups.create', $can, true)) : ?>
<dialog id="followup-modal" class="modal" <?= $open === 'followup' ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= $followLead > 0 ? site_url('app/leads/' . $followLead . '/followups') : site_url('app/leads') ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="followup">
        <input type="hidden" name="lead_id" data-field="lead_id" value="<?= $followLead ?: '' ?>">
        <header class="modal-head">
            <h2 data-modal-title>Follow-up</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>What happened <textarea name="body" data-field="body" required><?= esc((string) old('body')) ?></textarea></label>
            <label>Next follow-up <input type="datetime-local" name="next_at" data-field="next_at" value="<?= esc((string) old('next_at')) ?>"></label>
            <p class="fine">The next time is only a reminder you set. It does not change the lead.</p>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('clipboard-list') ?> Log follow-up</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>

<?php if (in_array('leads.transfer', $can, true)) : ?>
<dialog id="transfer-modal" class="modal" <?= $open === 'transfer' ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= $followLead > 0 ? site_url('app/leads/' . $followLead . '/transfer') : site_url('app/leads') ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="transfer">
        <input type="hidden" name="lead_id" data-field="lead_id" value="<?= old('form') === 'transfer' ? $followLead : '' ?>">
        <header class="modal-head">
            <h2 data-modal-title>Transfer lead</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <label>Transfer to
                <select name="to_user_id" data-hide-owner required>
                    <option value="">Choose</option>
                    <?php foreach ($staff as $person) : ?>
                        <option value="<?= (int) $person['user_id'] ?>" <?= (string) old('to_user_id') === (string) $person['user_id'] ? 'selected' : '' ?>><?= esc(person_label((string) $person['name'], (int) $person['user_id'], $designationByUser ?? [])) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Note <input name="note" data-field="note" value="<?= esc(old('form') === 'transfer' ? (string) old('note') : '') ?>"></label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('history') ?> Transfer</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>

<?php if (in_array('visits.create', $can, true)) : ?>
<dialog id="visit-modal" class="modal" <?= $open === 'visit' ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= $followLead > 0 ? site_url('app/leads/' . $followLead . '/visits') : site_url('app/leads') ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="visit">
        <input type="hidden" name="lead_id" data-field="lead_id" value="<?= old('form') === 'visit' ? $followLead : '' ?>">
        <header class="modal-head">
            <h2 data-modal-title>Schedule site visit</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <div class="split">
                <label>Staff
                    <select name="staff_id" data-field="staff_id" required>
                        <option value="">Choose</option>
                        <?php foreach ($staff as $person) : ?>
                            <option value="<?= (int) $person['user_id'] ?>" <?= (string) old('staff_id') === (string) $person['user_id'] ? 'selected' : '' ?>><?= esc(person_label((string) $person['name'], (int) $person['user_id'], $designationByUser ?? [])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>When <input type="datetime-local" name="scheduled_at" data-field="scheduled_at" value="<?= esc((string) old('scheduled_at')) ?>" required></label>
            </div>
            <div class="split">
                <label>Project
                    <select name="project_id" data-field="project_id" data-project-filter>
                        <option value="">From the lead</option>
                        <?php foreach ($projects as $project) : ?>
                            <option value="<?= (int) $project['id'] ?>" <?= (string) old('project_id') === (string) $project['id'] ? 'selected' : '' ?>><?= esc($project['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Plot
                    <select name="plot_id" data-field="plot_id" data-plot-options>
                        <option value="">From the lead</option>
                        <?php foreach ($plots as $plot) : ?>
                            <option value="<?= (int) $plot['id'] ?>" data-project="<?= (int) $plot['project_id'] ?>" <?= (string) old('plot_id') === (string) $plot['id'] ? 'selected' : '' ?>><?= esc($plot['plot_no']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>Note <input name="note" data-field="note" value="<?= esc(old('form') === 'visit' ? (string) old('note') : '') ?>"></label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('map-pin') ?> Schedule</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>

<dialog id="history-modal" class="modal">
    <header class="modal-head">
        <h2 data-modal-title>History</h2>
        <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
    </header>
    <div class="modal-body" data-history-list></div>
    <footer class="modal-foot">
        <button class="ghost" type="button" data-close>Close</button>
    </footer>
</dialog>
<?= $this->endSection() ?>
