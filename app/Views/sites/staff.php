<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
    $failed = old('form') === 'staff';
    $userId = (int) old('user_id');
    $action = $userId > 0 ? site_url('app/staff/' . $userId) : site_url('app/staff/0');
    $pick = static function (string $field) use ($failed): string {
        return $failed ? (string) old($field) : '';
    };
?>
<header class="page-head">
    <div>
        <p class="eyebrow">People</p>
        <h1>Staff</h1>
        <p class="lede">Designation, phone, and whether someone can be assigned. Invite new people from Team.</p>
    </div>
</header>
<?php if ($staff === []) : ?>
    <section class="panel"><div class="empty"><?= icon('inbox') ?><p>No one is on this workspace yet.</p></div></section>
<?php else : ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Name</th><th>Role</th><th>Designation</th><th>Phone</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($staff as $person) :
                $status = $person['status'] ?: 'active';
            ?>
                <tr>
                    <td>
                        <strong><?= esc($person['name']) ?></strong>
                        <div class="fine"><?= esc($person['email']) ?></div>
                    </td>
                    <td><?= esc($person['role_name']) ?></td>
                    <td><?= esc(person_label('', (int) $person['user_id'], $designationByUser ?? [])) ?></td>
                    <td><?= esc($person['phone'] ?: '—') ?></td>
                    <td><span class="badge <?= $status === 'active' ? '' : 'mute' ?>"><?= esc(ucfirst($status)) ?></span></td>
                    <td class="actions-cell">
                        <?php if (in_array('staff.update', $can, true)) : ?>
                            <button class="ghost" type="button" data-modal="staff-modal" data-action="<?= site_url('app/staff/' . $person['user_id']) ?>" data-title="Edit <?= esc($person['name'], 'attr') ?>" data-user_id="<?= (int) $person['user_id'] ?>" data-designation="<?= esc((string) $person['designation'], 'attr') ?>" data-phone="<?= esc((string) $person['phone'], 'attr') ?>" data-status="<?= esc($status, 'attr') ?>" data-employee_code="<?= esc((string) ($person['employee_code'] ?? ''), 'attr') ?>" data-joining_on="<?= esc((string) ($person['joining_on'] ?? ''), 'attr') ?>" data-address="<?= esc((string) ($person['address'] ?? ''), 'attr') ?>" data-relieved_on="<?= esc((string) ($person['relieved_on'] ?? ''), 'attr') ?>" data-team_id="<?= (int) ($person['team_id'] ?? 0) ?>" data-attendance_applicable="<?= (int) ($person['attendance_applicable'] ?? 1) ?>" data-lead_eligible="<?= (int) ($person['lead_eligible'] ?? 1) ?>" data-incentive_eligible="<?= (int) ($person['incentive_eligible'] ?? 1) ?>"><?= icon('square-pen') ?> Edit</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php if (in_array('staff.update', $can, true)) : ?>
<dialog id="staff-modal" class="modal" <?= ! empty($open) ? 'data-open="1"' : '' ?>>
    <form method="post" action="<?= $action ?>" data-modal-form class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="staff">
        <input type="hidden" name="user_id" data-field="user_id" value="<?= $userId ?: '' ?>">
        <header class="modal-head">
            <h2 data-modal-title>Edit staff</h2>
            <button class="icon-button" type="button" data-close aria-label="Close"><?= icon('x') ?></button>
        </header>
        <div class="modal-body stack">
            <div class="split">
                <label>Designation
                    <select name="designation" data-field="designation">
                        <option value="">None</option>
                        <?php
                            $designationChoices = $designations ?? [];
                            $knownDesignations = array_column($designationChoices, 'code');
                            foreach ($staff as $member) {
                                $code = (string) ($member['designation'] ?? '');
                                if ($code !== '' && ! in_array($code, $knownDesignations, true)) {
                                    $designationChoices[] = ['code' => $code, 'label' => $code];
                                    $knownDesignations[] = $code;
                                }
                            }
                        ?>
                        <?php foreach ($designationChoices as $item) : ?>
                            <option value="<?= esc($item['code'], 'attr') ?>" <?= $pick('designation') === $item['code'] ? 'selected' : '' ?>><?= esc($item['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Employee ID <input name="employee_code" data-field="employee_code" value="<?= esc($pick('employee_code')) ?>"></label>
            </div>
            <div class="split">
                <label>Phone <input name="phone" data-field="phone" value="<?= esc($pick('phone')) ?>"></label>
                <label>Joining date <input type="date" name="joining_on" data-field="joining_on" value="<?= esc($pick('joining_on')) ?>"></label>
            </div>
            <label>Address <input name="address" data-field="address" value="<?= esc($pick('address')) ?>"></label>
            <div class="split">
                <label>Team
                    <select name="team_id" data-field="team_id">
                        <option value="">None</option>
                        <?php foreach ($teams ?? [] as $team) : ?>
                            <option value="<?= (int) $team['id'] ?>" <?= $pick('team_id') === (string) $team['id'] ? 'selected' : '' ?>><?= esc($team['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Status
                    <select name="status" data-field="status">
                        <?php foreach (['invited' => 'Invited', 'active' => 'Active', 'inactive' => 'Inactive', 'relieved' => 'Relieved'] as $value => $label) : ?>
                            <option value="<?= $value ?>" <?= $pick('status') === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label>Relieving date <input type="date" name="relieved_on" data-field="relieved_on" value="<?= esc($pick('relieved_on')) ?>"></label>
            <label class="check"><input type="checkbox" name="attendance_applicable" value="1" data-field="attendance_applicable"> <span>Attendance applies</span></label>
            <label class="check"><input type="checkbox" name="lead_eligible" value="1" data-field="lead_eligible"> <span>Eligible for leads</span></label>
            <label class="check"><input type="checkbox" name="incentive_eligible" value="1" data-field="incentive_eligible"> <span>Eligible for incentive</span></label>
        </div>
        <footer class="modal-foot">
            <button class="ghost" type="button" data-close>Cancel</button>
            <button class="primary" type="submit"><?= icon('briefcase') ?> Save profile</button>
        </footer>
    </form>
</dialog>
<?php endif; ?>
<?= $this->endSection() ?>
