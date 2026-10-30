import React, { useEffect, useMemo, useRef, useState } from 'https://esm.sh/react@18.3.1';
import { createRoot } from 'https://esm.sh/react-dom@18.3.1/client';
import { createPortal } from 'https://esm.sh/react-dom@18.3.1';
import htm from 'https://esm.sh/htm@3.1.1';

const html = htm.bind(React.createElement);
const tokenKey = 'atrium-token';
const appRoot = new URL('..', import.meta.url).pathname.replace(/\/$/, '');

function apiPath(path) {
    return typeof path === 'string' && path.startsWith('/') ? appRoot + path : path;
}

const nav = [
    ['Menu', [['CRM', 'home'], ['Reports', 'reports']]],
    ['People', [['Staff', 'staff'], ['Roles', 'roles'], ['Teams', 'teams']]],
    ['Sites', [['Projects', 'projects']]],
    ['Sales', [['Leads', 'leads'], ['Follow-ups', 'followups'], ['Visits', 'visits'], ['Tasks', 'tasks']]],
    ['Office', [['Attendance', 'attendance'], ['Incentives', 'incentives']]],
    ['Insight', [['Activity', 'activity'], ['Notifications', 'notifications'], ['WhatsApp', 'whatsapp'], ['API console', 'api']]],
];
const modules = nav.flatMap((group) => group[1]);

const col = (label, key) => ({ label, key });
const lists = {
    settings: { path: '/api/v1/company', kind: 'company', lede: 'Company name, email, address, and logo.', columns: [] },
    staff: { path: '/api/v1/staff', lede: 'People in this workspace. The company is the one you are signed into.', columns: [col('Name', 'name'), col('Email', 'email'), col('Phone', 'phone_number'), col('Role', 'role_name'), col('Profile', 'profile'), col('Status', 'status')], actions: ['user', 'staff-edit'] },
    roles: { path: '/api/v1/roles', lede: 'Roles for this workspace. Access is ticked on the role.', columns: [col('Role', 'name'), col('People', 'member_count'), col('Access', 'access_count')], actions: ['role', 'role-delete'] },
    teams: { path: '/api/v1/teams', lede: 'Teams, who leads them, and which projects they work.', columns: [col('Team', 'name'), col('Head', 'head_name'), col('Projects', 'projects'), col('Members', 'members')], actions: ['team', 'team-delete'] },
    projects: { path: '/api/v1/projects', kind: 'sites', start: 'dashboard', lede: '', columns: [] },
    plots: { path: '/api/v1/plots', kind: 'sites', start: 'inventory', lede: '', columns: [] },
    leads: { path: '/api/v1/leads', kind: 'leads', lede: '', columns: [], actions: ['lead', 'followup', 'visit', 'task', 'assign'] },
    followups: { path: '/api/v1/followups', kind: 'calls', lede: '', columns: [], actions: ['followup'] },
    visits: { path: '/api/v1/visits', kind: 'visits', lede: '', columns: [], actions: ['visit'] },
    tasks: { path: '/api/v1/tasks', kind: 'tasks', lede: '', columns: [], actions: ['task'] },
    attendance: { path: '/api/v1/attendance', kind: 'attendance', lede: '', columns: [] },
    incentives: { path: '/api/v1/incentives', lede: 'Commission rows and what is still pending.', columns: [col('User', 'user_name'), col('Project', 'project_name'), col('Plot', 'plot_no'), col('Status', 'status'), col('Amount', 'amount_minor')] },
    activity: { path: '/api/v1/activity', lede: 'What changed in this workspace.', columns: [col('When', 'created_at'), col('Person', 'user_name'), col('Action', 'action'), col('Record', 'subject_type'), col('Id', 'subject_id'), col('Detail', 'detail')] },
    notifications: { path: '/api/v1/notifications', lede: 'Messages for this sign-in.', columns: [col('Title', 'title'), col('Message', 'body'), col('Read', 'read_at')] },
    whatsapp: { path: '/api/v1/whatsapp/templates', lede: 'Message templates stored for this workspace.', columns: [col('Name', 'name'), col('Language', 'language'), col('Message', 'body')], actions: ['template'] },
};

async function call(path, options = {}) {
    const headers = { Accept: 'application/json' };
    const token = localStorage.getItem(tokenKey);
    if (token) headers.Authorization = 'Bearer ' + token;
    if (options.body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }
    const response = await fetch(apiPath(path), {
        method: options.method || 'GET',
        headers,
        body: options.body === undefined ? undefined : JSON.stringify(options.body),
    });
    const text = await response.text();
    let payload = {};
    try { payload = text ? JSON.parse(text) : {}; } catch { payload = { raw: text }; }
    if (!response.ok || payload.ok === false) {
        throw new Error(payload.error || ('Request failed (' + response.status + ')'));
    }
    return payload.data !== undefined ? payload.data : payload;
}
function useCompanyLogo(stamp) {
    const [url, setUrl] = useState('');
    useEffect(() => {
        if (!stamp) { setUrl(''); return undefined; }
        let objectUrl = '';
        let gone = false;
        const token = localStorage.getItem(tokenKey);
        fetch(apiPath('/api/v1/company/logo'), { headers: token ? { Authorization: 'Bearer ' + token } : {} })
            .then((response) => (response.ok ? response.blob() : null))
            .then((blob) => {
                if (gone || !blob) return;
                objectUrl = URL.createObjectURL(blob);
                setUrl(objectUrl);
            })
            .catch(() => {});
        return () => { gone = true; if (objectUrl) URL.revokeObjectURL(objectUrl); };
    }, [stamp]);
    return url;
}
function brandIcon() {
    return html`<span class="brand-mark" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V9l8-5 8 5v11"/><path d="M9 20v-6h6v6"/></svg></span>`;
}
function companyMark(company, logoUrl) {
    if (!company) return null;
    return html`<div class="company-strip">
        ${logoUrl ? html`<img src=${logoUrl} alt="" />` : brandIcon()}
        <div><strong>${company.companyName || 'Company'}</strong><p>${[company.companyAdminEmail, company.phone, company.address].filter(Boolean).join(' · ') || 'Set the name, email, address, and logo in Settings.'}</p></div>
    </div>`;
}

function SetPassword({ token, onDone }) {
    const [password, setPassword] = useState('');
    const [again, setAgain] = useState('');
    const [error, setError] = useState('');
    async function submit(event) {
        event.preventDefault();
        setError('');
        if (password.length < 8) { setError('Use a password of at least 8 characters.'); return; }
        if (password !== again) { setError('The two passwords do not match.'); return; }
        try {
            await call('/api/v1/auth/password', { method: 'POST', body: { token, password } });
            onDone();
        } catch (e) { setError(e.message); }
    }
    return html`<main class="login-split">
        <section class="login-hero">
            <p>Atrium</p>
            <h1>Create your password</h1>
            <p>This link replaces the temporary password. Use it once, then sign in.</p>
        </section>
        <section class="login"><form class="card stack" onSubmit=${submit}>
            <div><h1>Set password</h1><p class="lede">Choose the password you will use to sign in.</p></div>
            <label>New password <input type="password" value=${password} onInput=${(e) => setPassword(e.target.value)} required minlength="8" autocomplete="new-password" /></label>
            <label>Confirm password <input type="password" value=${again} onInput=${(e) => setAgain(e.target.value)} required minlength="8" autocomplete="new-password" /></label>
            ${error ? html`<p class="error">${error}</p>` : null}
            <button class="primary" type="submit">Save password</button>
        </form></section>
    </main>`;
}
function ChangePassword({ onClose }) {
    const [form, setForm] = useState({ current_password: '', password: '', again: '' });
    const [error, setError] = useState('');
    async function submit(event) {
        event.preventDefault();
        setError('');
        if (form.password.length < 8) { setError('Use a password of at least 8 characters.'); return; }
        if (form.password !== form.again) { setError('The two passwords do not match.'); return; }
        try {
            await call('/api/v1/me/password', { method: 'POST', body: { current_password: form.current_password, password: form.password } });
            onClose();
        } catch (e) { setError(e.message); }
    }
    return html`<${FormCard} title="Change password" error=${error} onSubmit=${submit} onClose=${onClose}>
        ${field('Current password', inputOf(form, 'current_password', setForm, 'password'), true)}
        ${field('New password', inputOf(form, 'password', setForm, 'password'))}
        ${field('Confirm password', inputOf(form, 'again', setForm, 'password'))}
    <//>`;
}
function Login({ onIn }) {
    const [email, setEmail] = useState('priya@example.com');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    async function submit(event) {
        event.preventDefault();
        setError('');
        try {
            const data = await call('/api/v1/auth/login', { method: 'POST', body: { email, password, device: 'React desk' } });
            localStorage.setItem(tokenKey, data.token);
            onIn(await call('/api/v1/me'));
        } catch (e) {
            setError(e.message);
        }
    }
    return html`<main class="login-split">
        <section class="login-hero">
            <p>Atrium</p>
            <h1>Welcome back</h1>
            <p>Pipeline, plots, visits and follow-ups for this workspace.</p>
        </section>
        <section class="login"><form class="card stack" onSubmit=${submit}>
            <div><h1>Sign in</h1><p class="lede">Use the same account the mobile app will use.</p></div>
            <label>Email <input type="email" value=${email} onInput=${(e) => setEmail(e.target.value)} required /></label>
            <label>Password <input type="password" value=${password} onInput=${(e) => setPassword(e.target.value)} required /></label>
            ${error ? html`<p class="error">${error}</p>` : null}
            <button class="primary" type="submit">Sign in</button>
        </form></section>
    </main>`;
}

function cell(column, row) {
    const value = row[column.key];
    if (Array.isArray(value)) return value.join(', ');
    if (value === null || value === undefined || value === '') return '—';
    if (column.key.endsWith('_minor')) return money(value);
    return value;
}
function sheetValue(column, row) {
    const value = column.text ? column.text(row) : row[column.key];
    if (Array.isArray(value)) return value.join(', ');
    if (value === null || value === undefined || value === '') return '';
    return value;
}
function excelName(title) {
    return String(title || 'export').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'export';
}
function downloadSheet(title, headers, body) {
    const esc = (value) => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    let xml = '<?xml version="1.0" encoding="UTF-8"?>\n<?mso-application progid="Excel.Sheet"?>\n';
    xml += '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
    xml += '<Worksheet ss:Name="' + esc(String(title || 'Sheet').slice(0, 31)) + '"><Table><Row>';
    headers.forEach((header) => { xml += '<Cell><Data ss:Type="String">' + esc(header) + '</Data></Cell>'; });
    xml += '</Row>';
    body.forEach((line) => {
        xml += '<Row>';
        line.forEach((value) => {
            const number = typeof value === 'number' && Number.isFinite(value);
            xml += '<Cell><Data ss:Type="' + (number ? 'Number' : 'String') + '">' + esc(value) + '</Data></Cell>';
        });
        xml += '</Row>';
    });
    xml += '</Table></Worksheet></Workbook>';
    const blob = new Blob([xml], { type: 'application/vnd.ms-excel' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = excelName(title) + '.xls';
    link.click();
    URL.revokeObjectURL(link.href);
}
function Grid({ title, note, columns, rows, query = '', filters = false, onRow, active, onEdit, onDelete }) {
    const [text, setText] = useState('');
    const [pick, setPick] = useState('');
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [min, setMin] = useState('');
    const facet = columns.find((column) => ['name', 'label', 'status'].includes(column.key));
    const numberColumn = ['sales', 'total', 'leads', 'bookings'].map((key) => columns.find((column) => column.key === key)).find(Boolean);
    const hasDay = columns.some((column) => column.key === 'day');
    const choices = facet ? [...new Set(rows.map((row) => String(row[facet.key] ?? '')).filter(Boolean))].sort((a, b) => a.localeCompare(b)) : [];
    const filtering = Boolean((filters ? text : query) || pick || from || to || min !== '');
    const indexed = rows.map((row, index) => ({ row, key: (row.id != null ? 'id-' + row.id : 'i-' + index) + '-' + index }));
    const shown = indexed.filter((item) => {
        if (!tableHit(item.row, filters ? text : query, columns)) return false;
        if (pick && facet && String(item.row[facet.key] ?? '') !== pick) return false;
        if (hasDay) {
            const day = String(item.row.day || '');
            if (from && day < from) return false;
            if (to && day > to) return false;
        }
        if (min !== '' && numberColumn && Number(item.row[numberColumn.key] || 0) < Number(min)) return false;
        return true;
    });
    const [selected, setSelected] = useState({});
    useEffect(() => { setSelected({}); }, [title, rows.length]);
    const chosen = indexed.filter((item) => selected[item.key]);
    const shownChosen = shown.filter((item) => selected[item.key]);
    const allOn = shown.length > 0 && shownChosen.length === shown.length;
    function boxRef(element) {
        if (element) element.indeterminate = shownChosen.length > 0 && !allOn;
    }
    function markAll(event) {
        const next = { ...selected };
        shown.forEach((item) => { next[item.key] = event.target.checked; });
        setSelected(next);
    }
    function mark(key, event) {
        event.stopPropagation();
        setSelected((current) => ({ ...current, [key]: event.target.checked }));
    }
    function exportSheet() {
        const source = shownChosen.length ? shownChosen : shown;
        downloadSheet(title, columns.map((column) => column.label), source.map((item) => columns.map((column) => sheetValue(column, item.row))));
    }
    function clearFilters() {
        setText('');
        setPick('');
        setFrom('');
        setTo('');
        setMin('');
    }
    return html`<article class="panel sheet">
        <header class="panel-head">
            <div><h2>${title}</h2>${note ? html`<p class="lede">${note}</p>` : null}</div>
            <div class="row-actions">
                <span class="muted">${shownChosen.length ? shownChosen.length + ' selected' : (filtering ? shown.length + ' of ' + rows.length : rows.length + ' rows')}</span>
                <button class="ghost" title=${shownChosen.length ? 'Export the selected rows' : 'Export the rows that match the filter'} onClick=${exportSheet}>Excel</button>
            </div>
        </header>
        ${filters ? html`<div class="filters">
            <input class="grow" placeholder="Search this report" value=${text} onInput=${(event) => setText(event.target.value)} />
            ${facet && choices.length ? html`<select value=${pick} onChange=${(event) => setPick(event.target.value)}><option value="">Any ${facet.label.toLowerCase()}</option>${choices.map((choice) => html`<option key=${choice} value=${choice}>${choice}</option>`)}</select>` : null}
            ${hasDay ? html`<input type="date" value=${from} aria-label="From" onInput=${(event) => setFrom(event.target.value)} />` : null}
            ${hasDay ? html`<input type="date" value=${to} aria-label="To" onInput=${(event) => setTo(event.target.value)} />` : null}
            ${numberColumn ? html`<input type="number" min="0" placeholder=${'Min ' + numberColumn.label.toLowerCase()} value=${min} onInput=${(event) => setMin(event.target.value)} />` : null}
            ${filtering ? html`<button class="ghost" type="button" onClick=${clearFilters}>Clear</button>` : null}
        </div>` : null}
        ${shownChosen.length ? html`<div class="bulk"><span>${shownChosen.length} selected for bulk action</span><button class="ghost" onClick=${exportSheet}>Export selected</button><button class="ghost" onClick=${() => setSelected({})}>Clear</button></div>` : null}
        <div class="table-card"><table class="grid-table">
            <thead><tr>
                <th class="check"><input ref=${boxRef} type="checkbox" checked=${allOn} aria-label="Select all" onChange=${markAll} /></th>
                ${columns.map((column) => html`<th key=${column.key}>${column.label}</th>`)}
                ${onEdit || onDelete ? html`<th></th>` : null}
            </tr></thead>
            <tbody>
                ${shown.length === 0 ? html`<tr><td colSpan=${columns.length + 1 + (onEdit || onDelete ? 1 : 0)}>${filtering ? 'Nothing matches this filter.' : 'Nothing here yet.'}</td></tr>` : shown.map((item) => html`<tr key=${item.key} class=${`${selected[item.key] ? 'picked' : ''} ${active === item.row ? 'active' : ''}`} onClick=${() => onRow && onRow(item.row)}>
                    <td class="check"><input type="checkbox" checked=${!!selected[item.key]} aria-label="Select row" onClick=${(event) => event.stopPropagation()} onChange=${(event) => mark(item.key, event)} /></td>
                    ${columns.map((column) => html`<td key=${column.key}>${column.text ? column.text(item.row) : cell(column, item.row)}</td>`)}
                    ${onEdit || onDelete ? html`<td class="row-actions" onClick=${(event) => event.stopPropagation()}>
                        ${onEdit ? html`<button class="ghost" type="button" onClick=${() => onEdit(item.row)}>Edit</button>` : null}
                        ${onDelete ? html`<button class="ghost" type="button" onClick=${() => onDelete(item.row)}>Delete</button>` : null}
                    </td>` : null}
                </tr>`)}
            </tbody>
        </table></div>
    </article>`;
}

function field(label, control, wide) {
    return html`<label class=${wide ? 'field span-2' : 'field'}><span>${label}</span>${control}</label>`;
}
function inputOf(form, key, setForm, type = 'text') {
    return html`<input type=${type} value=${form[key] || ''} onInput=${(e) => setForm({ ...form, [key]: e.target.value })} />`;
}
function selectOf(form, key, setForm, options) {
    return html`<select value=${form[key] || ''} onChange=${(e) => setForm({ ...form, [key]: e.target.value })}>
        ${options.map((option) => html`<option key=${option[0]} value=${option[0]}>${option[1]}</option>`)}
    </select>`;
}
function FormCard({ title, error, onSubmit, onClose, children }) {
    return html`<div class="modal-back" onClick=${onClose}>
        <form class="form-card modal-card" onClick=${(event) => event.stopPropagation()} onSubmit=${onSubmit}>
            <h2>${title}</h2>
            <div class="form-grid">${children}</div>
            ${error ? html`<p class="error">${error}</p>` : null}
            <div class="row-actions">
                <button class="primary" type="submit">Save</button>
                <button class="ghost" type="button" onClick=${onClose}>Cancel</button>
            </div>
        </form>
    </div>`;
}
function confirmDelete(label, error, onYes, onClose) {
    return html`<div class="modal-back" onClick=${onClose}>
        <form class="form-card modal-card confirm-card" onClick=${(event) => event.stopPropagation()} onSubmit=${onYes}>
            <h2>Delete ${label}?</h2>
            <p class="lede">This removes the record from the list.</p>
            ${error ? html`<p class="error">${error}</p>` : null}
            <div class="row-actions">
                <button class="danger" type="submit">Delete</button>
                <button class="ghost" type="button" onClick=${onClose}>Cancel</button>
            </div>
        </form>
    </div>`;
}
function xrow(label, control, required, top) {
    return html`<label class=${top ? 'xcrm-row top' : 'xcrm-row'}><span class=${required ? 'req' : ''}>${label}</span>${control}</label>`;
}
function sheet(title, error, onSubmit, onClose, children, narrow, action) {
    return html`<div class="modal-back" onClick=${onClose}>
        <form class=${narrow ? 'xcrm narrow' : 'xcrm'} onClick=${(event) => event.stopPropagation()} onSubmit=${onSubmit}>
            <h2>${title}</h2>
            ${children}
            ${error ? html`<p class="error">${error}</p>` : null}
            <div class="xcrm-actions">
                <button class="ghost" type="button" onClick=${onClose}>Cancel</button>
                <button class="primary" type="submit">${action || 'Save'}</button>
            </div>
        </form>
    </div>`;
}
function digits(value) {
    return String(value || '').replace(/\D+/g, '');
}
function leadFormState(lead) {
    if (!lead) return { clientNamePrefix: 'Mr.', assignedType: 'USER', leadStage: 'New', leadSource: '', clientBudgetType: 'Lakhs', intrestedProjects: [] };
    return {
        clientNamePrefix: lead.client_name_prefix || 'Mr.',
        clientFirstName: lead.client_first_name || '',
        clientLastName: lead.client_last_name || '',
        assignedType: lead.assigned_type || 'USER',
        assignedTo: String(lead.assigned_type === 'GROUP' ? (lead.group_id || lead.team_id || '') : (lead.assigned_to || lead.owner_id || '')),
        phoneNumber: lead.phone_number || lead.phone || '',
        alternatePhoneNumber: lead.alternate_phone_number || lead.phone_secondary || '',
        email: lead.email || '',
        leadStage: lead.lead_stage || 'New',
        leadSource: lead.lead_source || lead.source || '',
        campaignSource: lead.campaign_source || '',
        projectId: lead.project_id ? String(lead.project_id) : '',
        clientStreet: lead.client_street || '',
        clientCity: lead.client_city || '',
        clientState: lead.client_state || '',
        clientCountry: lead.client_country || '',
        clientZipCode: lead.client_zip_code || '',
        propertyType: lead.property_type || '',
        clientPreference: lead.client_preference || '',
        clientBudgetValue: lead.client_budget_value ? String(lead.client_budget_value) : '',
        clientBudgetType: lead.client_budget_type || 'Lakhs',
        location: lead.location || '',
        area: lead.area || '',
        description: lead.description || lead.notes || '',
        intrestedProjects: String(lead.interested_projects || '').split(',').map((item) => item.trim()).filter(Boolean),
    };
}
function LeadForm({ lead, onDone, onClose }) {
    const [form, setForm] = useState(() => leadFormState(lead));
    const [people, setPeople] = useState([]);
    const [teams, setTeams] = useState([]);
    const [projects, setProjects] = useState([]);
    const [error, setError] = useState('');
    useEffect(() => {
        call('/api/v1/staff').then((rows) => setPeople(Array.isArray(rows) ? rows : [])).catch(() => {});
        call('/api/v1/teams').then((rows) => setTeams(Array.isArray(rows) ? rows : [])).catch(() => {});
        call('/api/v1/projects').then((rows) => setProjects(Array.isArray(rows) ? rows : [])).catch(() => {});
    }, []);
    const assignees = form.assignedType === 'GROUP'
        ? teams.map((row) => [String(row.id), row.name])
        : people.map((row) => [String(row.user_id), row.name]);
    function setPhone(key, event) {
        setForm({ ...form, [key]: digits(event.target.value).slice(0, 10) });
    }
    async function submit(event) {
        event.preventDefault();
        if (!form.clientFirstName?.trim()) return setError('Please enter first name');
        if (!form.assignedTo) return setError('Please select assigned to');
        if (!form.leadStage) return setError('Please select lead stage');
        if (!digits(form.phoneNumber)) return setError('Please enter phone number');
        if (digits(form.phoneNumber).length !== 10) return setError('Phone number must be 10 digits');
        if (form.alternatePhoneNumber && digits(form.alternatePhoneNumber).length !== 10) return setError('Mobile number must be 10 digits');
        if (form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) return setError('Please enter a valid email address');
        setError('');
        try {
            const path = lead?.id ? '/api/v1/leads/' + lead.id : '/api/v1/leads';
            await call(path, { method: 'POST', body: { ...form, assignedTo: Number(form.assignedTo), projectId: Number(form.projectId || 0), clientBudgetValue: form.clientBudgetValue ? Number(form.clientBudgetValue) : 0 } });
            onDone();
        } catch (e) { setError(e.message); }
    }
    const stages = ['New', 'Qualified', 'Follow Up', 'Proposal Sent', 'Site Visit Scheduled', 'Negotiation', 'Booked', 'Lost'];
    const sources = ['Website', 'Facebook', 'Instagram', 'Email Campaign', 'Portal', 'Event', 'Referral', 'Walk-in', 'WhatsApp'];
    const properties = ['Plot', 'Villa/Bungalow', 'Farm Land', 'Apartment/Flat', 'Agricultural Land', 'Industrial Land', 'Other'];
    return sheet(lead?.id ? 'Edit Lead' : 'Add Lead', error, submit, onClose, html`
        <h3>Lead Information</h3>
        <div class="xcrm-grid">
            <div>
                ${xrow('Assigned type', selectOf(form, 'assignedType', setForm, [['USER', 'Person'], ['GROUP', 'Team']]), true)}
                ${xrow('First name', html`<div class="name-pair">${selectOf(form, 'clientNamePrefix', setForm, [['Mr.', 'Mr.'], ['Mrs.', 'Mrs.'], ['Ms.', 'Ms.'], ['Dr.', 'Dr.']])}${inputOf(form, 'clientFirstName', setForm)}</div>`, true)}
                ${xrow('Phone number', html`<input inputMode="numeric" maxlength="10" placeholder="Enter phone number" value=${form.phoneNumber || ''} onInput=${(event) => setPhone('phoneNumber', event)} />`, true)}
                ${xrow('Email', inputOf(form, 'email', setForm, 'email'))}
                ${xrow('Lead source', selectOf(form, 'leadSource', setForm, [['', 'Select lead source'], ...sources.map((item) => [item, item])]))}
                ${xrow('Project enquiry', selectOf(form, 'projectId', setForm, [['', 'Select project'], ...projects.map((row) => [String(row.id), row.name])]))}
            </div>
            <div>
                ${xrow('Assigned to', selectOf(form, 'assignedTo', setForm, [['', 'Select assigned to'], ...assignees]), true)}
                ${xrow('Last name', inputOf(form, 'clientLastName', setForm))}
                ${xrow('Mobile', html`<input inputMode="numeric" maxlength="10" placeholder="Enter mobile number" value=${form.alternatePhoneNumber || ''} onInput=${(event) => setPhone('alternatePhoneNumber', event)} />`)}
                ${xrow('Lead stage', selectOf(form, 'leadStage', setForm, stages.map((item) => [item, item])), true)}
                ${xrow('Campaign source', selectOf(form, 'campaignSource', setForm, [['', 'Select campaign source'], ...sources.map((item) => [item, item])]))}
            </div>
        </div>
        <h3>Address Information</h3>
        <div class="xcrm-grid">
            <div>
                ${xrow('Street', inputOf(form, 'clientStreet', setForm))}
                ${xrow('City', inputOf(form, 'clientCity', setForm))}
                ${xrow('Zip code', inputOf(form, 'clientZipCode', setForm))}
            </div>
            <div>
                ${xrow('State', inputOf(form, 'clientState', setForm))}
                ${xrow('Country', inputOf(form, 'clientCountry', setForm))}
            </div>
        </div>
        <h3>Lead Preference</h3>
        <div class="xcrm-grid">
            <div>
                ${xrow('Property type', selectOf(form, 'propertyType', setForm, [['', 'Property type'], ...properties.map((item) => [item, item])]))}
                ${xrow('Budget', html`<div class="name-pair">${inputOf(form, 'clientBudgetValue', setForm, 'number')}${selectOf(form, 'clientBudgetType', setForm, [['Lakhs', 'Lakhs'], ['Crores', 'Crores']])}</div>`)}
                ${xrow('Area', inputOf(form, 'area', setForm))}
            </div>
            <div>
                ${xrow('Preference', inputOf(form, 'clientPreference', setForm))}
                ${xrow('Location', inputOf(form, 'location', setForm))}
            </div>
        </div>
        <h3>Description</h3>
        ${xrow('Description', html`<textarea placeholder="Description" value=${form.description || ''} onInput=${(event) => setForm({ ...form, description: event.target.value })}></textarea>`, false, true)}
        <h3>Interested projects</h3>
        <div class="pick-list">
            ${projects.length === 0 ? html`<span>No projects yet</span>` : projects.map((row) => html`<label key=${row.id}><input type="checkbox" checked=${(form.intrestedProjects || []).includes(String(row.id))} onChange=${() => {
                const current = form.intrestedProjects || [];
                const id = String(row.id);
                setForm({ ...form, intrestedProjects: current.includes(id) ? current.filter((item) => item !== id) : [...current, id] });
            }} />${row.name}</label>`)}
        </div>
    `, false, lead?.id ? 'Save' : 'Add');
}
function FollowForm({ leadId, leadName, onDone, onClose }) {
    const locked = Boolean(leadId);
    const [form, setForm] = useState({ leadId: leadId ? String(leadId) : '', callPurpose: '' });
    const [leads, setLeads] = useState([]);
    const [people, setPeople] = useState([]);
    const [error, setError] = useState('');
    useEffect(() => {
        if (!locked) call('/api/v1/leads').then((rows) => setLeads(Array.isArray(rows) ? rows : [])).catch(() => {});
        call('/api/v1/staff').then((rows) => setPeople(Array.isArray(rows) ? rows : [])).catch(() => {});
    }, []);
    const purposes = ['Introduction', 'Follow up', 'Site visit reminder', 'Negotiation', 'Closure'];
    async function submit(event) {
        event.preventDefault();
        const chosen = locked ? String(leadId) : form.leadId;
        if (!chosen) return setError('Please select a lead');
        if (!form.callScheduledDateTime) return setError('Please choose the follow-up time');
        if (!form.assignedTo) return setError('Please select assigned to');
        if (!form.callSubject?.trim()) return setError('Please enter a subject');
        if (form.callSubject.trim().length > 30) return setError('Subject must be 30 characters or less');
        if (!form.callPurpose) return setError('Please select a follow-up purpose');
        setError('');
        try {
            await call('/api/v1/followups', { method: 'POST', body: { ...form, leadId: Number(chosen), assignedTo: Number(form.assignedTo), callDescription: form.callDescription || '' } });
            onDone();
        } catch (e) { setError(e.message); }
    }
    return sheet('Schedule a Follow-up', error, submit, onClose, html`
        <h3>Follow-up information</h3>
        ${locked ? xrow('Lead name', html`<input class="locked" value=${leadName || 'This lead'} readOnly />`) : xrow('Lead name', selectOf(form, 'leadId', setForm, [['', 'Lead name'], ...leads.map((row) => [String(row.id), row.name])]), true)}
        ${xrow('Follow-up time', inputOf(form, 'callScheduledDateTime', setForm, 'datetime-local'), true)}
        ${xrow('Assigned to', selectOf(form, 'assignedTo', setForm, [['', 'Staff name'], ...people.map((row) => [String(row.user_id), row.name])]), true)}
        ${xrow('Subject', html`<input maxlength="30" placeholder="Subject" value=${form.callSubject || ''} onInput=${(event) => setForm({ ...form, callSubject: event.target.value })} />`, true)}
        <h3>Purpose</h3>
        ${xrow('Follow-up purpose', selectOf(form, 'callPurpose', setForm, [['', 'Follow-up purpose'], ...purposes.map((item) => [item, item])]), true)}
        ${xrow('Description', html`<textarea placeholder="Description" value=${form.callDescription || ''} onInput=${(event) => setForm({ ...form, callDescription: event.target.value })}></textarea>`, false, true)}
    `, true, 'Schedule');
}
function VisitForm({ leadId, leadName, onDone, onClose }) {
    const locked = Boolean(leadId);
    const [form, setForm] = useState({ leadId: leadId ? String(leadId) : '', visitType: '', currentState: 'SCHEDULED' });
    const [leads, setLeads] = useState([]);
    const [people, setPeople] = useState([]);
    const [projects, setProjects] = useState([]);
    const [error, setError] = useState('');
    useEffect(() => {
        if (!locked) call('/api/v1/leads').then((rows) => setLeads(Array.isArray(rows) ? rows : [])).catch(() => {});
        call('/api/v1/staff').then((rows) => setPeople(Array.isArray(rows) ? rows : [])).catch(() => {});
        call('/api/v1/projects').then((rows) => setProjects(Array.isArray(rows) ? rows : [])).catch(() => {});
    }, []);
    async function submit(event) {
        event.preventDefault();
        const chosen = locked ? String(leadId) : form.leadId;
        if (!chosen) return setError('Please select a lead');
        if (!form.projectId) return setError('Please select a project');
        if (!form.scheduledDateTime) return setError('Please choose the visit time');
        if (!form.assignedTo) return setError('Please select assigned to');
        if (!form.visitType) return setError('Please select a visit type');
        if (!form.description?.trim()) return setError('Please enter a description');
        setError('');
        try {
            await call('/api/v1/visits', { method: 'POST', body: { ...form, leadId: Number(chosen), assignedTo: Number(form.assignedTo), projectId: Number(form.projectId), currentState: 'SCHEDULED' } });
            onDone();
        } catch (e) { setError(e.message); }
    }
    return sheet('Schedule a Visit', error, submit, onClose, html`
        <h3>Visit Information</h3>
        ${locked ? xrow('Lead name', html`<input class="locked" value=${leadName || 'This lead'} readOnly />`) : xrow('Lead name', selectOf(form, 'leadId', setForm, [['', 'Lead name'], ...leads.map((row) => [String(row.id), row.name])]), true)}
        ${xrow('Project', selectOf(form, 'projectId', setForm, [['', 'Project'], ...projects.map((row) => [String(row.id), row.name])]), true)}
        ${xrow('Schedule date', inputOf(form, 'scheduledDateTime', setForm, 'datetime-local'), true)}
        ${xrow('Status', html`<input class="locked" value="Schedule" readOnly />`)}
        ${xrow('Assigned to', selectOf(form, 'assignedTo', setForm, [['', 'Staff name'], ...people.map((row) => [String(row.user_id), row.name])]), true)}
        ${xrow('Visit type', selectOf(form, 'visitType', setForm, [['', 'Visit type'], ['New Visit', 'New Visit'], ['Re-Visit', 'Re-Visit']]), true)}
        ${xrow('Description', html`<textarea placeholder="Description" value=${form.description || ''} onInput=${(event) => setForm({ ...form, description: event.target.value })}></textarea>`, true, true)}
    `, true, 'Schedule');
}
function TaskForm({ leadId, leadName, onDone, onClose }) {
    const locked = Boolean(leadId);
    const [form, setForm] = useState({ leadId: leadId ? String(leadId) : '', priority: 'MEDIUM' });
    const [leads, setLeads] = useState([]);
    const [people, setPeople] = useState([]);
    const [error, setError] = useState('');
    useEffect(() => {
        if (!locked) call('/api/v1/leads').then((rows) => setLeads(Array.isArray(rows) ? rows : [])).catch(() => {});
        call('/api/v1/staff').then((rows) => setPeople(Array.isArray(rows) ? rows : [])).catch(() => {});
    }, []);
    async function submit(event) {
        event.preventDefault();
        const chosen = locked ? String(leadId) : form.leadId;
        if (!chosen) { setError('Please select a lead'); return; }
        setError('');
        try {
            await call('/api/v1/tasks', { method: 'POST', body: { ...form, leadId: Number(chosen), assignedTo: Number(form.assignedTo) } });
            onDone();
        } catch (e) { setError(e.message); }
    }
    return html`<${FormCard} title="New task" error=${error} onSubmit=${submit} onClose=${onClose}>
        ${locked ? field('Lead', html`<input class="locked" value=${leadName || 'This lead'} readOnly />`, true) : field('Lead', selectOf(form, 'leadId', setForm, [['', 'Choose a lead'], ...leads.map((row) => [String(row.id), row.name])]), true)}
        ${field('Subject', inputOf(form, 'subject', setForm))}
        ${field('Priority', selectOf(form, 'priority', setForm, [['LOW', 'Low'], ['MEDIUM', 'Medium'], ['HIGH', 'High']]))}
        ${field('Start time', inputOf(form, 'scheduledDateTime', setForm, 'datetime-local'))}
        ${field('Assign to', selectOf(form, 'assignedTo', setForm, [['', 'Choose'], ...people.map((row) => [String(row.user_id), row.name])]))}
        ${field('Description', html`<textarea value=${form.description || ''} onInput=${(e) => setForm({ ...form, description: e.target.value })}></textarea>`, true)}
    <//>`;
}
function AssignForm({ lead, onDone, onClose }) {
    const [form, setForm] = useState({ assignedTo: '', reason: '' });
    const [people, setPeople] = useState([]);
    const [error, setError] = useState('');
    useEffect(() => { call('/api/v1/staff').then((rows) => setPeople(Array.isArray(rows) ? rows : [])).catch(() => {}); }, []);
    async function submit(event) {
        event.preventDefault();
        setError('');
        try {
            await call('/api/v1/leads/' + lead.id + '/transfer', { method: 'POST', body: { assignedTo: Number(form.assignedTo), reason: form.reason } });
            onDone();
        } catch (e) { setError(e.message); }
    }
    return html`<${FormCard} title=${'Assign ' + (lead.name || 'lead')} error=${error} onSubmit=${submit} onClose=${onClose}>
        ${field('Person', selectOf(form, 'assignedTo', setForm, [['', 'Choose'], ...people.map((row) => [String(row.user_id), row.name])]))}
        ${field('Reason', inputOf(form, 'reason', setForm), true)}
    <//>`;
}
function UserForm({ onDone, onClose }) {
    const [form, setForm] = useState({ active: '1' });
    const [roles, setRoles] = useState([]);
    const [teams, setTeams] = useState([]);
    const [error, setError] = useState('');
    const [issued, setIssued] = useState(null);
    useEffect(() => {
        call('/api/v1/roles').then((rows) => setRoles(Array.isArray(rows) ? rows : [])).catch(() => {});
        call('/api/v1/teams').then((rows) => setTeams(Array.isArray(rows) ? rows : [])).catch(() => {});
    }, []);
    async function submit(event) {
        event.preventDefault();
        setError('');
        try {
            const created = await call('/api/v1/users', { method: 'POST', body: { ...form, role_id: Number(form.role_id), groupId: Number(form.groupId || 0), active: form.active === '1' } });
            setIssued(created || {});
        } catch (e) { setError(e.message); }
    }
    if (issued) {
        const link = location.origin + apiPath(issued.setup_path || '');
        return html`<div class="modal-back" onClick=${onDone}><form class="form-card modal-card confirm-card" onClick=${(event) => event.stopPropagation()} onSubmit=${(event) => { event.preventDefault(); onDone(); }}>
            <h2>User created</h2>
            <p class="lede">Any role can be created. Sign in with POST /api/v1/auth/login using this email and password. There is no extra restriction.</p>
            ${issued.password || issued.temporary_password ? field('Password', html`<input readOnly value=${issued.password || issued.temporary_password} />`) : html`<p>This email already has an account. Sign in with the password that account already uses.</p>`}
            ${field('Setup link', html`<input readOnly value=${link} />`, true)}
            <div class="row-actions"><button class="primary" type="submit">Done</button></div>
        </form></div>`;
    }
    return html`<${FormCard} title="New user" error=${error} onSubmit=${submit} onClose=${onClose}>
        ${field('Name', inputOf(form, 'name', setForm))}
        ${field('Email', inputOf(form, 'email', setForm, 'email'))}
        ${field('Phone', inputOf(form, 'phoneNumber', setForm, 'tel'))}
        ${field('Password', inputOf(form, 'password', setForm, 'password'))}
        ${field('Role', selectOf(form, 'role_id', setForm, [['', 'Choose a role'], ...roles.map((row) => [String(row.id), row.name])]))}
        ${field('Profile', inputOf(form, 'profile', setForm))}
        ${field('Team', selectOf(form, 'groupId', setForm, [['', 'None'], ...teams.map((row) => [String(row.id), row.name])]))}
        ${field('Date of birth', inputOf(form, 'dob', setForm, 'date'))}
        ${field('Street', inputOf(form, 'street', setForm))}
        ${field('City', inputOf(form, 'city', setForm))}
        ${field('State', inputOf(form, 'state', setForm))}
        ${field('Zip', inputOf(form, 'zipCode', setForm))}
        ${field('Active', selectOf(form, 'active', setForm, [['1', 'Active'], ['0', 'Inactive']]))}
    <//>`;
}
function RoleForm({ role, onDone, onClose }) {
    const [name, setName] = useState(role?.name || '');
    const [groups, setGroups] = useState({});
    const [keys, setKeys] = useState([]);
    const [error, setError] = useState('');
    useEffect(() => {
        call('/api/v1/access').then((data) => setGroups(data && typeof data === 'object' ? data : {})).catch((e) => setError(e.message));
        if (role?.id) {
            call('/api/v1/roles/' + role.id).then((data) => { setName(data.name || ''); setKeys(data.keys || []); }).catch((e) => setError(e.message));
        }
    }, [role?.id]);
    function toggle(key) {
        setKeys(keys.includes(key) ? keys.filter((item) => item !== key) : [...keys, key]);
    }
    async function submit(event) {
        event.preventDefault();
        setError('');
        try {
            if (role?.id) await call('/api/v1/roles/' + role.id + '/access', { method: 'POST', body: { keys } });
            else await call('/api/v1/roles', { method: 'POST', body: { name, keys } });
            onDone();
        } catch (e) { setError(e.message); }
    }
    return html`<div class="modal-back" onClick=${onClose}>
    <form class="form-card modal-card" onClick=${(event) => event.stopPropagation()} onSubmit=${submit}>
        <h2>${role?.id ? 'Allocate access' : 'New role'}</h2>
        ${role?.id ? null : field('Role name', html`<input value=${name} onInput=${(e) => setName(e.target.value)} />`)}
        ${Object.entries(groups).map(([label, items]) => html`<div class="access-group span-2" key=${label}>
            <strong>${label}</strong>
            <div class="checks">${items.map((item) => html`<label class="check-row" key=${item.key}><input type="checkbox" checked=${keys.includes(item.key)} onChange=${() => toggle(item.key)} />${item.label}</label>`)}</div>
        </div>`)}
        ${error ? html`<p class="error">${error}</p>` : null}
        <div class="row-actions">
            <button class="primary" type="submit">Save</button>
            <button class="ghost" type="button" onClick=${onClose}>Cancel</button>
        </div>
    </form></div>`;
}
function Person({ name }) {
    if (!name) return '—';
    const colors = ['#e85d4c', '#3cbfa0', '#e0a23a', '#1F6B4A', '#299cdb', '#8b6cc1'];
    const color = colors[(name.charCodeAt(0) || 0) % colors.length];
    return html`<span class="who"><i style=${{ background: color }}>${initials(name)}</i><span>${name}</span></span>`;
}
function StatusPill({ value }) {
    const key = String(value || 'SCHEDULED').toUpperCase();
    return html`<span class=${'state ' + key.toLowerCase()}>${key.replace('_', ' ')}</span>`;
}
function prettyDate(value) {
    const parts = stampParts(value) || (String(value || '').match(/^(\d{4}-\d{2}-\d{2})/) ? { day: String(value).slice(0, 10), hour: 0, minute: '00' } : null);
    if (!parts) return '—';
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const date = new Date(parts.day + 'T12:00:00');
    return String(date.getDate()).padStart(2, '0') + ' ' + months[date.getMonth()] + ' ' + date.getFullYear();
}
function prettyWhen(value) {
    if (!stampParts(value)) return prettyDate(value);
    return prettyDate(value) + ' ' + clock(value);
}
const DATE_RANGES = [['all', 'All time'], ['today', 'Today'], ['yesterday', 'Yesterday'], ['this_week', 'This week'], ['last_week', 'Last week'], ['this_month', 'This month'], ['last_month', 'Last month'], ['last_6_months', 'Last 6 months'], ['this_year', 'This year'], ['custom', 'Custom range']];
function blankFilter() {
    return { stage: '', source: '', assignee: '', project: '', status: '', range: 'all', from: '', to: '' };
}
function rangeBounds(range, from, to) {
    const now = new Date();
    const at = (year, month, day, end) => new Date(year, month, day, end ? 23 : 0, end ? 59 : 0, end ? 59 : 0, end ? 999 : 0);
    const today = at(now.getFullYear(), now.getMonth(), now.getDate(), false);
    const shift = (days) => { const date = new Date(today); date.setDate(date.getDate() + days); return date; };
    const pair = (start, finish) => [start, at(finish.getFullYear(), finish.getMonth(), finish.getDate(), true)];
    if (range === 'today') return pair(today, today);
    if (range === 'yesterday') { const date = shift(-1); return pair(date, date); }
    if (range === 'this_week') {
        const monday = shift(-((today.getDay() + 6) % 7));
        return pair(monday, shift(-((today.getDay() + 6) % 7) + 6));
    }
    if (range === 'last_week') return pair(shift(-((today.getDay() + 6) % 7) - 7), shift(-((today.getDay() + 6) % 7) - 1));
    if (range === 'this_month') return [at(now.getFullYear(), now.getMonth(), 1, false), at(now.getFullYear(), now.getMonth() + 1, 0, true)];
    if (range === 'last_month') return [at(now.getFullYear(), now.getMonth() - 1, 1, false), at(now.getFullYear(), now.getMonth(), 0, true)];
    if (range === 'last_6_months') return [at(now.getFullYear(), now.getMonth() - 5, 1, false), at(now.getFullYear(), now.getMonth() + 1, 0, true)];
    if (range === 'this_year') return [at(now.getFullYear(), 0, 1, false), at(now.getFullYear(), 11, 31, true)];
    if (range === 'custom') {
        const start = from ? new Date(from + 'T00:00:00') : null;
        const end = to ? new Date(to + 'T23:59:59') : null;
        if (start && end && end < start) return 'invalid';
        if (!start && !end) return null;
        return [start, end];
    }
    return null;
}
function inRange(value, bounds) {
    if (!bounds) return true;
    if (bounds === 'invalid') return false;
    const parts = stampParts(value);
    const day = parts ? parts.day : (String(value || '').match(/^(\d{4}-\d{2}-\d{2})/) || [])[1];
    if (!day) return false;
    const when = new Date(day + 'T12:00:00');
    if (bounds[0] && when < new Date(bounds[0].getFullYear(), bounds[0].getMonth(), bounds[0].getDate())) return false;
    if (bounds[1] && when > bounds[1]) return false;
    return true;
}
function choices(rows, read) {
    return [...new Set(rows.map(read).filter(Boolean))].sort((a, b) => String(a).localeCompare(String(b)));
}
function FilterDialog({ title, draft, setDraft, activity, stages, sources, projects, people, onApply, onClear, onClose }) {
    const bad = draft.range === 'custom' && draft.from && draft.to && draft.to < draft.from;
    function field(label, key, options, placeholder) {
        return html`<label>${label}<select value=${draft[key]} onChange=${(event) => setDraft({ ...draft, [key]: event.target.value })}><option value="">${placeholder}</option>${options.map((item) => html`<option key=${item} value=${item}>${item}</option>`)}</select></label>`;
    }
    return html`<div class="modal-back" onClick=${onClose}>
        <form class="filter-box" onClick=${(event) => event.stopPropagation()} onSubmit=${(event) => { event.preventDefault(); if (!bad) onApply(); }}>
            <header><h2>${title}</h2><button class="filter-x" type="button" onClick=${onClose} aria-label="Close">×</button></header>
            <div class="filter-body">
                ${field('Assigned to', 'assignee', people, 'All users')}
                ${activity ? field('Status', 'status', ['SCHEDULED', 'COMPLETED', 'OVERDUE', 'CANCELLED'], 'All status') : null}
                ${activity ? null : field('Lead stage', 'stage', stages, 'All stages')}
                ${activity ? null : field('Lead source', 'source', sources, 'All sources')}
                ${activity ? null : field('Project', 'project', projects, 'All projects')}
                <label>Date range<select value=${draft.range} onChange=${(event) => setDraft({ ...draft, range: event.target.value })}>${DATE_RANGES.map(([value, label]) => html`<option key=${value} value=${value}>${label}</option>`)}</select></label>
                ${draft.range === 'custom' ? html`<div class="filter-dates">
                    <label>Start date<input type="date" value=${draft.from} onInput=${(event) => setDraft({ ...draft, from: event.target.value })} /></label>
                    <label>End date<input type="date" value=${draft.to} onInput=${(event) => setDraft({ ...draft, to: event.target.value })} /></label>
                </div>` : null}
                ${bad ? html`<p class="error">End date must be after the start date.</p>` : null}
            </div>
            <footer>
                <button class="ghost" type="button" onClick=${onClear}>Clear</button>
                <button class="primary" type="submit">Apply filter</button>
            </footer>
        </form>
    </div>`;
}
function Board({ kind, title, rows, onReload, onRow, onOpen, onEdit, onDelete, active }) {
    const [text, setText] = useState('');
    const [filters, setFilters] = useState(blankFilter);
    const [draft, setDraft] = useState(blankFilter);
    const [filterOpen, setFilterOpen] = useState(false);
    const [busy, setBusy] = useState(0);
    const [error, setError] = useState('');
    const [move, setMove] = useState(null);
    const [when, setWhen] = useState('');
    const activity = kind !== 'leads';
    const counts = { SCHEDULED: 0, COMPLETED: 0, OVERDUE: 0, CANCELLED: 0 };
    rows.forEach((row) => { const key = String(row.display_status || '').toUpperCase(); if (counts[key] != null) counts[key] += 1; });
    const stages = choices(rows, (row) => row.lead_stage);
    const sources = choices(rows, (row) => row.lead_source || row.source);
    const projects = choices(rows, (row) => row.project_name);
    const people = choices(rows, (row) => row.assigned_name || row.owner_name || row.staff_name);
    const bounds = rangeBounds(filters.range, filters.from, filters.to);
    const shown = rows.filter((row) => {
        if (activity && filters.status && String(row.display_status || '').toUpperCase() !== filters.status) return false;
        if (!activity && filters.stage && String(row.lead_stage || '') !== filters.stage) return false;
        if (filters.source && String(row.lead_source || row.source || '') !== filters.source) return false;
        if (filters.project && String(row.project_name || '') !== filters.project) return false;
        if (filters.assignee && String(row.assigned_name || row.owner_name || row.staff_name || '') !== filters.assignee) return false;
        const when = activity
            ? (kind === 'calls' ? (row.call_scheduled_at || row.next_at) : kind === 'visits' ? (row.scheduled_date_time || row.scheduled_at) : row.scheduled_at)
            : row.created_at;
        if (!inRange(when, bounds)) return false;
        if (!text.trim()) return true;
        const blob = Object.values(row).join(' ').toLowerCase();
        return blob.includes(text.trim().toLowerCase());
    });
    const chips = [];
    if (filters.assignee) chips.push(['assignee', 'Assigned: ' + filters.assignee]);
    if (filters.status) chips.push(['status', 'Status: ' + filters.status[0] + filters.status.slice(1).toLowerCase()]);
    if (filters.stage) chips.push(['stage', 'Stage: ' + filters.stage]);
    if (filters.source) chips.push(['source', 'Source: ' + filters.source]);
    if (filters.project) chips.push(['project', 'Project: ' + filters.project]);
    if (filters.range && filters.range !== 'all') {
        const label = filters.range === 'custom'
            ? [filters.from, filters.to].filter(Boolean).join(' – ') || 'Custom range'
            : (DATE_RANGES.find((item) => item[0] === filters.range) || [])[1];
        chips.push(['range', label]);
    }
    function dropChip(key) {
        if (key === 'range') setFilters({ ...filters, range: 'all', from: '', to: '' });
        else setFilters({ ...filters, [key]: '' });
    }
    const base = kind === 'calls' ? '/api/v1/followups/' : kind === 'visits' ? '/api/v1/visits/' : '/api/v1/tasks/';
    async function done(row, event) {
        event.stopPropagation();
        setError('');
        setBusy(row.id);
        try { await call(base + row.id + '/done', { method: 'POST', body: {} }); onReload(); }
        catch (e) { setError(e.message); }
        setBusy(0);
    }
    async function reschedule(event) {
        event.preventDefault();
        setError('');
        try {
            const body = kind === 'calls' ? { callScheduledDateTime: when } : { scheduledDateTime: when };
            await call(base + move.id + '/reschedule', { method: 'POST', body });
            setMove(null);
            setWhen('');
            onReload();
        } catch (e) { setError(e.message); }
    }
    function actions(row) {
        const closed = ['COMPLETED', 'CANCELLED'].includes(String(row.display_status || '').toUpperCase());
        if (closed) return null;
        return html`<div class="row-actions" onClick=${(event) => event.stopPropagation()}>
            <button class="act-done" disabled=${busy === row.id} onClick=${(event) => done(row, event)}>Mark Done</button>
            <button class="act-later" onClick=${() => { setMove(row); setWhen(''); }}>Reschedule</button>
        </div>`;
    }
    return html`<article class="board">
        <header class="board-head">
            <h2>${title}</h2>
            ${activity ? html`<div class="counts">
                ${[['SCHEDULED', counts.SCHEDULED, 'sched'], ['COMPLETED', counts.COMPLETED, 'done'], ['OVERDUE', counts.OVERDUE, 'late'], ['CANCELLED', counts.CANCELLED, 'stop']].map(([label, count, tone]) => html`<button key=${label} class=${`count ${tone} ${filters.status === label ? 'on' : ''}`} onClick=${() => setFilters({ ...filters, status: filters.status === label ? '' : label })}><i></i>${count} ${label[0] + label.slice(1).toLowerCase()}</button>`)}
            </div>` : html`<div class="board-search">
                <input placeholder=${'Search ' + title + '...'} value=${text} onInput=${(event) => setText(event.target.value)} />
                <span>Total: ${shown.length} ${title.toLowerCase()}</span>
            </div>`}
        </header>
        <div class="board-tools">
            <button class="filter-by" type="button" onClick=${() => { setDraft({ ...filters }); setFilterOpen(true); }}>
                Filter by
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 5h16l-6 7.2V19l-4 2v-8.8L4 5z"/></svg>
            </button>
            <div class="filter-chips">
                ${chips.map(([key, label]) => html`<span class="filter-chip" key=${key}>${label}<button type="button" aria-label=${'Remove ' + label} onClick=${() => dropChip(key)}>×</button></span>`)}
            </div>
            ${activity ? html`<div class="board-search">
                <input placeholder=${'Search ' + title + '...'} value=${text} onInput=${(event) => setText(event.target.value)} />
                <span>Total: ${shown.length}</span>
            </div>` : null}
        </div>
        ${filterOpen ? html`<${FilterDialog}
            title=${'Filter ' + title}
            draft=${draft}
            setDraft=${setDraft}
            activity=${activity}
            stages=${stages}
            sources=${sources}
            projects=${projects}
            people=${people}
            onApply=${() => { setFilters(draft); setFilterOpen(false); }}
            onClear=${() => { const next = blankFilter(); setDraft(next); setFilters(next); setFilterOpen(false); }}
            onClose=${() => setFilterOpen(false)}
        />` : null}
        ${error ? html`<p class="error">${error}</p>` : null}
        ${move ? html`<div class="modal-back" onClick=${() => setMove(null)}>
            <form class="form-card modal-card confirm-card" onClick=${(event) => event.stopPropagation()} onSubmit=${reschedule}>
                <h2>Reschedule ${move.lead_name || ''}</h2>
                <div class="form-grid">
                    <label class="field"><span>New time</span><input type="datetime-local" required value=${when} onInput=${(event) => setWhen(event.target.value)} /></label>
                </div>
                <div class="row-actions">
                    <button class="primary" type="submit">Save</button>
                    <button class="ghost" type="button" onClick=${() => setMove(null)}>Cancel</button>
                </div>
            </form>
        </div>` : null}
        <div class="table-card"><table class="board-table">
            <thead><tr>
                ${kind === 'leads' ? html`<th>Lead name</th><th>Phone no</th><th>Project</th><th>Assigned to</th><th>Lead stage</th><th>Lead source</th><th>Modified date</th><th>Created on</th>${onEdit || onDelete ? html`<th></th>` : null}` : null}
                ${kind === 'calls' ? html`<th>Lead name</th><th>Phone number</th><th>Schedule date</th><th>Subject</th><th>Assigned to</th><th>Assigned by</th><th>Created on</th><th>Status</th><th></th>` : null}
                ${kind === 'visits' ? html`<th>Lead name</th><th>Project</th><th>Schedule date</th><th>Assigned to</th><th>Assigned by</th><th>Created on</th><th>Status</th><th></th>` : null}
                ${kind === 'tasks' ? html`<th>Lead name</th><th>Subject</th><th>Priority</th><th>Start time</th><th>Assigned to</th><th>Created on</th><th>Status</th><th></th>` : null}
            </tr></thead>
            <tbody>
                ${shown.length === 0 ? html`<tr><td colSpan="9">Nothing here yet.</td></tr>` : shown.map((row) => html`<tr key=${row.id} class=${active === row ? 'active' : ''} onClick=${() => { onRow && onRow(row); if (onOpen) onOpen(row); }}>
                    ${kind === 'leads' ? html`
                        <td>${row.name || '—'}</td>
                        <td>${row.phone_number || row.phone || '—'}</td>
                        <td>${row.project_name || '—'}</td>
                        <td><${Person} name=${row.assigned_name || row.owner_name} /></td>
                        <td><span class="stage">${row.lead_stage || row.status || '—'}</span></td>
                        <td><span class="source"><i></i>${row.lead_source || row.source || '—'}</span></td>
                        <td>${prettyDate(row.updated_at)}</td>
                        <td>${prettyDate(row.created_at)}</td>
                        ${onEdit || onDelete ? html`<td class="row-actions" onClick=${(event) => event.stopPropagation()}>
                            ${onEdit ? html`<button class="ghost" type="button" onClick=${() => onEdit(row)}>Edit</button>` : null}
                            ${onDelete ? html`<button class="ghost" type="button" onClick=${() => onDelete(row)}>Delete</button>` : null}
                        </td>` : null}` : null}
                    ${kind === 'calls' ? html`
                        <td>${row.lead_name || '—'}</td>
                        <td>${row.lead_phone || '—'}</td>
                        <td>${prettyWhen(row.call_scheduled_at || row.next_at)}</td>
                        <td>${row.call_subject || row.body || '—'}</td>
                        <td><${Person} name=${row.assigned_name} /></td>
                        <td><${Person} name=${row.assigned_by_name} /></td>
                        <td>${prettyWhen(row.created_at)}</td>
                        <td><${StatusPill} value=${row.display_status} /></td>
                        <td>${actions(row)}</td>` : null}
                    ${kind === 'visits' ? html`
                        <td>${row.lead_name || '—'}</td>
                        <td>${row.project_name || '—'}</td>
                        <td>${prettyWhen(row.scheduled_date_time || row.scheduled_at)}</td>
                        <td><${Person} name=${row.assigned_name || row.staff_name} /></td>
                        <td><${Person} name=${row.assigned_by_name} /></td>
                        <td>${prettyWhen(row.created_at)}</td>
                        <td><${StatusPill} value=${row.display_status} /></td>
                        <td>${actions(row)}</td>` : null}
                    ${kind === 'tasks' ? html`
                        <td>${row.lead_name || '—'}</td>
                        <td>${row.subject || '—'}</td>
                        <td><span class=${'prio ' + String(row.priority || '').toLowerCase()}>${row.priority || '—'}</span></td>
                        <td>${prettyWhen(row.scheduled_at)}</td>
                        <td><${Person} name=${row.assigned_name} /></td>
                        <td>${prettyWhen(row.created_at)}</td>
                        <td><${StatusPill} value=${row.display_status} /></td>
                        <td>${actions(row)}</td>` : null}
                </tr>`)}
            </tbody>
        </table></div>
    </article>`;
}
const LEAD_STAGES = ['New', 'Qualified', 'Follow Up', 'Proposal Sent', 'Site Visit Scheduled', 'Negotiation', 'Booked', 'Lost'];
const DEAL_STAGES = [['PROPOSAL_SUBMITTED', 'Proposal submitted'], ['NEGOTIATION', 'Negotiation'], ['BOOKING', 'Booking'], ['BOOKING_CANCELLED', 'Booking cancelled'], ['REGISTERED', 'Registered']];
function dealLabel(value) {
    return (DEAL_STAGES.find((item) => item[0] === value) || [value, value])[1];
}
function LeadPage({ leadId, onBack }) {
    const [lead, setLead] = useState(null);
    const [section, setSection] = useState('overview');
    const [details, setDetails] = useState(false);
    const [notes, setNotes] = useState([]);
    const [deals, setDeals] = useState([]);
    const [projects, setProjects] = useState([]);
    const [calls, setCalls] = useState([]);
    const [visits, setVisits] = useState([]);
    const [tasks, setTasks] = useState([]);
    const [error, setError] = useState('');
    const [editor, setEditor] = useState('');
    const [stageOpen, setStageOpen] = useState(false);
    const [stage, setStage] = useState('New');
    const [pickedProjects, setPickedProjects] = useState([]);
    const [note, setNote] = useState({ noteHeading: '', noteContent: '' });
    const [noteConfirm, setNoteConfirm] = useState(null);
    const [noteSort, setNoteSort] = useState('new');
    const [tab, setTab] = useState('followups');
    const [menu, setMenu] = useState(false);
    const [deal, setDeal] = useState({ dealStage: 'PROPOSAL_SUBMITTED', totalDealValue: '', projectId: '', amount: '', dealDescription: '' });
    const loadTick = useRef(0);
    const safe = (path) => call(path).catch(() => []);
    function load() {
        const tick = ++loadTick.current;
        const mine = (rows) => (Array.isArray(rows) ? rows : []).filter((row) => Number(row.lead_id || row.leadId) === Number(leadId));
        const keep = (apply, pick) => (rows) => { if (tick === loadTick.current) apply(pick ? pick(rows) : rows); };
        call('/api/v1/leads/' + leadId).then((row) => { if (tick === loadTick.current) { setLead(row); setStage(row.lead_stage || 'New'); } }).catch((e) => { if (tick === loadTick.current) setError(e.message); });
        safe('/api/v1/leads/' + leadId + '/notes').then(keep(setNotes, (rows) => Array.isArray(rows) ? rows : []));
        safe('/api/v1/leads/' + leadId + '/deals').then(keep(setDeals, (rows) => Array.isArray(rows) ? rows : []));
        safe('/api/v1/projects').then(keep(setProjects, (rows) => Array.isArray(rows) ? rows : []));
        safe('/api/v1/followups').then(keep(setCalls, mine));
        safe('/api/v1/visits').then(keep(setVisits, mine));
        safe('/api/v1/tasks').then(keep(setTasks, mine));
    }
    function savedActivity(kind) {
        setEditor('');
        setSection('activities');
        setTab(kind);
        load();
    }
    useEffect(() => { load(); }, [leadId]);
    if (!lead) return html`<p class="lede">${error || 'Loading the lead…'}</p>`;
    const ids = String(lead.interested_projects || '').split(',').map((item) => item.trim()).filter(Boolean);
    const interested = ids.map((id) => ({ id, name: (projects.find((row) => String(row.id) === id) || {}).name || ('Project ' + id) }));
    const owner = lead.assigned_name || lead.owner_name || 'Unassigned';
    const openOf = (rows) => rows.filter((row) => ['SCHEDULED', 'OVERDUE'].includes(String(row.display_status || '').toUpperCase()));
    const openCalls = openOf(calls);
    const openVisits = openOf(visits);
    const openTasks = openOf(tasks);
    const openCount = openCalls.length + openVisits.length + openTasks.length;
    const shownNotes = [...notes].sort((a, b) => noteSort === 'new' ? String(b.created_at).localeCompare(String(a.created_at)) : String(a.created_at).localeCompare(String(b.created_at)));
    function fact(label, value) {
        return html`<div class="fact"><span>${label}</span><div>${value || '—'}</div></div>`;
    }
    async function saveStage(event) {
        event.preventDefault();
        setError('');
        try { setLead(await call('/api/v1/leads/' + leadId + '/stage', { method: 'POST', body: { leadStage: stage } })); setStageOpen(false); }
        catch (e) { setError(e.message); }
    }
    async function saveProjects(event) {
        event.preventDefault();
        setError('');
        try { setLead(await call('/api/v1/leads/' + leadId + '/projects', { method: 'POST', body: { intrestedProjects: pickedProjects } })); setEditor(''); }
        catch (e) { setError(e.message); }
    }
    async function removeProject(id) {
        setError('');
        try { setLead(await call('/api/v1/leads/' + leadId + '/projects', { method: 'POST', body: { intrestedProjects: ids.filter((item) => item !== id) } })); }
        catch (e) { setError(e.message); }
    }
    async function saveNote(event) {
        event.preventDefault();
        if (!note.noteHeading.trim() || !note.noteContent.trim()) { setError('A note needs a title and some text.'); return; }
        setError('');
        try { await call('/api/v1/leads/' + leadId + '/notes', { method: 'POST', body: note }); setNote({ noteHeading: '', noteContent: '' }); load(); }
        catch (e) { setError(e.message); }
    }
    async function removeNote(event) {
        event.preventDefault();
        if (!noteConfirm) return;
        setError('');
        try { await call('/api/v1/leads/' + leadId + '/notes/' + noteConfirm.id + '/delete', { method: 'POST', body: {} }); setNoteConfirm(null); load(); }
        catch (e) { setError(e.message); }
    }
    async function saveDeal(event) {
        event.preventDefault();
        if (!deal.dealStage) { setError('Choose a deal stage.'); return; }
        setError('');
        const projectDetails = deal.projectId ? [{ projectId: Number(deal.projectId), amount: Number(deal.amount || deal.totalDealValue || 0) }] : [];
        try {
            await call('/api/v1/leads/' + leadId + '/deals', { method: 'POST', body: { ...deal, totalDealValue: Number(deal.totalDealValue || 0), projectDetails } });
            setDeal({ dealStage: 'PROPOSAL_SUBMITTED', totalDealValue: '', projectId: '', amount: '', dealDescription: '' });
            setEditor('');
            load();
        } catch (e) { setError(e.message); }
    }
    async function finish(kind, row) {
        const base = kind === 'followups' ? '/api/v1/followups/' : kind === 'visits' ? '/api/v1/visits/' : '/api/v1/tasks/';
        setError('');
        try { await call(base + row.id + '/done', { method: 'POST', body: {} }); load(); }
        catch (e) { setError(e.message); }
    }
    const activityRows = tab === 'followups' ? openCalls : tab === 'visits' ? openVisits : openTasks;
    return html`<div class="lead-page">
        <aside class="lead-side">
            <button type="button" class="back-link" onClick=${onBack}><span>←</span> Back to Leads</button>
            <button type="button" class=${section === 'overview' ? 'on' : ''} onClick=${() => setSection('overview')}>Overview</button>
            <button type="button" class=${section === 'notes' ? 'on' : ''} onClick=${() => setSection('notes')}>Notes <em>${notes.length}</em></button>
            <button type="button" class=${section === 'activities' ? 'on' : ''} onClick=${() => setSection('activities')}>Open Activities <em>${openCount}</em></button>
        </aside>
        <div class="lead-main">
            ${error ? html`<p class="error">${error}</p>` : null}
            <header class="lead-head">
                <span class="lead-avatar">${String(lead.client_first_name || lead.name || '?').trim().charAt(0).toUpperCase()}</span>
                <div>
                    <h2>${lead.name || 'Unnamed lead'}</h2>
                    <p>Assigned to <b>${owner}</b> (${lead.assigned_type || 'USER'})</p>
                </div>
                <button type="button" class="ghost" onClick=${() => setEditor('whatsapp')}>Send WhatsApp</button>
                <button type="button" class="icon-plain" aria-label="Edit lead" onClick=${() => setEditor('lead')}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                </button>
            </header>
            ${section === 'overview' ? html`<div>
                <section class="lead-card">
                    <h3>Basic Information</h3>
                    ${fact('Lead owner', html`<${Person} name=${owner} />`)}
                    ${fact('Email', lead.email ? html`<a href=${'mailto:' + lead.email}>${lead.email}</a>` : '—')}
                    ${fact('Phone', lead.phone_number || lead.phone)}
                    ${fact('Mobile', lead.alternate_phone_number || lead.phone_secondary)}
                    ${fact('Lead stage', html`<b class="stage-edit">${lead.lead_stage || '—'}</b><button type="button" class="icon-plain" aria-label="Edit stage" onClick=${() => { setStage(lead.lead_stage || 'New'); setStageOpen(true); }}><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg></button>`)}
                    ${fact('Assigned by', lead.assigned_by_name ? html`<${Person} name=${lead.assigned_by_name} />` : '—')}
                </section>
                <button type="button" class="details-toggle" onClick=${() => setDetails(!details)}>${details ? 'Hide Details' : 'View Details'} <span>${details ? '▴' : '▾'}</span></button>
                ${details ? html`<section class="lead-card">
                    <h3>Lead Information</h3>
                    <div class="fact-grid">
                        <div>
                            ${fact('Assigned to', owner)}
                            ${fact('First name', [lead.client_name_prefix, lead.client_first_name].filter(Boolean).join(' '))}
                            ${fact('Last name', lead.client_last_name)}
                            ${fact('Phone', lead.phone_number || lead.phone)}
                            ${fact('Mobile', lead.alternate_phone_number || lead.phone_secondary)}
                        </div>
                        <div>
                            ${fact('Email', lead.email)}
                            ${fact('Project enquiry', lead.project_name)}
                            ${fact('Lead source', lead.lead_source || lead.source)}
                            ${fact('Campaign', lead.campaign_source)}
                        </div>
                    </div>
                    <h3>Address Information</h3>
                    <div class="fact-grid">
                        <div>${fact('Street', lead.client_street)}${fact('State', lead.client_state)}${fact('City', lead.client_city)}</div>
                        <div>${fact('Country', lead.client_country)}${fact('Postal code', lead.client_zip_code)}</div>
                    </div>
                    <h3>Lead Preferences</h3>
                    <div class="fact-grid">
                        <div>
                            ${fact('Property type', lead.property_type)}
                            ${fact('Preference', lead.client_preference)}
                            ${fact('Budget', lead.client_budget_value ? lead.client_budget_value + ' ' + (lead.client_budget_type || '') : '')}
                        </div>
                        <div>${fact('Location', lead.location)}${fact('Area', lead.area)}</div>
                    </div>
                    <h3>Lead Description</h3>
                    ${fact('Description', lead.description || lead.notes)}
                </section>` : null}
                <section class="lead-card">
                    <div class="card-head"><h3>Interests Projects</h3><button type="button" class="add-round" onClick=${() => { setPickedProjects(ids); setEditor('projects'); }}><i>+</i> Add Projects</button></div>
                    ${interested.length === 0 ? html`<p class="empty-line">No interested projects added yet</p>` : html`<div class="project-pills">${interested.map((item) => html`<span key=${item.id}>${item.name}<button type="button" aria-label=${'Remove ' + item.name} onClick=${() => removeProject(item.id)}>×</button></span>`)}</div>`}
                </section>
                <section class="lead-card">
                    <div class="card-head"><h3>Deals</h3><button type="button" class="add-round" onClick=${() => setEditor('deal')}><i>+</i> Add Deal</button></div>
                    ${deals.length === 0 ? html`<div class="empty-deal"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#c5c9d1" stroke-width="1.4"><path d="M8 4h8l2 3v13H6V7l2-3z"/><path d="M9 12h6M9 16h6"/></svg></div>` : html`<div class="deal-list">${deals.map((row) => html`<article key=${row.id}><strong>${dealLabel(row.deal_stage)}</strong><span>${money(row.total_deal_value)}</span><p>${(row.project_details || []).map((item) => item.projectName).filter(Boolean).join(', ') || row.deal_description || '—'}</p></article>`)}</div>`}
                </section>
            </div>` : null}
            ${section === 'notes' ? html`<section class="lead-card">
                <div class="card-head"><h3>Notes <em class="count-badge">${notes.length}</em></h3>
                    <select value=${noteSort} onChange=${(event) => setNoteSort(event.target.value)}><option value="new">Newest</option><option value="old">Oldest</option></select>
                </div>
                ${shownNotes.length === 0 ? html`<p class="empty-line">No notes yet</p>` : shownNotes.map((row) => html`<article class="note" key=${row.id}><header><strong>${row.note_heading}</strong><button type="button" onClick=${() => setNoteConfirm(row)}>Delete</button></header><p>${row.note_content}</p><small>${row.user_name || 'Staff'} · ${prettyWhen(row.created_at)}</small></article>`)}
                ${noteConfirm ? confirmDelete(noteConfirm.note_heading || 'this note', error, removeNote, () => { setNoteConfirm(null); setError(''); }) : null}
                <form class="note-box" onSubmit=${saveNote}>
                    <input placeholder="Note title" value=${note.noteHeading} onInput=${(event) => setNote({ ...note, noteHeading: event.target.value })} />
                    <textarea placeholder="Add a note..." value=${note.noteContent} onInput=${(event) => setNote({ ...note, noteContent: event.target.value })}></textarea>
                    <div><button class="ghost" type="button" onClick=${() => setNote({ noteHeading: '', noteContent: '' })}>Cancel</button><button class="primary" type="submit">Save</button></div>
                </form>
            </section>` : null}
            ${section === 'activities' ? html`<section class="lead-card">
                <div class="card-head">
                    <h3>Open Activities <em class="count-badge">${openCount}</em></h3>
                    <div class="add-menu">
                        <button type="button" class="linkish" onClick=${() => setMenu(!menu)}>Add new ▾</button>
                        ${menu ? html`<div class="menu-pop">
                            <button type="button" onClick=${() => { setEditor('followup'); setMenu(false); }}>Follow-up</button>
                            <button type="button" onClick=${() => { setEditor('visit'); setMenu(false); }}>Visit</button>
                            <button type="button" onClick=${() => { setEditor('task'); setMenu(false); }}>Task</button>
                        </div>` : null}
                    </div>
                </div>
                <div class="activity-tabs">
                    ${[['followups', 'Follow-ups', openCalls.length], ['visits', 'Visits', openVisits.length], ['tasks', 'Tasks', openTasks.length]].map(([key, label, count]) => html`<button type="button" key=${key} class=${tab === key ? 'on' : ''} onClick=${() => setTab(key)}>${label} <em>${count}</em></button>`)}
                </div>
                ${activityRows.length === 0 ? html`<p class="empty-line">Nothing scheduled yet</p>` : html`<table class="board-table"><tbody>
                    ${activityRows.map((row) => html`<tr key=${row.id}>
                        <td>${prettyWhen(row.call_scheduled_at || row.scheduled_date_time || row.scheduled_at || row.next_at)}</td>
                        <td>${row.call_subject || row.subject || row.visit_type || row.description || '—'}</td>
                        <td><${StatusPill} value=${row.display_status} /></td>
                        <td>${['COMPLETED', 'CANCELLED'].includes(String(row.display_status || '').toUpperCase()) ? null : html`<button class="act-done" type="button" onClick=${() => finish(tab, row)}>Mark Done</button>`}</td>
                    </tr>`)}
                </tbody></table>`}
            </section>` : null}
        </div>
        ${stageOpen ? html`<div class="modal-back" onClick=${() => setStageOpen(false)}><form class="filter-box" onClick=${(event) => event.stopPropagation()} onSubmit=${saveStage}>
            <header><h2>Lead stage</h2><button class="filter-x" type="button" onClick=${() => setStageOpen(false)}>×</button></header>
            <div class="filter-body"><label>Stage<select value=${stage} onChange=${(event) => setStage(event.target.value)}>${LEAD_STAGES.map((item) => html`<option key=${item} value=${item}>${item}</option>`)}</select></label></div>
            <footer><button class="ghost" type="button" onClick=${() => setStageOpen(false)}>Cancel</button><button class="primary" type="submit">Save</button></footer>
        </form></div>` : null}
        ${editor === 'projects' ? html`<div class="modal-back" onClick=${() => setEditor('')}><form class="filter-box" onClick=${(event) => event.stopPropagation()} onSubmit=${saveProjects}>
            <header><h2>Add projects</h2><button class="filter-x" type="button" onClick=${() => setEditor('')}>×</button></header>
            <div class="filter-body"><div class="pick-list">${projects.length === 0 ? html`<span>No projects yet</span>` : projects.map((row) => html`<label key=${row.id}><input type="checkbox" checked=${pickedProjects.includes(String(row.id))} onChange=${() => { const id = String(row.id); setPickedProjects(pickedProjects.includes(id) ? pickedProjects.filter((item) => item !== id) : [...pickedProjects, id]); }} />${row.name}</label>`)}</div></div>
            <footer><button class="ghost" type="button" onClick=${() => setEditor('')}>Cancel</button><button class="primary" type="submit">Save</button></footer>
        </form></div>` : null}
        ${editor === 'deal' ? sheet('Add Deal', error, saveDeal, () => setEditor(''), html`
            ${xrow('Deal stage', html`<select value=${deal.dealStage} onChange=${(event) => setDeal({ ...deal, dealStage: event.target.value })}>${DEAL_STAGES.map(([value, label]) => html`<option key=${value} value=${value}>${label}</option>`)}</select>`, true)}
            ${xrow('Deal value', html`<input type="number" min="0" placeholder="Amount in rupees" value=${deal.totalDealValue} onInput=${(event) => setDeal({ ...deal, totalDealValue: event.target.value })} />`, true)}
            ${xrow('Project', html`<select value=${deal.projectId} onChange=${(event) => setDeal({ ...deal, projectId: event.target.value })}><option value="">Choose a project</option>${projects.map((row) => html`<option key=${row.id} value=${String(row.id)}>${row.name}</option>`)}</select>`)}
            ${xrow('Project amount', html`<input type="number" min="0" value=${deal.amount} onInput=${(event) => setDeal({ ...deal, amount: event.target.value })} />`)}
            ${xrow('Description', html`<textarea value=${deal.dealDescription} onInput=${(event) => setDeal({ ...deal, dealDescription: event.target.value })}></textarea>`, false, true)}
        `, true, 'Add') : null}
        ${editor === 'lead' ? html`<${LeadForm} lead=${lead} onDone=${() => { setEditor(''); load(); }} onClose=${() => setEditor('')} />` : null}
        ${editor === 'followup' ? html`<${FollowForm} leadId=${leadId} leadName=${lead.name} onDone=${() => savedActivity('followups')} onClose=${() => setEditor('')} />` : null}
        ${editor === 'visit' ? html`<${VisitForm} leadId=${leadId} leadName=${lead.name} onDone=${() => savedActivity('visits')} onClose=${() => setEditor('')} />` : null}
        ${editor === 'task' ? html`<${TaskForm} leadId=${leadId} leadName=${lead.name} onDone=${() => savedActivity('tasks')} onClose=${() => setEditor('')} />` : null}
        ${editor === 'whatsapp' ? html`<${SendWhatsApp} lead=${lead} onDone=${() => setEditor('')} onClose=${() => setEditor('')} />` : null}
    </div>`;
}
const PLOT_LABEL = { available: 'Available', advance: 'Advance', booked: 'Booked', sold: 'Sold', owner_use: 'Owner use', future: 'Future', registered: 'Registered', hold: 'Hold', blocked: 'Blocked' };
const PLOT_TONE = { available: 'ok', advance: 'warn', booked: 'info', sold: 'blue', owner_use: 'muted', future: 'soft', registered: 'ok', hold: 'warn', blocked: 'stop' };
function plotTag(status) {
    const key = String(status || '');
    return html`<span class=${'tag ' + (PLOT_TONE[key] || 'muted')}>${PLOT_LABEL[key] || key || '—'}</span>`;
}
function dayShift(value) {
    const day = String(value || '').slice(0, 10);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(day)) return null;
    const then = new Date(day + 'T12:00:00');
    const now = new Date();
    now.setHours(12, 0, 0, 0);
    return Math.round((then - now) / 86400000);
}
function ProjectForm({ project, onDone, onClose }) {
    const [company, setCompany] = useState(null);
    const logoUrl = useCompanyLogo(company?.logo ? company.updatedDate || 'logo' : '');
    const [places, setPlaces] = useState([]);
    const [categories, setCategories] = useState([]);
    const [mode, setMode] = useState(project?.attendance_location_id ? 'existing' : 'none');
    const [placeId, setPlaceId] = useState(project?.attendance_location_id ? String(project.attendance_location_id) : '');
    const [spot, setSpot] = useState({ name: '', category_id: '', category_name: '', address: '', latitude: '', longitude: '', radius_metres: '', work_start: '', work_end: '', accuracy_metres: '' });
    const [form, setForm] = useState({
        name: project?.name || '',
        location: project?.location || '',
        status: project?.status || 'ongoing',
        project_type: project?.project_type || 'Plotted layout',
        total_plots: project?.total_plots ? String(project.total_plots) : '',
        launch_on: String(project?.launch_on || '').slice(0, 10),
    });
    const [error, setError] = useState('');
    useEffect(() => {
        call('/api/v1/company').then((row) => {
            setCompany(row);
            if (!project?.id && row?.address) setForm((current) => current.location ? current : { ...current, location: row.address });
        }).catch(() => {});
        call('/api/v1/attendance/locations').then((rows) => setPlaces(Array.isArray(rows) ? rows : [])).catch(() => {});
        call('/api/v1/attendance/categories').then((rows) => setCategories(Array.isArray(rows) ? rows : [])).catch(() => {});
    }, []);
    async function submit(event) {
        event.preventDefault();
        setError('');
        try {
            const body = { ...form, total_plots: Number(form.total_plots || 0) };
            if (mode === 'new') {
                body.attendance_location = {
                    name: spot.name || form.name,
                    category_id: Number(spot.category_id || 0),
                    category_name: spot.category_name || '',
                    address: spot.address || form.location || '',
                    latitude: spot.latitude,
                    longitude: spot.longitude,
                    radius_metres: Number(spot.radius_metres || 0),
                    work_start: spot.work_start,
                    work_end: spot.work_end,
                    accuracy_metres: spot.accuracy_metres,
                    status: 'active',
                };
            } else {
                body.attendance_location_id = mode === 'existing' ? Number(placeId || 0) : 0;
            }
            const path = project?.id ? '/api/v1/projects/' + project.id : '/api/v1/projects';
            await call(path, { method: 'POST', body });
            onDone();
        } catch (e) { setError(e.message); }
    }
    const setSpotKey = (key) => (event) => setSpot({ ...spot, [key]: event.target.value });
    return html`<${FormCard} title=${project?.id ? 'Edit project' : 'Add project'} error=${error} onSubmit=${submit} onClose=${onClose}>
        ${companyMark(company, logoUrl)}
        ${field('Name', inputOf(form, 'name', setForm))}
        ${field('Location', inputOf(form, 'location', setForm))}
        ${field('Status', selectOf(form, 'status', setForm, [['upcoming', 'Upcoming'], ['ongoing', 'Ongoing'], ['completed', 'Completed']]))}
        ${field('Type', inputOf(form, 'project_type', setForm))}
        ${field('Total plots', inputOf(form, 'total_plots', setForm, 'number'))}
        ${field('Launch date', inputOf(form, 'launch_on', setForm, 'date'))}
        ${field('Attendance location', html`<select value=${mode === 'existing' ? placeId : mode} onChange=${(event) => {
            const value = event.target.value;
            if (value === 'none' || value === 'new') { setMode(value); setPlaceId(''); return; }
            setMode('existing');
            setPlaceId(value);
        }}><option value="none">No attendance location</option><option value="new">Create a location</option>${places.filter((row) => row.status === 'active' || String(row.id) === placeId).map((row) => html`<option key=${row.id} value=${String(row.id)}>${row.name}${row.category_name ? ' · ' + row.category_name : ''}</option>`)}</select>`, true)}
        ${mode === 'new' ? html`
            ${project?.attendance_location_id ? html`<p class="muted span-2">This project already has a place. Saving updates that same location.</p>` : null}
            ${field('Place name', html`<input value=${spot.name} placeholder=${form.name || 'Place name'} onInput=${setSpotKey('name')} />`)}
            ${field('Category', html`<select value=${spot.category_id} onChange=${(event) => setSpot({ ...spot, category_id: event.target.value, category_name: '' })}><option value="">New category</option>${categories.filter((row) => row.status === 'active').map((row) => html`<option key=${row.id} value=${String(row.id)}>${row.name}</option>`)}</select>`)}
            ${spot.category_id ? null : field('New category', html`<input value=${spot.category_name} onInput=${setSpotKey('category_name')} placeholder="Type a category" />`)}
            ${field('Place address', html`<input value=${spot.address} onInput=${setSpotKey('address')} />`)}
            ${field('Latitude', html`<input value=${spot.latitude} onInput=${setSpotKey('latitude')} />`)}
            ${field('Longitude', html`<input value=${spot.longitude} onInput=${setSpotKey('longitude')} />`)}
            ${field('Geofence (metres)', html`<input type="number" min="1" max="100000" step="1" value=${spot.radius_metres} onInput=${setSpotKey('radius_metres')} />`)}
            ${field('Work start', html`<input type="time" value=${spot.work_start} onInput=${setSpotKey('work_start')} />`)}
            ${field('Work end', html`<input type="time" value=${spot.work_end} onInput=${setSpotKey('work_end')} />`)}
            ${field('GPS accuracy (metres)', html`<input type="number" min="0" max="5000" step="1" value=${spot.accuracy_metres} onInput=${setSpotKey('accuracy_metres')} placeholder="Empty skips this check" />`)}
            <div class="span-2 row-actions"><button class="ghost" type="button" onClick=${async () => {
                setError('');
                try {
                    const here = await readPlace();
                    setSpot({ ...spot, latitude: String(here.lat), longitude: String(here.lng) });
                } catch (e) { setError(e.message); }
            }}>Use my current location</button></div>
            <${MapPick} lat=${spot.latitude} lng=${spot.longitude} onPick=${(lat, lng) => setSpot({ ...spot, latitude: lat, longitude: lng })} />
        ` : null}
    <//>`;
}
function SitesBoard({ start }) {
    const [tab, setTab] = useState(start || 'dashboard');
    const [projects, setProjects] = useState([]);
    const [plots, setPlots] = useState([]);
    const [people, setPeople] = useState([]);
    const [projectId, setProjectId] = useState('');
    const [text, setText] = useState('');
    const [error, setError] = useState('');
    const [edit, setEdit] = useState(null);
    const [adding, setAdding] = useState(false);
    const [projectForm, setProjectForm] = useState(null);
    const [confirm, setConfirm] = useState(null);
    const [listOpen, setListOpen] = useState(false);
    const [company, setCompany] = useState(null);
    const logoUrl = useCompanyLogo(company?.logo ? company.updatedDate || 'logo' : '');
    function load() {
        setError('');
        call('/api/v1/projects').then((rows) => setProjects(Array.isArray(rows) ? rows : [])).catch((e) => setError(e.message));
        call('/api/v1/plots').then((rows) => setPlots(Array.isArray(rows) ? rows : [])).catch((e) => setError(e.message));
        call('/api/v1/staff').then((rows) => setPeople(Array.isArray(rows) ? rows : [])).catch(() => {});
        call('/api/v1/company').then(setCompany).catch(() => {});
    }
    useEffect(() => { load(); }, []);
    useEffect(() => { setTab(start || 'dashboard'); }, [start]);
    const rows = plots.filter((row) => !projectId || String(row.project_id) === String(projectId));
    const q = text.trim().toLowerCase();
    const searched = rows.filter((row) => !q || [row.project_name, row.phase, row.block, row.plot_no, row.customer_name, row.customer_phone, row.associate_name, row.head_name, row.facing].join(' ').toLowerCase().includes(q));
    const count = (status) => rows.filter((row) => row.status === status).length;
    const available = rows.filter((row) => row.status === 'available');
    const booked = searched.filter((row) => row.status === 'booked');
    const follow = searched.filter((row) => row.registration_followup && !(row.registered_on || row.status === 'registered'));
    const done = searched.filter((row) => row.registered_on || row.status === 'registered');
    const faces = {};
    available.forEach((row) => {
        const key = row.facing || 'Other';
        if (!faces[key]) faces[key] = { count: 0, area: 0 };
        faces[key].count += 1;
        faces[key].area += Number(row.area_sqft || 0);
    });
    const summary = projects.filter((project) => !projectId || String(project.id) === String(projectId)).map((project) => {
        const mine = plots.filter((row) => String(row.project_id) === String(project.id));
        const of = (status) => mine.filter((row) => row.status === status).length;
        return { id: project.id, name: project.name, total: mine.length, available: of('available'), advance: of('advance'), booked: of('booked'), sold: of('sold'), owner: of('owner_use'), future: of('future') };
    });
    const teams = {};
    searched.forEach((row) => {
        const key = (row.head_name || '—') + '|' + (row.associate_name || '—');
        if (!teams[key]) teams[key] = { head: row.head_name || '—', associate: row.associate_name || '—', plots: 0, bookings: 0, advance: 0, amount: 0, follow: 0, advOver: 0, regOver: 0, done: 0 };
        const item = teams[key];
        item.plots += 1;
        if (['advance', 'booked', 'sold', 'registered'].includes(row.status)) item.bookings += 1;
        item.advance += Number(row.advance_minor || 0);
        item.amount += Number(row.booking_amount_minor || 0);
        if (row.registration_followup && !(row.registered_on || row.status === 'registered')) item.follow += 1;
        if (row.advance_overdue) item.advOver += 1;
        if (row.registration_overdue) item.regOver += 1;
        if (row.registered_on || row.status === 'registered') item.done += 1;
    });
    const tabs = [['dashboard', 'Dashboard'], ['available', 'Available plots'], ['booking', 'Booking'], ['follow', 'Registration follow-up'], ['done', 'Registration completed'], ['teams', 'Team-wise reports'], ['inventory', 'Plot inventory']];
    const tones = [['available', '#0ab39c'], ['advance', '#f7b84b'], ['booked', '#1F6B4A'], ['sold', '#143D2C'], ['owner_use', '#64748b'], ['future', '#299cdb']];
    function stack(source) {
        const total = Math.max(1, source.length);
        return html`<div class="stack-bar">${tones.map(([key, color]) => {
            const n = source.filter((row) => row.status === key).length;
            return n ? html`<span key=${key} style=${{ width: (n / total * 100) + '%', background: color }}></span>` : null;
        })}</div>`;
    }
    function donut() {
        const total = Math.max(1, rows.length);
        const radius = 42;
        const circ = 2 * Math.PI * radius;
        let cursor = 0;
        return html`<svg class="donut" viewBox="0 0 120 120">
            <circle cx="60" cy="60" r="42" fill="none" stroke="#eef1f6" stroke-width="14" />
            ${tones.map(([key, color]) => {
                const n = rows.filter((row) => row.status === key).length;
                const len = (n / total) * circ;
                const node = n ? html`<circle key=${key} cx="60" cy="60" r="42" fill="none" stroke=${color} stroke-width="14" stroke-dasharray=${len + ' ' + (circ - len)} stroke-dashoffset=${-cursor} transform="rotate(-90 60 60)" />` : null;
                cursor += len;
                return node;
            })}
        </svg>`;
    }
    const followCount = rows.filter((row) => row.registration_followup && !(row.registered_on || row.status === 'registered')).length;
    const selected = projects.find((project) => String(project.id) === String(projectId));
    const phase = available[0]?.phase || available[0]?.block || rows[0]?.phase || '—';
    return html`<div class="sites">
        ${error ? html`<p class="error">${error}</p>` : null}
        ${companyMark(company, logoUrl)}
        <div class="site-tools">
            <label>Project<select value=${projectId} onChange=${(event) => setProjectId(event.target.value)}><option value="">All projects</option>${projects.map((project) => html`<option key=${project.id} value=${String(project.id)}>${project.name}</option>`)}</select></label>
            <button class="primary" type="button" onClick=${() => setProjectForm({})}>Add project</button>
            ${selected ? html`<button class="ghost" type="button" onClick=${() => setProjectForm(selected)}>Edit project</button>` : null}
            ${selected ? html`<button class="ghost" type="button" onClick=${() => setConfirm({ kind: 'project', row: selected })}>Delete project</button>` : null}
            <button class="ghost" type="button" onClick=${load}>Refresh</button>
            ${tab !== 'dashboard' ? html`<input class="site-search" placeholder="Search project, plot, customer" value=${text} onInput=${(event) => setText(event.target.value)} />` : null}
        </div>
        <div class="site-tabs">
            ${tabs.map(([id, label]) => html`<button key=${id} type="button" class=${tab === id ? 'on' : ''} onClick=${() => setTab(id)}>${label}</button>`)}
        </div>
        ${tab === 'dashboard' ? html`<div class="site-stack">
            <section class="panel-card">
                <div class="card-head"><div><h3>Available plots</h3><p>Plots currently marked available${selected ? ' · ' + selected.name : ''}.</p></div><button class="linkish" type="button" onClick=${() => setTab('available')}>View all available plots</button></div>
                <div class="avail-card">
                    <div>
                        <strong>${selected ? selected.name : 'All projects'}</strong>
                        <p>Phase ${phase}</p>
                        <div class="facing-pills">${Object.keys(faces).length === 0 ? html`<span>No available plots</span>` : Object.entries(faces).map(([name, item]) => html`<div key=${name}><b>${name}</b><strong>${item.count}</strong><em>${Math.round(item.area).toLocaleString('en-IN')} sq.ft.</em></div>`)}</div>
                        <button class="linkish" type="button" onClick=${() => setListOpen(true)}>View all available plots</button>
                    </div>
                    <div class="avail-count">${available.length}<span>Available plots</span></div>
                </div>
            </section>
            <section>
                <h3 class="site-title">Business overview</h3>
                <div class="metric-grid">
                    ${[['Total plots', rows.length, 'Visible inventory', ''], ['Available', count('available'), 'Available for sale', 'ok'], ['Advance / token', count('advance'), 'Advance stage', 'warn'], ['Booked', count('booked'), 'Booked plots', 'info'], ['Sold', count('sold'), 'Sold plots', 'ink'], ['Owner use', count('owner_use'), 'Owner-use inventory', ''], ['Future plots', count('future'), 'Future inventory', ''], ['Registration follow-up', followCount, 'Requires follow-up', 'late'], ['Advance overdue', rows.filter((row) => row.advance_overdue).length, 'Booking older than 4 days', 'late'], ['Registration overdue', rows.filter((row) => row.registration_overdue).length, 'Past the 40-day deadline', 'late']].map(([label, value, note, tone]) => html`<article key=${label} class=${'metric-tile ' + tone}><span>${label}</span><b>${value}</b><em>${note}</em></article>`)}
                </div>
            </section>
            <section class="site-split">
                <article class="panel-card">
                    <h3>Project overview</h3>
                    ${summary.map((project) => html`<div key=${project.id} class="overview-line"><strong>${project.name}</strong><b>${project.total}</b>${stack(plots.filter((row) => String(row.project_id) === String(project.id)))}</div>`)}
                    <div class="legend">${tones.map(([key, color]) => html`<span key=${key}><i style=${{ background: color }}></i>${PLOT_LABEL[key]}</span>`)}</div>
                </article>
                <article class="panel-card donut-card">
                    <h3>Selected inventory status</h3>
                    ${donut()}
                    <div class="legend">${tones.map(([key, color]) => html`<span key=${key}><i style=${{ background: color }}></i>${PLOT_LABEL[key]} ${count(key)}</span>`)}</div>
                </article>
            </section>
            <section class="panel-card">
                <h3>Project-wise plot status</h3>
                ${summary.map((project) => html`<div key=${project.id} class="bar-project"><span>${project.name}</span>${stack(plots.filter((row) => String(row.project_id) === String(project.id)))}<b>${project.total}</b></div>`)}
            </section>
            <section class="panel-card">
                <h3>Project summary</h3>
                <div class="table-card"><table class="board-table"><thead><tr><th>Project</th><th>Total</th><th>Available</th><th>Advance</th><th>Booked</th><th>Sold</th><th>Owner use</th><th>Future</th></tr></thead><tbody>
                    ${summary.map((project) => html`<tr key=${project.id}><td>${project.name}</td><td>${project.total}</td><td>${project.available}</td><td>${project.advance}</td><td>${project.booked}</td><td>${project.sold}</td><td>${project.owner}</td><td>${project.future}</td></tr>`)}
                </tbody></table></div>
            </section>
        </div>` : null}
        ${tab === 'available' ? html`<section class="panel-card"><h3>Available plots</h3><p>Use Book on a plot to enter the customer and booking.</p><${PlotTable} rows=${searched.filter((row) => row.status === 'available')} mode="available" onEdit=${setEdit} /></section>` : null}
        ${tab === 'booking' ? html`<section class="panel-card"><h3>Booking register</h3><p>Edit is where the customer, advance, booking amount, and registration dates are typed.</p><${PlotTable} rows=${booked} mode="booking" onEdit=${setEdit} /></section>` : null}
        ${tab === 'follow' ? html`<section class="panel-card"><h3>Registration follow-up</h3><p>These rows appear when an advance is saved and the actual registration date is still empty. Edit the same booking to set the date.</p><${PlotTable} rows=${follow} mode="follow" onEdit=${setEdit} /></section>` : null}
        ${tab === 'done' ? html`<section class="panel-card"><h3>Registration completed</h3><p>A plot lands here after the actual registration date is saved on the booking.</p><${PlotTable} rows=${done} mode="done" onEdit=${setEdit} /></section>` : null}
        ${tab === 'teams' ? html`<section class="panel-card"><h3>Team-wise and associate performance</h3><p>This table is counted from bookings. The associate chosen on the booking supplies the team head. There is no separate form.</p><div class="table-card"><table class="board-table"><thead><tr><th>Team head</th><th>Associate</th><th>Total plots</th><th>Booking cases</th><th>Advance</th><th>Booking amount</th><th>Follow-up</th><th>Advance overdue</th><th>Registration overdue</th><th>Completed</th></tr></thead><tbody>
            ${Object.values(teams).map((item) => html`<tr key=${item.head + item.associate}><td>${item.head}</td><td>${item.associate}</td><td>${item.plots}</td><td>${item.bookings}</td><td>${money(item.advance)}</td><td>${money(item.amount)}</td><td>${item.follow}</td><td>${item.advOver}</td><td>${item.regOver}</td><td>${item.done}</td></tr>`)}
        </tbody></table></div></section>` : null}
        ${tab === 'inventory' ? html`<section class="panel-card"><div class="card-head"><h3>Plot inventory</h3><button class="primary" type="button" onClick=${() => setAdding(true)}>Add plot</button></div><${PlotTable} rows=${searched} mode="inventory" onEdit=${setEdit} onDelete=${(row) => setConfirm({ kind: 'plot', row })} /></section>` : null}
        ${listOpen ? html`<div class="modal-back" onClick=${() => setListOpen(false)}><div class="filter-box wide" onClick=${(event) => event.stopPropagation()}><header><h2>Available plots</h2><button class="filter-x" type="button" onClick=${() => setListOpen(false)}>×</button></header><div class="filter-body"><${PlotTable} rows=${available} mode="available" /></div></div></div>` : null}
        ${edit ? html`<${BookingForm} plot=${edit} people=${people} onDone=${() => { setEdit(null); load(); }} onClose=${() => setEdit(null)} />` : null}
        ${adding ? html`<${PlotForm} projects=${projects} onDone=${() => { setAdding(false); load(); }} onClose=${() => setAdding(false)} />` : null}
        ${projectForm ? html`<${ProjectForm} project=${projectForm.id ? projectForm : null} onDone=${() => { setProjectForm(null); load(); }} onClose=${() => setProjectForm(null)} />` : null}
        ${confirm ? confirmDelete(confirm.kind === 'project' ? confirm.row.name : 'plot ' + (confirm.row.plot_no || ''), error, async (event) => {
            event.preventDefault();
            setError('');
            try {
                const path = confirm.kind === 'project' ? '/api/v1/projects/' + confirm.row.id + '/delete' : '/api/v1/plots/' + confirm.row.id + '/delete';
                await call(path, { method: 'POST', body: {} });
                if (confirm.kind === 'project') setProjectId('');
                setConfirm(null);
                load();
            } catch (e) { setError(e.message); }
        }, () => { setConfirm(null); setError(''); }) : null}
    </div>`;
}
function PlotTable({ rows, mode, onEdit, onDelete }) {
    if (!rows.length) return html`<p class="empty-line">Nothing in this view.</p>`;
    return html`<div class="table-card"><table class="board-table"><thead><tr>
        <th>Project</th><th>Phase</th><th>Plot no</th><th>Commission</th>
        ${mode === 'available' ? html`<th>Status</th><th>Registration</th>${onEdit ? html`<th></th>` : null}` : null}
        ${mode === 'booking' ? html`<th>Customer</th><th>Contact</th><th>Associate</th><th>Team head</th><th>Status</th><th>Booking date</th><th>Advance</th><th>Advance deadline</th><th>Deadline</th><th>Booking amount</th><th>Balance</th><th></th>` : null}
        ${mode === 'follow' ? html`<th>Customer</th><th>Contact</th><th>Associate</th><th>Team head</th><th>Advance</th><th>Advance received</th><th>Deadline</th><th>Days left</th><th>Status</th><th>Registration</th><th>Expected</th><th>Remarks</th>${onEdit ? html`<th></th>` : null}` : null}
        ${mode === 'done' ? html`<th>Customer</th><th>Contact</th><th>Associate</th><th>Team head</th><th>Status</th><th>Registration</th><th>Expected</th><th>Actual</th><th>Remarks</th>${onEdit ? html`<th></th>` : null}` : null}
        ${mode === 'inventory' ? html`<th>Facing</th><th>Area</th><th>Status</th><th>Customer</th><th>Associate</th><th>Team head</th><th>Registration</th><th>Expected</th><th></th>` : null}
    </tr></thead><tbody>
        ${rows.map((row) => html`<tr key=${row.id}>
            <td>${row.project_name || '—'}</td>
            <td>${row.phase || row.block || '—'}</td>
            <td>${row.plot_no || '—'}</td>
            <td>${money(row.commission_minor)}</td>
            ${mode === 'available' ? html`<td>${plotTag(row.status)}</td><td>${regCell(row)}</td>${onEdit ? html`<td><button class="ghost" type="button" onClick=${() => onEdit(row)}>Book</button></td>` : null}` : null}
            ${mode === 'booking' ? html`<td>${row.customer_name || '—'}</td><td>${row.customer_phone || '—'}</td><td>${row.associate_name || '—'}</td><td>${row.head_name || '—'}</td><td>${plotTag(row.status)}</td><td>${prettyDate(row.booking_on)}</td><td>${money(row.advance_minor)}</td><td>${prettyDate(row.advance_deadline)}</td><td>${deadlineCell(row)}</td><td>${money(row.booking_amount_minor)}</td><td>${money(row.balance_minor)}</td><td><button class="ghost" type="button" onClick=${() => onEdit(row)}>Edit</button></td>` : null}
            ${mode === 'follow' ? html`<td>${row.customer_name || '—'}</td><td>${row.customer_phone || '—'}</td><td>${row.associate_name || '—'}</td><td>${row.head_name || '—'}</td><td>${money(row.advance_minor)}</td><td>${prettyDate(row.advance_received_on)}</td><td>${prettyDate(row.registration_deadline)}</td><td>${leftCell(row)}</td><td>${plotTag(row.status)}</td><td>${regCell(row)}</td><td>${prettyDate(row.expected_registration_on)}</td><td>${row.remarks || '—'}</td>${onEdit ? html`<td><button class="ghost" type="button" onClick=${() => onEdit(row)}>Edit</button></td>` : null}` : null}
            ${mode === 'done' ? html`<td>${row.customer_name || '—'}</td><td>${row.customer_phone || '—'}</td><td>${row.associate_name || '—'}</td><td>${row.head_name || '—'}</td><td>${plotTag(row.status)}</td><td>${regCell(row)}</td><td>${prettyDate(row.expected_registration_on)}</td><td>${prettyDate(row.registered_on)}</td><td>${row.remarks || '—'}</td>${onEdit ? html`<td><button class="ghost" type="button" onClick=${() => onEdit(row)}>Edit</button></td>` : null}` : null}
            ${mode === 'inventory' ? html`<td>${row.facing || '—'}</td><td>${row.area_sqft || '—'}</td><td>${plotTag(row.status)}</td><td>${row.customer_name || '—'}</td><td>${row.associate_name || '—'}</td><td>${row.head_name || '—'}</td><td>${regCell(row)}</td><td>${prettyDate(row.expected_registration_on)}</td><td class="row-actions"><button class="ghost" type="button" onClick=${() => onEdit(row)}>Edit</button>${onDelete ? html`<button class="ghost" type="button" onClick=${() => onDelete(row)}>Delete</button>` : null}</td>` : null}
        </tr>`)}
    </tbody></table></div>`;
}
function deadlineCell(row) {
    if (!row.booking_on) return html`<span class="tag muted">Booking date missing</span>`;
    if (row.advance_overdue) return html`<span class="tag stop">Overdue ${Math.abs(dayShift(row.advance_deadline) || 0)} days</span>`;
    return prettyDate(row.advance_deadline);
}
function leftCell(row) {
    if (row.days_left == null) return '—';
    if (Number(row.days_left) < 0) return html`<span class="tag stop">${Math.abs(row.days_left)} days overdue</span>`;
    return html`<span class="tag ok">${row.days_left} days left</span>`;
}
function regCell(row) {
    if (row.registered_on || row.status === 'registered') return html`<span class="tag info">Completed</span>`;
    if (row.status === 'available') return 'Available plots';
    if (row.registration_followup) return html`<span class="tag warn">Pending</span>`;
    return '—';
}
function BookingForm({ plot, people, onDone, onClose }) {
    const [leads, setLeads] = useState([]);
    const [form, setForm] = useState({
        status: plot.status || 'booked',
        customerName: plot.customer_name || '',
        customerPhone: plot.customer_phone || '',
        leadId: '',
        associateUserId: plot.associate_user_id ? String(plot.associate_user_id) : '',
        bookingOn: String(plot.booking_on || '').slice(0, 10),
        advance: plot.advance_minor ? String(Math.round(Number(plot.advance_minor) / 100)) : '',
        advanceReceivedOn: String(plot.advance_received_on || '').slice(0, 10),
        bookingAmount: plot.booking_amount_minor ? String(Math.round(Number(plot.booking_amount_minor) / 100)) : '',
        commission: Number(plot.commission_minor) > 0 ? String(Math.round(Number(plot.commission_minor) / 100)) : '',
        expectedRegistrationOn: String(plot.expected_registration_on || '').slice(0, 10),
        registeredOn: String(plot.registered_on || '').slice(0, 10),
        remarks: plot.remarks || '',
    });
    const [error, setError] = useState('');
    useEffect(() => { call('/api/v1/leads').then((rows) => setLeads(Array.isArray(rows) ? rows : [])).catch(() => {}); }, []);
    const set = (key) => (event) => setForm({ ...form, [key]: event.target.value });
    const sale = form.status === 'booked' || form.status === 'sold' || form.status === 'registered';
    async function submit(event) {
        event.preventDefault();
        setError('');
        if (!String(form.commission).trim() || Number(form.commission) <= 0) { setError('Enter the commission for this plot.'); return; }
        if (sale && !form.associateUserId) { setError('Choose the associate. The incentive is added for that person.'); return; }
        if (sale && !form.leadId) { setError('Choose the lead. The incentive is added when the plot is booked, sold, or registered.'); return; }
        try {
            await call('/api/v1/plots/' + plot.id + '/booking', { method: 'POST', body: { ...form, leadId: Number(form.leadId || 0), associateUserId: Number(form.associateUserId || 0) } });
            onDone();
        } catch (e) { setError(e.message); }
    }
    return sheet('Edit booking', error, submit, onClose, html`
        ${xrow('Status', html`<select value=${form.status} onChange=${set('status')}>${Object.entries(PLOT_LABEL).map(([value, label]) => html`<option key=${value} value=${value}>${label}</option>`)}</select>`, true)}
        ${xrow('Customer', html`<input value=${form.customerName} onInput=${set('customerName')} />`, true)}
        ${xrow('Contact', html`<input value=${form.customerPhone} onInput=${set('customerPhone')} />`)}
        ${xrow('Lead', html`<select value=${form.leadId} onChange=${set('leadId')}><option value="">Choose</option>${leads.map((row) => html`<option key=${row.id} value=${String(row.id)}>${row.lead_name || row.name || row.phone_number}</option>`)}</select>`, sale)}
        ${xrow('Associate', html`<select value=${form.associateUserId} onChange=${set('associateUserId')}><option value="">Choose</option>${people.map((row) => html`<option key=${row.user_id || row.id} value=${String(row.user_id || row.id)}>${row.name}</option>`)}</select>`, sale)}
        ${xrow('Booking date', html`<input type="date" value=${form.bookingOn} onInput=${set('bookingOn')} />`)}
        ${xrow('Advance', html`<input type="number" min="0" value=${form.advance} onInput=${set('advance')} />`)}
        ${xrow('Advance received', html`<input type="date" value=${form.advanceReceivedOn} onInput=${set('advanceReceivedOn')} />`)}
        ${xrow('Booking amount', html`<input type="number" min="0" value=${form.bookingAmount} onInput=${set('bookingAmount')} />`)}
        ${xrow('Commission', html`<input type="number" min="1" value=${form.commission} onInput=${set('commission')} />`, true)}
        ${xrow('Expected registration', html`<input type="date" value=${form.expectedRegistrationOn} onInput=${set('expectedRegistrationOn')} />`)}
        ${xrow('Actual registration', html`<input type="date" value=${form.registeredOn} onInput=${set('registeredOn')} />`)}
        ${xrow('Remarks', html`<textarea value=${form.remarks} onInput=${set('remarks')}></textarea>`, false, true)}
    `, true, 'Save');
}
function PlotForm({ projects, onDone, onClose }) {
    const [form, setForm] = useState({ project_id: projects[0] ? String(projects[0].id) : '', phase: '', plot_no: '', facing: '', area_sqft: '', commission: '', status: 'available' });
    const [error, setError] = useState('');
    const set = (key) => (event) => setForm({ ...form, [key]: event.target.value });
    async function submit(event) {
        event.preventDefault();
        setError('');
        if (!String(form.commission).trim() || Number(form.commission) <= 0) { setError('Enter the commission for this plot.'); return; }
        try {
            await call('/api/v1/plots', { method: 'POST', body: { ...form, project_id: Number(form.project_id) } });
            onDone();
        } catch (e) { setError(e.message); }
    }
    return sheet('Add plot', error, submit, onClose, html`
        ${xrow('Project', html`<select value=${form.project_id} onChange=${set('project_id')}>${projects.map((project) => html`<option key=${project.id} value=${String(project.id)}>${project.name}</option>`)}</select>`, true)}
        ${xrow('Phase', html`<input value=${form.phase} onInput=${set('phase')} />`)}
        ${xrow('Plot no', html`<input value=${form.plot_no} onInput=${set('plot_no')} />`, true)}
        ${xrow('Facing', html`<input value=${form.facing} onInput=${set('facing')} />`)}
        ${xrow('Area', html`<input value=${form.area_sqft} onInput=${set('area_sqft')} />`)}
        ${xrow('Commission', html`<input type="number" min="1" value=${form.commission} onInput=${set('commission')} />`, true)}
        ${xrow('Status', html`<select value=${form.status} onChange=${set('status')}>${['available', 'hold', 'owner_use', 'future', 'blocked'].map((value) => html`<option key=${value} value=${value}>${PLOT_LABEL[value]}</option>`)}</select>`, true)}
    `, true, 'Add');
}
function StaffForm({ person, onDone, onClose }) {
    const [form, setForm] = useState({ phone: person.phone_number || person.phone || '', status: person.status || 'active', team_id: person.team_id ? String(person.team_id) : '' });
    const [teams, setTeams] = useState([]);
    const [error, setError] = useState('');
    useEffect(() => { call('/api/v1/teams').then((rows) => setTeams(Array.isArray(rows) ? rows : [])).catch(() => {}); }, []);
    async function submit(event) {
        event.preventDefault();
        setError('');
        try {
            await call('/api/v1/staff/' + (person.user_id || person.id), { method: 'POST', body: { phone: form.phone, status: form.status, team_id: Number(form.team_id || 0), attendance_applicable: person.attendance_applicable ?? 1, lead_eligible: person.lead_eligible ?? 1, incentive_eligible: person.incentive_eligible ?? 1 } });
            onDone();
        } catch (e) { setError(e.message); }
    }
    return html`<${FormCard} title=${'Edit ' + (person.name || 'user')} error=${error} onSubmit=${submit} onClose=${onClose}>
        ${field('Phone', inputOf(form, 'phone', setForm, 'tel'))}
        ${field('Status', selectOf(form, 'status', setForm, [['active', 'Active'], ['inactive', 'Inactive'], ['relieved', 'Relieved']]))}
        ${field('Team', selectOf(form, 'team_id', setForm, [['', 'None'], ...teams.map((row) => [String(row.id), row.name])]), true)}
    <//>`;
}
function TeamForm({ team, onDone, onClose }) {
    const [form, setForm] = useState({ name: team?.name || '', head_user_id: team?.head_user_id ? String(team.head_user_id) : '', member_ids: (team?.member_ids || []).map(String), project_ids: (team?.project_ids || []).map(String) });
    const [people, setPeople] = useState([]);
    const [projects, setProjects] = useState([]);
    const [error, setError] = useState('');
    useEffect(() => {
        call('/api/v1/staff').then((rows) => setPeople(Array.isArray(rows) ? rows : [])).catch(() => {});
        call('/api/v1/projects').then((rows) => setProjects(Array.isArray(rows) ? rows : [])).catch(() => {});
    }, []);
    function toggle(key, id) {
        const current = form[key];
        setForm({ ...form, [key]: current.includes(id) ? current.filter((item) => item !== id) : [...current, id] });
    }
    async function submit(event) {
        event.preventDefault();
        setError('');
        try {
            const path = team?.id ? '/api/v1/teams/' + team.id : '/api/v1/teams';
            await call(path, { method: 'POST', body: { name: form.name, head_user_id: Number(form.head_user_id || 0), member_ids: form.member_ids.map(Number), project_ids: form.project_ids.map(Number) } });
            onDone();
        } catch (e) { setError(e.message); }
    }
    return html`<${FormCard} title=${team?.id ? 'Edit team' : 'Add team'} error=${error} onSubmit=${submit} onClose=${onClose}>
        ${field('Team', inputOf(form, 'name', setForm), true)}
        ${field('Head', selectOf(form, 'head_user_id', setForm, [['', 'Choose'], ...people.map((row) => [String(row.user_id), row.name])]), true)}
        <div class="span-2"><span class="req">Projects</span><div class="pick-list">${projects.map((row) => html`<label key=${row.id}><input type="checkbox" checked=${form.project_ids.includes(String(row.id))} onChange=${() => toggle('project_ids', String(row.id))} />${row.name}</label>`)}</div></div>
        <div class="span-2 pick-list">${people.map((row) => html`<label key=${row.user_id}><input type="checkbox" checked=${form.member_ids.includes(String(row.user_id))} onChange=${() => toggle('member_ids', String(row.user_id))} />${row.name}</label>`)}</div>
    <//>`;
}
function TemplateForm({ onDone, onClose }) {
    const [form, setForm] = useState({ name: '', language: 'en', body: '' });
    const [error, setError] = useState('');
    async function submit(event) {
        event.preventDefault();
        setError('');
        try {
            await call('/api/v1/whatsapp/templates', { method: 'POST', body: { ...form, active: 1 } });
            onDone();
        } catch (e) { setError(e.message); }
    }
    return html`<${FormCard} title="Add template" error=${error} onSubmit=${submit} onClose=${onClose}>
        ${field('Name', inputOf(form, 'name', setForm))}
        ${field('Language', inputOf(form, 'language', setForm))}
        ${field('Message', html`<textarea value=${form.body} onInput=${(event) => setForm({ ...form, body: event.target.value })}></textarea>`, true)}
    <//>`;
}
function Resource({ title, lede, spec }) {
    const [rows, setRows] = useState([]);
    const [error, setError] = useState('');
    const [picked, setPicked] = useState(null);
    const [extra, setExtra] = useState('');
    const [composer, setComposer] = useState('');
    const [editing, setEditing] = useState(null);
    const [confirm, setConfirm] = useState(null);
    const [detail, setDetail] = useState(null);
    const actions = spec.actions || [];
    function load() {
        call(spec.path).then((data) => setRows(Array.isArray(data) ? data : [])).catch((e) => setError(e.message));
    }
    useEffect(() => { setDetail(null); load(); }, [spec.path]);
    async function more(kind) {
        if (!picked) return;
        setError('');
        try {
            if (kind === 'timeline') setExtra(JSON.stringify(await call('/api/v1/leads/' + picked.id + '/timeline'), null, 2));
            if (kind === 'history') setExtra(JSON.stringify(await call('/api/v1/plots/' + picked.id + '/history'), null, 2));
            if (kind === 'team') setExtra(JSON.stringify(await call('/api/v1/teams/' + picked.id + '/history'), null, 2));
            if (kind === 'project') setExtra(JSON.stringify(await call('/api/v1/projects/' + picked.id + '/dashboard'), null, 2));
            if (kind === 'open') setExtra(JSON.stringify(await call('/api/v1/staff/' + (picked.user_id || picked.id) + '/open-work'), null, 2));
        } catch (e) {
            setError(e.message);
        }
    }
    function saved() { setComposer(''); setEditing(null); setError(''); load(); }
    function open(kind, row) {
        if ((kind === 'assign' || kind === 'role-edit') && !row && !picked) { setError('Select a row first.'); return; }
        setError('');
        setEditing(row || null);
        setComposer(kind);
    }
    async function remove(event) {
        event.preventDefault();
        if (!confirm) return;
        setError('');
        try {
            const id = confirm.id;
            const path = spec.path.includes('roles') ? '/api/v1/roles/' + id + '/delete' : spec.path.includes('teams') ? '/api/v1/teams/' + id + '/delete' : '/api/v1/leads/' + id + '/delete';
            await call(path, { method: 'POST', body: {} });
            setConfirm(null);
            setPicked(null);
            load();
        } catch (e) { setError(e.message); }
    }
    if (detail) return html`<${LeadPage} leadId=${detail.id} onBack=${() => { setDetail(null); load(); }} />`;
    return html`<section class="module-list">
        ${actions.length ? html`<div class="toolbar">
            ${actions.includes('lead') ? html`<button class="primary" onClick=${() => open('lead')}>New lead</button>` : null}
            ${actions.includes('followup') ? html`<button class="primary" onClick=${() => open('followup')}>New follow-up</button>` : null}
            ${actions.includes('visit') ? html`<button class="primary" onClick=${() => open('visit')}>New visit</button>` : null}
            ${actions.includes('task') ? html`<button class="primary" onClick=${() => open('task')}>New task</button>` : null}
            ${actions.includes('assign') ? html`<button class="ghost" onClick=${() => open('assign')}>Assign lead</button>` : null}
            ${actions.includes('user') ? html`<button class="primary" onClick=${() => open('user')}>Add user</button>` : null}
            ${actions.includes('role') ? html`<button class="primary" onClick=${() => open('role')}>Add role</button>` : null}
            ${actions.includes('team') ? html`<button class="primary" onClick=${() => open('team')}>Add team</button>` : null}
            ${actions.includes('template') ? html`<button class="primary" onClick=${() => open('template')}>Add template</button>` : null}
        </div>` : null}
        ${error ? html`<p class="error">${error}</p>` : null}
        ${composer === 'lead' ? html`<${LeadForm} lead=${editing} onDone=${saved} onClose=${() => setComposer('')} />` : null}
        ${composer === 'followup' ? html`<${FollowForm} leadId=${spec.path.includes('/leads') ? picked?.id : ''} onDone=${saved} onClose=${() => setComposer('')} />` : null}
        ${composer === 'visit' ? html`<${VisitForm} leadId=${spec.path.includes('/leads') ? picked?.id : ''} onDone=${saved} onClose=${() => setComposer('')} />` : null}
        ${composer === 'task' ? html`<${TaskForm} leadId=${spec.path.includes('/leads') ? picked?.id : ''} onDone=${saved} onClose=${() => setComposer('')} />` : null}
        ${composer === 'assign' && picked ? html`<${AssignForm} lead=${picked} onDone=${saved} onClose=${() => setComposer('')} />` : null}
        ${composer === 'user' ? html`<${UserForm} onDone=${saved} onClose=${() => setComposer('')} />` : null}
        ${composer === 'staff-edit' && editing ? html`<${StaffForm} person=${editing} onDone=${saved} onClose=${() => setComposer('')} />` : null}
        ${composer === 'team' ? html`<${TeamForm} team=${editing} onDone=${saved} onClose=${() => setComposer('')} />` : null}
        ${composer === 'template' ? html`<${TemplateForm} onDone=${saved} onClose=${() => setComposer('')} />` : null}
        ${composer === 'role' ? html`<${RoleForm} onDone=${saved} onClose=${() => setComposer('')} />` : null}
        ${composer === 'role-edit' && (editing || picked) ? html`<${RoleForm} role=${editing || picked} onDone=${saved} onClose=${() => setComposer('')} />` : null}
        ${confirm ? confirmDelete(confirm.name || 'this record', error, remove, () => { setConfirm(null); setError(''); }) : null}
        ${spec.kind ? html`<${Board} kind=${spec.kind} title=${title} rows=${rows} onReload=${load} onRow=${setPicked} onOpen=${spec.kind === 'leads' ? setDetail : null} onEdit=${spec.kind === 'leads' ? (row) => open('lead', row) : null} onDelete=${spec.kind === 'leads' ? setConfirm : null} active=${picked} />` : html`<${Grid} title=${title} note=${lede} columns=${spec.columns} rows=${rows} onRow=${setPicked} active=${picked} onEdit=${actions.includes('staff-edit') ? (row) => open('staff-edit', row) : actions.includes('role') ? (row) => open('role-edit', row) : actions.includes('team') ? (row) => open('team', row) : null} onDelete=${actions.includes('role-delete') || actions.includes('team-delete') ? setConfirm : null} />`}
        ${picked ? html`<div class="row-actions" style=${{ marginTop: '12px' }}>
            ${spec.path.includes('leads') ? html`<button class="ghost" onClick=${() => more('timeline')}>Timeline</button>` : null}
            ${spec.path.includes('plots') ? html`<button class="ghost" onClick=${() => more('history')}>Status history</button>` : null}
            ${spec.path.includes('teams') ? html`<button class="ghost" onClick=${() => more('team')}>Membership history</button>` : null}
            ${spec.path.includes('projects') ? html`<button class="ghost" onClick=${() => more('project')}>Dashboard</button>` : null}
            ${spec.path.includes('staff') ? html`<button class="ghost" onClick=${() => more('open')}>Open leads</button>` : null}
        </div>` : null}
        ${extra ? html`<pre>${extra}</pre>` : null}
    </section>`;
}

function money(minor) {
    return '₹' + Math.round((Number(minor) || 0) / 100).toLocaleString('en-IN');
}
function dayKey(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
}
function stampParts(value) {
    const match = String(value || '').match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}):(\d{2})/);
    if (!match) return null;
    return { day: match[1], hour: Number(match[2]), minute: match[3] };
}
function clock(value) {
    const parts = stampParts(value);
    if (!parts) return '';
    const suffix = parts.hour >= 12 ? 'PM' : 'AM';
    const hour = parts.hour % 12 || 12;
    return String(hour).padStart(2, '0') + ':' + parts.minute + ' ' + suffix;
}
function slotRange(value) {
    const parts = stampParts(value);
    if (!parts) return '';
    const start = new Date(parts.day + 'T' + String(parts.hour).padStart(2, '0') + ':' + parts.minute + ':00');
    const end = new Date(start.getTime() + 30 * 60 * 1000);
    const endStamp = parts.day + ' ' + String(end.getHours()).padStart(2, '0') + ':' + String(end.getMinutes()).padStart(2, '0');
    return clock(value) + ' to ' + clock(endStamp);
}
function initials(name) {
    const parts = String(name || '?').trim().split(/\s+/).filter(Boolean);
    if (parts.length > 1) return (parts[0][0] + parts[1][0]).toUpperCase();
    return String(parts[0] || '?').slice(0, 2).toUpperCase();
}
function Intake({ points }) {
    const width = 420;
    const max = Math.max(1, ...points.map((point) => Number(point.total) || 0));
    const step = points.length > 1 ? (width - 16) / (points.length - 1) : 0;
    const coords = points.map((point, index) => [8 + index * step, 16 + (1 - (Number(point.total) || 0) / max) * 120]);
    const line = coords.map((pair) => pair.map((n) => n.toFixed(1)).join(',')).join(' ');
    const last = coords.length ? coords[coords.length - 1][0].toFixed(1) : '8';
    return html`<svg class="area" viewBox="0 0 420 160" role="img" aria-label="Lead intake">
        <defs><linearGradient id="intake-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#f7b84b" stop-opacity="0.45"/><stop offset="100%" stop-color="#f7b84b" stop-opacity="0.02"/></linearGradient></defs>
        <polygon points=${`8,148 ${line} ${last},148`} fill="url(#intake-fill)"/>
        <polyline points=${line} fill="none" stroke="#f7b84b" strokeWidth="2.5" strokeLinejoin="round" strokeLinecap="round"/>
    </svg>`;
}
function hourLabel(hour) {
    return (hour % 12 || 12) + (hour >= 12 ? ' PM' : ' AM');
}
function bookingSlots(visits, followups) {
    const items = [];
    visits.forEach((row) => {
        const at = row.scheduled_date_time || row.scheduled_at;
        const parts = stampParts(at);
        if (!parts) return;
        items.push({ key: 'v' + row.id, day: parts.day, hour: parts.hour, kind: 'Visit', title: row.lead_name || 'Visit', when: clock(at), note: [row.project_name, row.display_status || row.status].filter(Boolean).join(' · ') });
    });
    followups.forEach((row) => {
        const at = row.call_scheduled_at || row.next_at;
        const parts = stampParts(at);
        if (!parts) return;
        items.push({ key: 'f' + row.id, day: parts.day, hour: parts.hour, kind: 'Follow-up', title: row.lead_name || 'Follow-up', when: clock(at), note: row.body || row.followup_type || row.display_status || '' });
    });
    return items;
}
function Heatmap({ visits, followups }) {
    const [pick, setPick] = useState(null);
    const days = [];
    for (let offset = 6; offset >= 0; offset -= 1) {
        const date = new Date();
        date.setHours(12, 0, 0, 0);
        date.setDate(date.getDate() - offset);
        days.push(date);
    }
    const hours = [9, 10, 11, 12, 13, 14, 15, 16, 17, 18];
    const items = bookingSlots(visits, followups);
    const counts = {};
    items.forEach((item) => {
        if (item.hour < 9 || item.hour > 18) return;
        const key = item.day + '-' + item.hour;
        counts[key] = (counts[key] || 0) + 1;
    });
    const max = Math.max(1, ...Object.values(counts), 0);
    const names = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
    const picked = pick ? items.filter((item) => item.day === pick.day && item.hour === pick.hour) : [];
    const popup = pick ? html`<div class="modal-back heat-modal" onClick=${() => setPick(null)}>
            <div class="filter-box" onClick=${(event) => event.stopPropagation()}>
                <header><h2>${pick.label}</h2><button class="filter-x" type="button" onClick=${() => setPick(null)} aria-label="Close">×</button></header>
                <div class="filter-body heat-pop">
                    ${picked.length === 0 ? html`<p>Nothing is booked in this hour.</p>` : picked.map((item) => html`<article key=${item.key}><strong>${item.title}</strong><em>${item.kind}${item.when ? ' · ' + item.when : ''}</em><p>${item.note || '—'}</p></article>`)}
                </div>
            </div>
        </div>` : null;
    return html`<div class="heat-wrap">
    <div class="heat" role="img" aria-label="Visits and follow-ups by day and hour">
        <span></span>
        ${days.map((date) => html`<span class="heat-head" key=${dayKey(date)}>${names[date.getDay()]}<b>${date.getDate()}</b></span>`)}
        ${hours.map((hour) => html`<div class="heat-row" key=${hour}>
            <span class="heat-label">${hourLabel(hour)}</span>
            ${days.map((date) => {
                const day = dayKey(date);
                const count = counts[day + '-' + hour] || 0;
                const tone = count === 0 ? 0 : 0.16 + (count / max) * 0.84;
                return html`<button type="button" key=${day + hour} class="heat-cell" title=${count + ' booked'} style=${{ background: count ? 'rgba(31, 107, 74, ' + tone.toFixed(2) + ')' : '#f3f6f9' }} onClick=${() => setPick({ day, hour, label: names[date.getDay()] + ' ' + date.getDate() + ' · ' + hourLabel(hour) })}></button>`;
            })}
        </div>`)}
    </div>
    ${popup ? createPortal(popup, document.body) : null}
    </div>`;
}
function Home({ onOpen }) {
    const [board, setBoard] = useState(null);
    const [leads, setLeads] = useState([]);
    const [visits, setVisits] = useState([]);
    const [followups, setFollowups] = useState([]);
    const [error, setError] = useState('');
    const [day, setDay] = useState(() => dayKey(new Date()));
    const [page, setPage] = useState(0);
    useEffect(() => {
        Promise.all([
            call('/api/v1/reports'),
            call('/api/v1/leads').catch(() => []),
            call('/api/v1/visits').catch(() => []),
            call('/api/v1/followups').catch(() => []),
        ]).then(([report, leadRows, visitRows, followRows]) => {
            setBoard(report);
            setLeads(Array.isArray(leadRows) ? leadRows : []);
            setVisits(Array.isArray(visitRows) ? visitRows : []);
            setFollowups(Array.isArray(followRows) ? followRows : []);
        }).catch((e) => setError(e.message));
    }, []);
    if (error) return html`<p class="error">${error}</p>`;
    if (!board) return html`<p class="lede">Loading the CRM dashboard…</p>`;
    const conversion = board.conversion || { leads: 0, visited: 0, booked: 0 };
    const fresh = (board.statuses || []).find((row) => row.status === 'new');
    const earned = (board.projects || []).reduce((sum, row) => sum + Number(row.sale_value_minor || 0), 0);
    const days = [];
    for (let offset = 0; offset < 14; offset += 1) {
        const date = new Date();
        date.setHours(12, 0, 0, 0);
        date.setDate(date.getDate() - 1 + offset);
        days.push(date);
    }
    const names = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];
    const agenda = [
        ...visits.filter((row) => stampParts(row.scheduled_at)?.day === day).map((row) => ({
            id: 'v' + row.id,
            at: row.scheduled_at,
            title: row.lead_name || 'Visit',
            note: [row.project_name, row.status].filter(Boolean).join(' · '),
        })),
        ...followups.filter((row) => stampParts(row.next_at)?.day === day).map((row) => ({
            id: 'f' + row.id,
            at: row.next_at,
            title: row.lead_name || 'Follow-up',
            note: row.body || row.followup_type || 'Follow-up',
        })),
    ].sort((a, b) => String(a.at).localeCompare(String(b.at)));
    const hours = [9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
    const byHour = new Map(hours.map((hour) => [hour, []]));
    const outside = [];
    agenda.forEach((item) => {
        const hour = stampParts(item.at)?.hour;
        if (byHour.has(hour)) byHour.get(hour).push(item);
        else outside.push(item);
    });
    const timeline = [];
    hours.forEach((hour) => {
        const items = byHour.get(hour);
        const label = slotRange(day + ' ' + String(hour).padStart(2, '0') + ':00');
        if (items.length === 0) timeline.push({ id: 'empty-' + hour, empty: true, label });
        else items.forEach((item) => timeline.push({ ...item, empty: false, label: slotRange(item.at) }));
    });
    outside.forEach((item) => timeline.push({ ...item, empty: false, label: slotRange(item.at) || clock(item.at) }));
    const avatarTones = ['info', 'primary', 'secondary', 'success'];
    const pageSize = 5;
    const pages = Math.max(1, Math.ceil(leads.length / pageSize));
    const safePage = Math.min(page, pages - 1);
    const visible = leads.slice(safePage * pageSize, safePage * pageSize + pageSize);
    const split = board.follow_split || { done: 0, overdue: 0, upcoming: 0 };
    const visitBars = (board.visit_status || []).map((row) => ({ label: String(row.status || '').replace(/_/g, ' '), total: row.total }));
    const attendanceBars = (board.attendance || []).map((row) => ({ label: row.status, total: row.total }));
    const conversionRows = [
        { label: 'Leads', total: conversion.leads },
        { label: 'Visited', total: conversion.visited },
        { label: 'Booked', total: conversion.booked },
    ];
    const followRows = [
        { label: 'Done', total: split.done },
        { label: 'Upcoming', total: split.upcoming },
        { label: 'Overdue', total: split.overdue },
    ];
    const staffSeries = [
        { key: 'leads', label: 'Leads', color: '#1F6B4A' },
        { key: 'followups', label: 'Follow-ups', color: '#299cdb' },
        { key: 'visits', label: 'Visits', color: '#f7b84b' },
        { key: 'bookings', label: 'Bookings', color: '#0ab39c' },
    ];
    const cards = [
        ['info', 'Visits scheduled', board.visits ?? 0, html`<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7"><path d="M8 3v3M16 3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z"/></svg>`, 'visits'],
        ['primary', 'New leads', fresh ? fresh.total : 0, html`<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7"><circle cx="10" cy="8" r="3"/><path d="M4 19a6 6 0 0 1 12 0M18 8v6M15 11h6"/></svg>`, 'leads'],
        ['warning', 'Open follow-ups', board.followups ?? 0, html`<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg>`, 'followups'],
        ['danger', 'Total leads', board.leads ?? 0, html`<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7"><path d="M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2"/></svg>`, 'leads'],
        ['secondary', 'Plots available', board.available ?? 0, html`<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7"><rect x="4" y="4" width="7" height="7" rx="1"/><rect x="13" y="4" width="7" height="7" rx="1"/><rect x="4" y="13" width="7" height="7" rx="1"/><rect x="13" y="13" width="7" height="7" rx="1"/></svg>`, 'projects'],
        ['success', 'Sales value', money(earned), html`<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/></svg>`, 'incentives'],
    ];
    return html`<section class="dosix">
        <article class="panel schedule">
            <header class="panel-head"><h2>Schedule</h2><span class="muted">${day.slice(0, 7)}</span></header>
            <div class="day-strip">
                ${days.map((date) => html`<button key=${dayKey(date)} class=${`day-chip${dayKey(date) === day ? ' on' : ''}`} onClick=${() => setDay(dayKey(date))}>
                    <strong>${String(date.getDate()).padStart(2, '0')}</strong>
                    <span>${names[date.getDay()]}</span>
                </button>`)}
            </div>
            <h3 class="agenda-count">Scheduled: ${String(agenda.length).padStart(2, '0')}</h3>
            <ol class="timeline">
                ${timeline.map((item, index) => html`<li key=${item.id}>
                    <span class="node"></span>
                    <p class="when">${item.label}</p>
                    ${item.empty ? html`<div class="slot-empty">No appointments</div>` : html`<div class="slot-card">
                        <span class=${`slot-avatar ${avatarTones[index % avatarTones.length]}`}>${initials(item.title)}</span>
                        <div><strong>${item.title}</strong><p class="muted">${item.note}</p></div>
                    </div>`}
                </li>`)}
            </ol>
        </article>
        <div class="dosix-main">
            <div class="metrics span-7">
                ${cards.map(([tone, label, value, icon, target]) => html`<button class="metric" key=${label} onClick=${() => onOpen(target)}>
                    <span class=${`metric-icon ${tone}`}>${icon}</span>
                    <h3 class=${String(value).length > 10 ? 'compact' : ''}>${value}</h3>
                    <p>${label}</p>
                </button>`)}
            </div>
            <article class="panel span-5 tall">
                <header class="panel-head"><h2>Lead intake</h2><button class="linkish" onClick=${() => onOpen('leads')}>View all leads</button></header>
                <${Intake} points=${board.intake || []} />
            </article>
            <article class="panel span-12">
                <header class="panel-head"><h2>Booking view</h2><span class="muted">Last 7 days · 9 AM to 6 PM</span></header>
                <${Heatmap} visits=${visits} followups=${followups} />
                <p class="muted heat-note">Click an hour to see the visits and follow-ups booked then.</p>
            </article>
            <article class="panel table-card span-8">
                <header class="panel-head"><h2>Leads</h2><button class="linkish" onClick=${() => onOpen('leads')}>Open list</button></header>
                <table>
                    <thead><tr><th>#</th><th>Name</th><th>Source</th><th>Status</th><th>Phone</th></tr></thead>
                    <tbody>
                        ${visible.length === 0 ? html`<tr><td colSpan="5">No leads yet.</td></tr>` : visible.map((lead, index) => html`<tr key=${lead.id}>
                            <td class="muted">${String(safePage * pageSize + index + 1).padStart(2, '0')}</td>
                            <td>${lead.name}</td>
                            <td>${lead.source || '—'}</td>
                            <td><span class="badge soft">${lead.status || 'new'}</span></td>
                            <td>${lead.phone || '—'}</td>
                        </tr>`)}
                    </tbody>
                </table>
                <div class="pager">
                    <span class="muted">Showing ${visible.length} of ${leads.length}</span>
                    <div>
                        <button class="ghost" disabled=${safePage === 0} onClick=${() => setPage(safePage - 1)}>Previous</button>
                        <button class="ghost on" type="button">${safePage + 1}</button>
                        <button class="ghost" disabled=${safePage >= pages - 1} onClick=${() => setPage(safePage + 1)}>Next</button>
                    </div>
                </div>
            </article>
            <article class="panel span-4">
                <header class="panel-head"><h2>Conversion</h2></header>
                <${FunnelChart} rows=${conversionRows} />
            </article>
            <article class="panel span-4">
                <header class="panel-head"><h2>Lead sources</h2></header>
                <${Pie} rows=${(board.sources || []).map((row) => ({ label: row.label, total: row.total }))} />
            </article>
            <article class="panel span-4">
                <header class="panel-head"><h2>Status funnel</h2></header>
                <${StatusFunnel} rows=${board.funnel || []} />
            </article>
            <article class="panel span-4">
                <header class="panel-head"><h2>Site visits</h2><button class="linkish" onClick=${() => onOpen('visits')}>Visits</button></header>
                <${VBars} rows=${visitBars} />
            </article>
            <article class="panel span-4">
                <header class="panel-head"><h2>Follow-ups</h2><button class="linkish" onClick=${() => onOpen('followups')}>Follow-ups</button></header>
                <${VBars} rows=${followRows} />
            </article>
            <article class="panel span-4">
                <header class="panel-head"><h2>Attendance</h2><button class="linkish" onClick=${() => onOpen('attendance')}>Attendance</button></header>
                <${VBars} rows=${attendanceBars} />
            </article>
            <article class="panel span-12">
                <header class="panel-head"><h2>Staff</h2><button class="linkish" onClick=${() => onOpen('staff')}>Staff list</button></header>
                <${HBars} rows=${board.staff || []} series=${staffSeries} />
            </article>
            <article class="panel span-12">
                <header class="panel-head"><h2>Teams</h2></header>
                <${HBars} rows=${board.teams || []} series=${staffSeries.filter((item) => item.key !== 'overdue')} />
            </article>
            <article class="panel span-8">
                <header class="panel-head"><h2>Projects</h2><button class="linkish" onClick=${() => onOpen('projects')}>Projects</button></header>
                <${HBars} rows=${board.projects || []} series=${[{ key: 'available', label: 'Available', color: '#0ab39c' }, { key: 'booked', label: 'Booked', color: '#1F6B4A' }, { key: 'sold', label: 'Sold', color: '#f7b84b' }, { key: 'sales', label: 'Sales', color: '#f06548' }]} />
            </article>
            <article class="panel span-4">
                <header class="panel-head"><h2>Incentives</h2><button class="linkish" onClick=${() => onOpen('incentives')}>Incentives</button></header>
                <${VBars} rows=${(board.incentives || []).map((row) => ({ label: row.status, total: Math.round(Number(row.amount_minor || 0) / 100) }))} />
                <p class="muted heat-note">${board.pending_incentive?.total || 0} pending · ${money(board.pending_incentive?.amount_minor)}</p>
            </article>
        </div>
    </section>`;
}

const chartColors = ['#1F6B4A', '#0ab39c', '#f7b84b', '#f06548', '#299cdb', '#878a99'];

function piePath(cx, cy, radius, start, sweep) {
    if (sweep >= 359.9) return 'M ' + cx + ' ' + (cy - radius) + ' A ' + radius + ' ' + radius + ' 0 1 1 ' + (cx - 0.01) + ' ' + (cy - radius) + ' Z';
    const a1 = (start - 90) * Math.PI / 180;
    const a2 = (start + sweep - 90) * Math.PI / 180;
    const x1 = (cx + radius * Math.cos(a1)).toFixed(2);
    const y1 = (cy + radius * Math.sin(a1)).toFixed(2);
    const x2 = (cx + radius * Math.cos(a2)).toFixed(2);
    const y2 = (cy + radius * Math.sin(a2)).toFixed(2);
    return 'M ' + cx + ' ' + cy + ' L ' + x1 + ' ' + y1 + ' A ' + radius + ' ' + radius + ' 0 ' + (sweep > 180 ? 1 : 0) + ' 1 ' + x2 + ' ' + y2 + ' Z';
}
function Pie({ rows }) {
    const total = rows.reduce((sum, row) => sum + Number(row.total || 0), 0);
    if (!total) return html`<p class="lede">No rows yet.</p>`;
    let cursor = 0;
    const slices = rows.map((row, index) => {
        const sweep = (Number(row.total || 0) / total) * 360;
        const slice = { path: piePath(70, 70, 58, cursor, sweep), color: chartColors[index % chartColors.length], label: row.label, total: row.total };
        cursor += sweep;
        return slice;
    });
    return html`<div class="pie-wrap">
        <svg viewBox="0 0 140 140" class="pie" role="img" aria-label="Pie chart">${slices.map((slice) => html`<path key=${slice.label} d=${slice.path} fill=${slice.color}></path>`)}</svg>
        <ul>${slices.map((slice) => html`<li key=${slice.label}><i style=${{ background: slice.color }}></i><span>${slice.label}</span><strong>${slice.total}</strong></li>`)}</ul>
    </div>`;
}
function FunnelChart({ rows }) {
    const max = Math.max(1, ...rows.map((row) => Number(row.total || 0)));
    if (rows.length === 0) return html`<p class="lede">No rows yet.</p>`;
    return html`<div class="funnel">${rows.map((row, index) => html`<div class="funnel-step" key=${row.label}>
        <span style=${{ width: Math.max(28, Math.round(Number(row.total || 0) / max * 100)) + '%', background: chartColors[index % chartColors.length] }}>${row.label}<b>${row.total}</b></span>
    </div>`)}</div>`;
}
function StatusFunnel({ rows }) {
    const stages = rows.filter((row) => row.label);
    const max = Math.max(1, ...stages.map((row) => Number(row.total || 0)));
    const sum = stages.reduce((total, row) => total + Number(row.total || 0), 0);
    if (!sum) return html`<p class="lede">No leads yet.</p>`;
    return html`<ol class="status-funnel">${stages.map((row) => {
        const count = Number(row.total || 0);
        const share = Math.round(count / sum * 100);
        const lost = String(row.label).toLowerCase() === 'lost';
        return html`<li key=${row.label}>
            <span title=${row.label}>${row.label}</span>
            <b><i class=${lost ? 'stop' : ''} style=${{ width: (count === 0 ? 0 : Math.max(6, Math.round(count / max * 100))) + '%' }}></i></b>
            <strong>${count}<em>${share}%</em></strong>
        </li>`;
    })}</ol>`;
}
function HBars({ rows, series }) {
    const max = Math.max(1, ...rows.flatMap((row) => series.map((item) => Number(row[item.key] || 0))));
    if (rows.length === 0) return html`<p class="lede">No rows yet.</p>`;
    return html`<div class="hbars">
        <div class="glegend">${series.map((item) => html`<span key=${item.key}><i style=${{ background: item.color }}></i>${item.label}</span>`)}</div>
        ${rows.map((row) => html`<div class="hrow" key=${row.name}><span>${row.name}</span><div>${series.map((item) => html`<b key=${item.key} title=${item.label + ' ' + (row[item.key] ?? 0)} style=${{ width: Math.max(Number(row[item.key]) ? 4 : 0, Math.round(Number(row[item.key] || 0) / max * 100)) + '%', background: item.color }}></b>`)}</div></div>`)}
    </div>`;
}
function VBars({ rows }) {
    const max = Math.max(1, ...rows.map((row) => Number(row.total || 0)));
    if (rows.length === 0) return html`<p class="lede">No rows yet.</p>`;
    return html`<div class="vbars">${rows.map((row, index) => html`<div class="vbar" key=${row.label}><strong>${row.total}</strong><span style=${{ height: Math.max(4, Math.round(Number(row.total || 0) / max * 110)) + 'px', background: chartColors[index % chartColors.length] }}></span><em>${row.label}</em></div>`)}</div>`;
}
function tableHit(row, query, columns) {
    if (!query) return true;
    const needle = query.toLowerCase();
    return columns.some((column) => String(column.text ? column.text(row) : row[column.key] ?? '').toLowerCase().includes(needle));
}
function titleWord(value) {
    const text = String(value || '').replace(/_/g, ' ').trim();
    if (!text) return '—';
    return text.charAt(0).toUpperCase() + text.slice(1);
}
function dayOf(value) {
    const match = String(value || '').match(/^(\d{4}-\d{2}-\d{2})/);
    return match ? match[1] : '';
}
function choicesOf(rows, read) {
    return [...new Set(rows.map((row) => String(read(row) || '').trim()).filter(Boolean))].sort((a, b) => a.localeCompare(b));
}
function includesText(row, query, readers) {
    const needle = String(query || '').trim().toLowerCase();
    if (!needle) return true;
    return readers.some((read) => String(read(row) ?? '').toLowerCase().includes(needle));
}
function inDates(value, from, to) {
    const day = dayOf(value);
    if (!day) return !from && !to;
    if (from && day < from) return false;
    if (to && day > to) return false;
    return true;
}
function personCol(label, read) {
    return { label, text: (row) => read(row) || '', node: (row) => html`<${Person} name=${read(row)} />` };
}
function plainCol(label, read, num) {
    return { label, num: !!num, text: (row) => { const value = read(row); return value === null || value === undefined || value === '' ? '' : value; } };
}
const reportTabs = [
    ['staff', 'Staff'],
    ['leads', 'Leads'],
    ['followups', 'Follow-ups'],
    ['visits', 'Visits'],
    ['bookings', 'Bookings'],
    ['attendance', 'Attendance'],
    ['incentives', 'Incentives'],
    ['projects', 'Projects'],
    ['teams', 'Teams'],
];
function reportView(id, rows) {
    const assigned = (row) => row.assigned_name || row.owner_name || row.staff_name || row.user_name || '';
    const select = (key, label, read) => ({ key, label, kind: 'select', options: choicesOf(rows, read) });
    if (id === 'staff') {
        return {
            title: 'Staff',
            note: 'One row per person. The user column is the name on the account.',
            columns: [
                personCol('User', (row) => row.name),
                plainCol('Role', (row) => row.role_name),
                plainCol('Leads', (row) => Number(row.leads || 0), true),
                plainCol('Follow-ups', (row) => Number(row.followups || 0), true),
                plainCol('Overdue', (row) => Number(row.overdue || 0), true),
                plainCol('Visits', (row) => Number(row.visits || 0), true),
                plainCol('Bookings', (row) => Number(row.bookings || 0), true),
            ],
            fields: [{ key: 'q', label: 'Search', kind: 'search' }, select('role', 'Role', (row) => row.role_name)],
            test: (row, filters) => (!filters.role || row.role_name === filters.role) && includesText(row, filters.q, [(item) => item.name, (item) => item.role_name]),
        };
    }
    if (id === 'leads') {
        return {
            title: 'Leads',
            note: 'Assigned to is the person’s name.',
            columns: [
                plainCol('Lead', (row) => row.name),
                plainCol('Phone', (row) => row.phone_number || row.phone),
                plainCol('Project', (row) => row.project_name),
                personCol('Assigned to', assigned),
                plainCol('Stage', (row) => row.lead_stage || titleWord(row.status)),
                plainCol('Source', (row) => row.lead_source || row.source),
                plainCol('Created', (row) => prettyDate(row.created_at)),
            ],
            fields: [
                { key: 'q', label: 'Search', kind: 'search' },
                select('user', 'Assigned to', assigned),
                select('stage', 'Stage', (row) => row.lead_stage),
                select('source', 'Source', (row) => row.lead_source || row.source),
                select('project', 'Project', (row) => row.project_name),
                { key: 'from', label: 'From', kind: 'date' },
                { key: 'to', label: 'To', kind: 'date' },
            ],
            test: (row, filters) => (!filters.user || assigned(row) === filters.user) && (!filters.stage || row.lead_stage === filters.stage) && (!filters.source || (row.lead_source || row.source) === filters.source) && (!filters.project || row.project_name === filters.project) && inDates(row.created_at, filters.from, filters.to) && includesText(row, filters.q, [(item) => item.name, (item) => item.phone_number || item.phone, assigned, (item) => item.project_name]),
        };
    }
    if (id === 'followups') {
        return {
            title: 'Follow-ups',
            note: 'Assigned to is the person’s name.',
            columns: [
                plainCol('Lead', (row) => row.lead_name),
                plainCol('Phone', (row) => row.lead_phone),
                plainCol('Scheduled', (row) => prettyWhen(row.call_scheduled_at || row.next_at)),
                plainCol('Subject', (row) => row.call_subject || row.body),
                personCol('Assigned to', assigned),
                { label: 'Status', text: (row) => titleWord(row.display_status || row.status), node: (row) => html`<${StatusPill} value=${row.display_status || row.status} />` },
            ],
            fields: [
                { key: 'q', label: 'Search', kind: 'search' },
                select('user', 'Assigned to', assigned),
                select('status', 'Status', (row) => titleWord(row.display_status || row.status)),
                { key: 'from', label: 'From', kind: 'date' },
                { key: 'to', label: 'To', kind: 'date' },
            ],
            test: (row, filters) => (!filters.user || assigned(row) === filters.user) && (!filters.status || titleWord(row.display_status || row.status) === filters.status) && inDates(row.call_scheduled_at || row.next_at, filters.from, filters.to) && includesText(row, filters.q, [(item) => item.lead_name, (item) => item.call_subject, assigned]),
        };
    }
    if (id === 'visits') {
        return {
            title: 'Visits',
            note: 'Assigned to is the person’s name.',
            columns: [
                plainCol('Lead', (row) => row.lead_name),
                plainCol('Project', (row) => row.project_name),
                plainCol('Scheduled', (row) => prettyWhen(row.scheduled_date_time || row.scheduled_at)),
                personCol('Assigned to', assigned),
                { label: 'Status', text: (row) => titleWord(row.display_status || row.status), node: (row) => html`<${StatusPill} value=${row.display_status || row.status} />` },
            ],
            fields: [
                { key: 'q', label: 'Search', kind: 'search' },
                select('user', 'Assigned to', assigned),
                select('project', 'Project', (row) => row.project_name),
                select('status', 'Status', (row) => titleWord(row.display_status || row.status)),
                { key: 'from', label: 'From', kind: 'date' },
                { key: 'to', label: 'To', kind: 'date' },
            ],
            test: (row, filters) => (!filters.user || assigned(row) === filters.user) && (!filters.project || row.project_name === filters.project) && (!filters.status || titleWord(row.display_status || row.status) === filters.status) && inDates(row.scheduled_date_time || row.scheduled_at, filters.from, filters.to) && includesText(row, filters.q, [(item) => item.lead_name, (item) => item.project_name, assigned]),
        };
    }
    if (id === 'bookings') {
        return {
            title: 'Bookings',
            note: 'Associate and team head are names. Advance, booked, sold and registered plots are listed.',
            columns: [
                plainCol('Project', (row) => row.project_name),
                plainCol('Plot', (row) => [row.block, row.plot_no].filter(Boolean).join(' ') || row.plot_no),
                plainCol('Customer', (row) => row.customer_name),
                personCol('Associate', (row) => row.associate_name),
                personCol('Team head', (row) => row.head_name),
                plainCol('Status', (row) => titleWord(row.status)),
                plainCol('Booking', (row) => money(row.booking_amount_minor)),
                plainCol('Advance', (row) => money(row.advance_minor)),
            ],
            fields: [
                { key: 'q', label: 'Search', kind: 'search' },
                select('project', 'Project', (row) => row.project_name),
                select('user', 'Associate', (row) => row.associate_name),
                select('status', 'Status', (row) => titleWord(row.status)),
            ],
            test: (row, filters) => (!filters.project || row.project_name === filters.project) && (!filters.user || row.associate_name === filters.user) && (!filters.status || titleWord(row.status) === filters.status) && includesText(row, filters.q, [(item) => item.project_name, (item) => item.plot_no, (item) => item.customer_name, (item) => item.associate_name, (item) => item.head_name]),
        };
    }
    if (id === 'attendance') {
        return {
            title: 'Attendance',
            note: 'The user column is the person’s name for the selected month.',
            columns: [
                personCol('User', (row) => row.user_name),
                plainCol('Date', (row) => prettyDate(row.work_date)),
                plainCol('Status', (row) => ATTEND_LABEL[row.status] || titleWord(row.status)),
                plainCol('Check in', (row) => row.check_in ? clock(row.check_in) : ''),
                plainCol('Check out', (row) => row.check_out ? clock(row.check_out) : ''),
            ],
            fields: [
                { key: 'month', label: 'Month', kind: 'month' },
                select('user', 'User', (row) => row.user_name),
                { key: 'status', label: 'Status', kind: 'select', options: ['Present', 'Late', 'Leave', 'Absent'] },
            ],
            test: (row, filters) => (!filters.user || row.user_name === filters.user) && (!filters.status || (ATTEND_LABEL[row.status] || titleWord(row.status)) === filters.status) && includesText(row, filters.q, [(item) => item.user_name]),
        };
    }
    if (id === 'incentives') {
        return {
            title: 'Incentives',
            note: 'The user column is the person’s name.',
            columns: [
                personCol('User', (row) => row.user_name),
                plainCol('Project', (row) => row.project_name),
                plainCol('Plot', (row) => row.plot_no),
                plainCol('Status', (row) => titleWord(row.status)),
                plainCol('Amount', (row) => money(row.amount_minor)),
            ],
            fields: [
                { key: 'q', label: 'Search', kind: 'search' },
                select('user', 'User', (row) => row.user_name),
                select('project', 'Project', (row) => row.project_name),
                select('status', 'Status', (row) => titleWord(row.status)),
            ],
            test: (row, filters) => (!filters.user || row.user_name === filters.user) && (!filters.project || row.project_name === filters.project) && (!filters.status || titleWord(row.status) === filters.status) && includesText(row, filters.q, [(item) => item.user_name, (item) => item.project_name, (item) => item.plot_no]),
        };
    }
    if (id === 'projects') {
        return {
            title: 'Projects',
            note: 'Plot counts for each project, with the sale value in this view.',
            columns: [
                plainCol('Project', (row) => row.name),
                plainCol('Available', (row) => Number(row.available || 0), true),
                plainCol('Hold', (row) => Number(row.hold || 0), true),
                plainCol('Booked', (row) => Number(row.booked || 0), true),
                plainCol('Sold', (row) => Number(row.sold || 0), true),
                plainCol('Registered', (row) => Number(row.registered || 0), true),
                plainCol('Sales', (row) => Number(row.sales || 0), true),
                plainCol('Value', (row) => money(row.sale_value_minor)),
            ],
            fields: [{ key: 'q', label: 'Search', kind: 'search' }],
            test: (row, filters) => includesText(row, filters.q, [(item) => item.name]),
        };
    }
    return {
        title: 'Teams',
        note: 'The head column is the team head’s name.',
        columns: [
            plainCol('Team', (row) => row.name),
            personCol('Head', (row) => row.head_name),
            plainCol('Leads', (row) => Number(row.leads || 0), true),
            plainCol('Follow-ups', (row) => Number(row.followups || 0), true),
            plainCol('Visits', (row) => Number(row.visits || 0), true),
            plainCol('Bookings', (row) => Number(row.bookings || 0), true),
        ],
        fields: [{ key: 'q', label: 'Search', kind: 'search' }, select('user', 'Head', (row) => row.head_name)],
        test: (row, filters) => (!filters.user || row.head_name === filters.user) && includesText(row, filters.q, [(item) => item.name, (item) => item.head_name]),
    };
}
async function loadReport(id, month) {
    if (id === 'staff' || id === 'teams' || id === 'projects') {
        const board = await call('/api/v1/reports');
        return board[id] || [];
    }
    if (id === 'leads') return call('/api/v1/leads');
    if (id === 'followups') return call('/api/v1/followups');
    if (id === 'visits') return call('/api/v1/visits');
    if (id === 'incentives') return call('/api/v1/incentives');
    if (id === 'bookings') {
        const rows = await call('/api/v1/plots');
        return (Array.isArray(rows) ? rows : []).filter((row) => ['advance', 'booked', 'sold', 'registered'].includes(String(row.status || '')));
    }
    return call('/api/v1/attendance?month=' + encodeURIComponent(month));
}
function Reports() {
    const [tab, setTab] = useState('staff');
    const [rows, setRows] = useState([]);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);
    const [filters, setFilters] = useState({ month: dayKey(new Date()).slice(0, 7) });
    const [page, setPage] = useState(0);
    const [pageSize, setPageSize] = useState(10);
    const [selected, setSelected] = useState({});
    useEffect(() => {
        let live = true;
        setLoading(true);
        setError('');
        loadReport(tab, filters.month || dayKey(new Date()).slice(0, 7)).then((data) => {
            if (!live) return;
            setRows(Array.isArray(data) ? data : []);
            setLoading(false);
        }).catch((e) => {
            if (!live) return;
            setRows([]);
            setError(e.message);
            setLoading(false);
        });
        return () => { live = false; };
    }, [tab, filters.month]);
    useEffect(() => { setPage(0); setSelected({}); }, [tab, pageSize, filters.q, filters.role, filters.user, filters.stage, filters.source, filters.project, filters.status, filters.from, filters.to, filters.month]);
    const view = reportView(tab, rows);
    const keyed = rows.map((row, index) => ({ row, key: 'r' + index }));
    const shown = keyed.filter((item) => view.test(item.row, filters));
    const pages = Math.max(1, Math.ceil(shown.length / pageSize));
    const safePage = Math.min(page, pages - 1);
    const visible = shown.slice(safePage * pageSize, safePage * pageSize + pageSize);
    const chosen = shown.filter((item) => selected[item.key]);
    const pageChosen = visible.filter((item) => selected[item.key]);
    const allOn = visible.length > 0 && pageChosen.length === visible.length;
    function boxRef(element) {
        if (element) element.indeterminate = pageChosen.length > 0 && !allOn;
    }
    function markAll(event) {
        const next = { ...selected };
        visible.forEach((item) => { next[item.key] = event.target.checked; });
        setSelected(next);
    }
    function setFilter(key, value) {
        setFilters((current) => ({ ...current, [key]: value }));
    }
    function clearFilters() {
        setFilters({ month: dayKey(new Date()).slice(0, 7) });
    }
    const filtering = view.fields.some((field) => field.kind !== 'month' && filters[field.key]);
    function exportSheet() {
        const source = chosen.length ? chosen : shown;
        downloadSheet(view.title, view.columns.map((column) => column.label), source.map((item) => view.columns.map((column) => column.text(item.row))));
    }
    const fromRow = shown.length === 0 ? 0 : safePage * pageSize + 1;
    const toRow = Math.min(shown.length, safePage * pageSize + visible.length);
    return html`<section class="reports">
        <div class="report-tabs">
            ${reportTabs.map(([id, label]) => html`<button key=${id} type="button" class=${tab === id ? 'on' : ''} onClick=${() => setTab(id)}>${label}</button>`)}
        </div>
        <article class="panel report-sheet">
            <header class="panel-head">
                <div><h2>${view.title}</h2><p class="lede">${view.note}</p></div>
                <div class="row-actions">
                    <span class="muted">${chosen.length ? chosen.length + ' selected' : shown.length + ' rows'}</span>
                    <button class="ghost" type="button" onClick=${exportSheet}>Excel</button>
                </div>
            </header>
            <div class="filters">
                ${view.fields.map((field) => html`<label key=${field.key}>${field.label}
                    ${field.kind === 'search' ? html`<input placeholder="Search" value=${filters.q || ''} onInput=${(event) => setFilter('q', event.target.value)} />` : null}
                    ${field.kind === 'select' ? html`<select value=${filters[field.key] || ''} onChange=${(event) => setFilter(field.key, event.target.value)}><option value="">Any</option>${(field.options || []).map((option) => html`<option key=${option} value=${option}>${option}</option>`)}</select>` : null}
                    ${field.kind === 'date' ? html`<input type="date" value=${filters[field.key] || ''} onInput=${(event) => setFilter(field.key, event.target.value)} />` : null}
                    ${field.kind === 'month' ? html`<input type="month" value=${filters.month || ''} onInput=${(event) => setFilter('month', event.target.value)} />` : null}
                </label>`)}
                ${filtering ? html`<button class="ghost" type="button" onClick=${clearFilters}>Clear</button>` : null}
            </div>
            ${error ? html`<p class="error">${error}</p>` : null}
            <div class="table-card"><table class="grid-table">
                <thead><tr>
                    <th class="check"><input ref=${boxRef} type="checkbox" checked=${allOn} aria-label="Select page" onChange=${markAll} /></th>
                    ${view.columns.map((column) => html`<th key=${column.label} class=${column.num ? 'num' : ''}>${column.label}</th>`)}
                </tr></thead>
                <tbody>
                    ${loading ? html`<tr><td colSpan=${view.columns.length + 1}>Loading…</td></tr>` : visible.length === 0 ? html`<tr><td colSpan=${view.columns.length + 1}>${filtering ? 'Nothing matches this filter.' : 'Nothing here yet.'}</td></tr>` : visible.map((item) => html`<tr key=${item.key} class=${selected[item.key] ? 'picked' : ''}>
                        <td class="check"><input type="checkbox" checked=${!!selected[item.key]} aria-label="Select row" onChange=${(event) => setSelected((current) => ({ ...current, [item.key]: event.target.checked }))} /></td>
                        ${view.columns.map((column) => html`<td key=${column.label} class=${column.num ? 'num' : ''}>${column.node ? column.node(item.row) : (column.text(item.row) === '' ? '—' : column.text(item.row))}</td>`)}
                    </tr>`)}
                </tbody>
            </table></div>
            <div class="pager">
                <span class="muted">${shown.length === 0 ? 'Showing 0' : 'Showing ' + fromRow + '–' + toRow + ' of ' + shown.length}</span>
                <div>
                    <label class="page-size">Rows
                        <select value=${String(pageSize)} onChange=${(event) => setPageSize(Number(event.target.value))}>
                            ${[10, 25, 50].map((size) => html`<option key=${size} value=${String(size)}>${size}</option>`)}
                        </select>
                    </label>
                    <button class="ghost" type="button" disabled=${safePage === 0} onClick=${() => setPage(safePage - 1)}>Previous</button>
                    ${Array.from({ length: pages }, (_, index) => index).filter((index) => index === 0 || index === pages - 1 || Math.abs(index - safePage) <= 1).map((index, position, list) => html`<span key=${index}>
                        ${position > 0 && list[position - 1] !== index - 1 ? html`<span class="muted">…</span>` : null}
                        <button class=${index === safePage ? 'ghost on' : 'ghost'} type="button" onClick=${() => setPage(index)}>${index + 1}</button>
                    </span>`)}
                    <button class="ghost" type="button" disabled=${safePage >= pages - 1} onClick=${() => setPage(safePage + 1)}>Next</button>
                </div>
            </div>
        </article>
    </section>`;
}

function Console() {
    const [entries, setEntries] = useState([]);
    const [current, setCurrent] = useState(null);
    const [path, setPath] = useState('');
    const [body, setBody] = useState('');
    const [live, setLive] = useState('');
    const [status, setStatus] = useState('');
    const [base, setBase] = useState('');
    const [query, setQuery] = useState('');
    const [error, setError] = useState('');
    const [sending, setSending] = useState(false);
    useEffect(() => {
        call('/api/v1/catalog').then((data) => {
            const list = data.entries || [];
            setEntries(list);
            setBase(data.base || '');
            setCurrent(list[0] || null);
            setPath(list[0]?.path || '');
            setBody(JSON.stringify(list[0]?.sample_request ?? {}, null, 2));
        }).catch((e) => setError(e.message));
    }, []);
    const groups = useMemo(() => {
        const needle = query.trim().toLowerCase();
        const map = new Map();
        entries.forEach((entry) => {
            const blob = (entry.group + ' ' + entry.name + ' ' + entry.method + ' ' + entry.path).toLowerCase();
            if (needle && !blob.includes(needle)) return;
            if (!map.has(entry.group)) map.set(entry.group, []);
            map.get(entry.group).push(entry);
        });
        return [...map.entries()];
    }, [entries, query]);
    function pick(entry) {
        setCurrent(entry);
        setPath(entry.path || '');
        setBody(JSON.stringify(entry.sample_request ?? {}, null, 2));
        setLive('');
        setStatus('');
        setError('');
    }
    async function send() {
        if (!current || sending) return;
        setError('');
        setLive('');
        setStatus('');
        setSending(true);
        try {
            let parsed;
            if (current.method !== 'GET' && body.trim() !== '' && body.trim() !== 'null') parsed = JSON.parse(body);
            const headers = { Accept: 'application/json' };
            const token = localStorage.getItem(tokenKey);
            if (token && current.auth !== false) headers.Authorization = 'Bearer ' + token;
            if (parsed !== undefined) headers['Content-Type'] = 'application/json';
            const response = await fetch(apiPath(path || current.path), { method: current.method, headers, body: parsed === undefined ? undefined : JSON.stringify(parsed) });
            const text = await response.text();
            let pretty = text;
            try { pretty = JSON.stringify(JSON.parse(text), null, 2); } catch { /* file or plain text */ }
            setStatus(String(response.status));
            setLive(pretty || '(empty)');
            if (!response.ok) setError('Request failed (' + response.status + ')');
        } catch (e) {
            setError(e instanceof SyntaxError ? 'The request body is not valid JSON.' : e.message);
        } finally {
            setSending(false);
        }
    }
    async function download() {
        setError('');
        try {
            const collection = await call('/api/v1/catalog/postman');
            const blob = new Blob([JSON.stringify(collection, null, 2)], { type: 'application/json' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'atrium-api.postman_collection.json';
            link.click();
            URL.revokeObjectURL(link.href);
        } catch (e) {
            setError(e.message);
        }
    }
    const verb = String(current?.method || 'get').toLowerCase();
    return html`<section class="api-console">
        <aside class="api-side">
            <div class="api-find">
                <input placeholder="Search endpoints" value=${query} onInput=${(event) => setQuery(event.target.value)} />
                <button class="ghost" type="button" onClick=${download}>Postman</button>
            </div>
            <div class="api-groups">
                ${groups.length === 0 ? html`<p class="lede">No endpoints match.</p>` : groups.map(([group, items]) => html`<div key=${group}>
                    <div class="nav-label">${group}<em>${items.length}</em></div>
                    ${items.map((entry) => html`<button key=${entry.method + entry.path + entry.name} type="button" class=${current === entry ? 'api-item on' : 'api-item'} onClick=${() => pick(entry)}>
                        <span class=${'verb ' + String(entry.method).toLowerCase()}>${entry.method}</span>
                        <span><strong>${entry.name}</strong><small>${entry.path}</small></span>
                    </button>`)}
                </div>`)}
            </div>
        </aside>
        ${current ? html`<div class="api-work">
            <div class="api-bar">
                <span class=${'verb ' + verb}>${current.method}</span>
                <input class="api-path" value=${path} onInput=${(event) => setPath(event.target.value)} aria-label="Request path" />
                ${current.permission ? html`<span class="api-perm">${current.permission}</span>` : null}
                <button class="primary" type="button" disabled=${sending} onClick=${send}>${sending ? 'Sending…' : 'Send'}</button>
            </div>
            <p class="lede">The desk and the app call this API. After sign-in, every call sends Authorization: Bearer and the token. Send uses the token from this sign-in${base ? ' · ' + base : ''}.</p>
            ${error ? html`<p class="error">${error}</p>` : null}
            <article class="api-card">
                <h3>How to send</h3>
                <pre class="how">${current.how_to_send || ''}</pre>
            </article>
            <div class="api-split">
                <article class="api-card">
                    <h3>${current.method === 'GET' ? 'Query' : 'Request body'}</h3>
                    ${current.method === 'GET' ? html`<p class="lede">A GET call has no JSON body. Filters stay on the URL.</p><pre class="how">${body}</pre>` : html`<textarea value=${body} onInput=${(event) => setBody(event.target.value)} spellcheck=${false}></textarea>`}
                </article>
                <article class="api-card">
                    <h3>Sample response</h3>
                    <pre>${JSON.stringify(current.sample_response, null, 2)}</pre>
                </article>
            </div>
            ${live ? html`<article class="api-card">
                <h3>Response <span class=${Number(status) >= 400 ? 'api-status bad' : 'api-status ok'}>${status}</span></h3>
                <pre>${live}</pre>
            </article>` : null}
        </div>` : html`<p class="lede">${error || 'Loading the catalog…'}</p>`}
    </section>`;
}

function readPlace() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('This browser cannot read your location.'));
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (position) => resolve({ lat: Number(position.coords.latitude.toFixed(7)), lng: Number(position.coords.longitude.toFixed(7)), accuracy: position.coords.accuracy == null ? null : Math.round(position.coords.accuracy) }),
            () => reject(new Error('Allow location access. Attendance is accepted only inside an assigned location.')),
            { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
        );
    });
}
const ATTEND_LABEL = { present: 'Present', late: 'Late', leave: 'Leave', absent: 'Absent' };
const ATTEND_MARK = { present: 'P', late: 'Lt', leave: 'L', absent: 'A' };
const ATTEND_TONE = { present: 'ok', late: 'warn', leave: 'info', absent: 'stop' };
function CompanyBoard({ onSaved }) {
    const [form, setForm] = useState(null);
    const [error, setError] = useState('');
    const [saved, setSaved] = useState('');
    const logoUrl = useCompanyLogo(form?.logo ? form.updatedDate || 'logo' : '');
    useEffect(() => { call('/api/v1/company').then(setForm).catch((e) => setError(e.message)); }, []);
    const set = (key) => (event) => setForm({ ...form, [key]: event.target.value });
    async function submit(event) {
        event.preventDefault();
        setError('');
        setSaved('');
        try {
            const data = await call('/api/v1/company', { method: 'POST', body: { companyName: form.companyName, companyAdminName: form.companyAdminName, companyAdminEmail: form.companyAdminEmail, phone: form.phone || '', address: form.address || '', attendanceLat: form.attendanceLat || '', attendanceLng: form.attendanceLng || '', attendanceKm: form.attendanceKm || '' } });
            setForm(data);
            setSaved('Settings saved.');
            if (onSaved) await onSaved();
        } catch (e) { setError(e.message); }
    }
    async function uploadLogo(event) {
        const file = event.target.files && event.target.files[0];
        event.target.value = '';
        if (!file) return;
        setError('');
        setSaved('');
        try {
            const headers = { Accept: 'application/json' };
            const token = localStorage.getItem(tokenKey);
            if (token) headers.Authorization = 'Bearer ' + token;
            const body = new FormData();
            body.append('logo', file);
            const response = await fetch(apiPath('/api/v1/company/logo'), { method: 'POST', headers, body });
            const payload = await response.json();
            if (!response.ok || payload.ok === false) throw new Error(payload.error || 'The logo was not saved.');
            setForm(payload.data);
            setSaved('Logo updated.');
            if (onSaved) await onSaved();
        } catch (e) { setError(e.message); }
    }
    if (!form) return html`<p class="empty-line">${error || 'Opening settings…'}</p>`;
    return html`<form class="panel-card stack" onSubmit=${submit}>
        <h3>Basic settings</h3>
        <p>Change the company name, email, phone, address, and logo. The email is also the owner sign-in. These details show when a site is created. The kilometre area below is used only when this workspace has no attendance locations.</p>
        ${error ? html`<p class="error">${error}</p>` : null}
        ${saved ? html`<p class="ok-line">${saved}</p>` : null}
        <div class="company-strip">
            ${logoUrl ? html`<img class="logo-preview" src=${logoUrl} alt="" />` : brandIcon()}
            <label class="field">Logo<input type="file" accept="image/png,image/jpeg,image/webp" onChange=${uploadLogo} /></label>
        </div>
        <div class="form-grid">
            ${field('Company name', html`<input value=${form.companyName || ''} onInput=${set('companyName')} required />`)}
            ${field('Email', html`<input type="email" value=${form.companyAdminEmail || ''} onInput=${set('companyAdminEmail')} required />`)}
            ${field('Phone', html`<input inputMode="numeric" value=${form.phone || ''} onInput=${set('phone')} />`)}
            ${field('Address', html`<input value=${form.address || ''} onInput=${set('address')} />`, true)}
            ${field('Contact name', html`<input value=${form.companyAdminName || ''} onInput=${set('companyAdminName')} required />`)}
            ${field('Attendance latitude', html`<input value=${form.attendanceLat || ''} onInput=${set('attendanceLat')} placeholder="11.0168000" />`)}
            ${field('Attendance longitude', html`<input value=${form.attendanceLng || ''} onInput=${set('attendanceLng')} placeholder="76.9558000" />`)}
            ${field('Attendance area (km)', html`<input type="number" min="0" max="100" step="0.1" value=${form.attendanceKm || ''} onInput=${set('attendanceKm')} placeholder="Leave empty to allow any place" />`)}
        </div>
        <div class="row-actions"><button class="ghost" type="button" onClick=${async () => {
            setError('');
            try {
                const place = await readPlace();
                setForm({ ...form, attendanceLat: String(place.lat), attendanceLng: String(place.lng) });
            } catch (e) { setError(e.message); }
        }}>Use my current location</button></div>
        <div class="row-actions"><button class="primary" type="submit">Save</button></div>
    </form>`;
}
const CHECK_WORD = { open: 'Checked in', done: 'Checked out', none: 'Not checked in' };
function loadLeaflet() {
    if (window.L) return Promise.resolve(window.L);
    if (!window.__leaflet) {
        window.__leaflet = new Promise((resolve, reject) => {
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css';
            document.head.appendChild(css);
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js';
            script.onload = () => {
                const L = window.L;
                delete L.Icon.Default.prototype._getIconUrl;
                L.Icon.Default.mergeOptions({
                    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
                    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                });
                resolve(L);
            };
            script.onerror = () => reject(new Error('The map could not be loaded. Enter the latitude and longitude, or use current location.'));
            document.head.appendChild(script);
        });
    }
    return window.__leaflet;
}
function MapPick({ lat, lng, onPick }) {
    const box = useRef(null);
    const mapRef = useRef(null);
    const markerRef = useRef(null);
    const [mapError, setMapError] = useState('');
    useEffect(() => {
        let cancelled = false;
        loadLeaflet().then((L) => {
            if (cancelled || !box.current || mapRef.current) return;
            const has = lat !== '' && lng !== '' && !Number.isNaN(Number(lat)) && !Number.isNaN(Number(lng));
            const map = L.map(box.current).setView(has ? [Number(lat), Number(lng)] : [20, 0], has ? 16 : 2);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
            map.on('click', (event) => onPick(event.latlng.lat.toFixed(7), event.latlng.lng.toFixed(7)));
            mapRef.current = map;
            if (has) markerRef.current = L.marker([Number(lat), Number(lng)]).addTo(map);
            setTimeout(() => map.invalidateSize(), 200);
        }).catch((e) => { if (!cancelled) setMapError(e.message); });
        return () => {
            cancelled = true;
            if (mapRef.current) { mapRef.current.remove(); mapRef.current = null; markerRef.current = null; }
        };
    }, []);
    useEffect(() => {
        const L = window.L;
        const map = mapRef.current;
        if (!L || !map) return;
        const has = lat !== '' && lng !== '' && !Number.isNaN(Number(lat)) && !Number.isNaN(Number(lng));
        if (!has) return;
        const point = [Number(lat), Number(lng)];
        if (markerRef.current) markerRef.current.setLatLng(point);
        else markerRef.current = L.marker(point).addTo(map);
        map.setView(point, Math.max(map.getZoom(), 16));
    }, [lat, lng]);
    return html`<div class="span-2"><div class="map-pick" ref=${box}></div>${mapError ? html`<p class="muted">${mapError}</p>` : html`<p class="muted">Click the map to set the point, or use current location.</p>`}</div>`;
}
function blankPlace() {
    return { id: 0, name: '', category_id: '', category_name: '', address: '', latitude: '', longitude: '', radius_metres: '', work_start: '', work_end: '', accuracy_metres: '', status: 'active', entity_type: '', entity_id: '', user_ids: [] };
}
function LocationDesk() {
    const [categories, setCategories] = useState([]);
    const [places, setPlaces] = useState([]);
    const [projects, setProjects] = useState([]);
    const [categoryName, setCategoryName] = useState('');
    const [editing, setEditing] = useState(null);
    const [error, setError] = useState('');
    function load() {
        setError('');
        call('/api/v1/attendance/categories').then((rows) => setCategories(Array.isArray(rows) ? rows : [])).catch((e) => setError(e.message));
        call('/api/v1/attendance/locations').then((rows) => setPlaces(Array.isArray(rows) ? rows : [])).catch((e) => setError(e.message));
        call('/api/v1/projects').then((rows) => setProjects(Array.isArray(rows) ? rows : [])).catch(() => {});
    }
    useEffect(() => { load(); }, []);
    async function saveCategory(event) {
        event.preventDefault();
        setError('');
        try {
            await call('/api/v1/attendance/categories', { method: 'POST', body: { name: categoryName, status: 'active' } });
            setCategoryName('');
            load();
        } catch (e) { setError(e.message); }
    }
    async function toggleCategory(row) {
        setError('');
        try {
            await call('/api/v1/attendance/categories/' + row.id, { method: 'POST', body: { name: row.name, status: row.status === 'active' ? 'inactive' : 'active' } });
            load();
        } catch (e) { setError(e.message); }
    }
    return html`<section class="panel-card stack">
        <h3>Attendance locations</h3>
        <p class="lede">Add a category, then a place. Link it to a project when it belongs to one. Anyone who can mark attendance can check in there.</p>
        ${error ? html`<p class="error">${error}</p>` : null}
        <form class="site-tools" onSubmit=${saveCategory}>
            <label>Category<input value=${categoryName} onInput=${(event) => setCategoryName(event.target.value)} placeholder="Category name" required /></label>
            <button class="primary" type="submit">Add category</button>
        </form>
        <div class="place-list">${categories.length === 0 ? html`<p class="empty-line">No categories yet.</p>` : categories.map((row) => html`<article key=${row.id} class="place-card"><strong>${row.name}</strong><span class=${'tag ' + (row.status === 'active' ? 'ok' : 'muted')}>${row.status === 'active' ? 'Active' : 'Inactive'}</span><button class="ghost" type="button" onClick=${() => toggleCategory(row)}>${row.status === 'active' ? 'Deactivate' : 'Activate'}</button></article>`)}</div>
        <div class="row-actions"><button class="primary" type="button" onClick=${() => setEditing(blankPlace())}>Add location</button></div>
        <div class="table-card"><table class="board-table"><thead><tr><th>Location</th><th>Category</th><th>Linked record</th><th>Radius</th><th>Hours</th><th>Status</th><th></th></tr></thead><tbody>
            ${places.length === 0 ? html`<tr><td colSpan="7">No attendance locations yet.</td></tr>` : places.map((row) => html`<tr key=${row.id}>
                <td>${row.name}</td>
                <td>${row.category_name || '—'}</td>
                <td>${row.entity_name || '—'}</td>
                <td>${row.radius_metres} m</td>
                <td>${row.work_start && row.work_end ? row.work_start + '–' + row.work_end : '—'}</td>
                <td>${row.status === 'active' ? 'Active' : 'Inactive'}</td>
                <td><button class="ghost" type="button" onClick=${() => setEditing({ ...blankPlace(), ...row, category_id: String(row.category_id || ''), entity_id: row.entity_type === 'project' && row.entity_id ? String(row.entity_id) : '', entity_type: row.entity_type || '', accuracy_metres: row.accuracy_metres == null ? '' : String(row.accuracy_metres), radius_metres: String(row.radius_metres || ''), user_ids: row.user_ids || [] })}>Edit</button></td>
            </tr>`)}
        </tbody></table></div>
        ${editing ? html`<${LocationForm} place=${editing} categories=${categories} projects=${projects} onDone=${() => { setEditing(null); load(); }} onClose=${() => setEditing(null)} />` : null}
    </section>`;
}
function LocationForm({ place, categories, projects, onDone, onClose }) {
    const [form, setForm] = useState(place);
    const [error, setError] = useState('');
    const set = (key) => (event) => setForm({ ...form, [key]: event.target.value });
    async function submit(event) {
        event.preventDefault();
        setError('');
        try {
            const path = form.id ? '/api/v1/attendance/locations/' + form.id : '/api/v1/attendance/locations';
            await call(path, { method: 'POST', body: {
                name: form.name,
                category_id: Number(form.category_id || 0),
                category_name: form.category_name || '',
                address: form.address || '',
                latitude: form.latitude,
                longitude: form.longitude,
                radius_metres: Number(form.radius_metres || 0),
                work_start: form.work_start || '',
                work_end: form.work_end || '',
                accuracy_metres: form.accuracy_metres,
                status: form.status || 'active',
                entity_type: form.entity_id ? 'project' : '',
                entity_id: form.entity_id ? Number(form.entity_id) : 0,
            } });
            onDone();
        } catch (e) { setError(e.message); }
    }
    return html`<${FormCard} title=${form.id ? 'Edit location' : 'Add location'} error=${error} onSubmit=${submit} onClose=${onClose}>
        ${field('Name', html`<input value=${form.name || ''} onInput=${set('name')} required />`)}
        ${field('Category', html`<select value=${form.category_id || ''} onChange=${(event) => setForm({ ...form, category_id: event.target.value, category_name: '' })}><option value="">New category</option>${categories.filter((row) => row.status === 'active' || String(row.id) === String(form.category_id)).map((row) => html`<option key=${row.id} value=${String(row.id)}>${row.name}</option>`)}</select>`)}
        ${form.category_id ? null : field('New category', html`<input value=${form.category_name || ''} onInput=${set('category_name')} placeholder="Type a category" />`)}
        ${field('Address', html`<input value=${form.address || ''} onInput=${set('address')} />`, true)}
        ${field('Latitude', html`<input value=${form.latitude || ''} onInput=${set('latitude')} />`)}
        ${field('Longitude', html`<input value=${form.longitude || ''} onInput=${set('longitude')} />`)}
        ${field('Geofence (metres)', html`<input type="number" min="1" max="100000" step="1" value=${form.radius_metres || ''} onInput=${set('radius_metres')} required />`)}
        ${field('Work start', html`<input type="time" value=${form.work_start || ''} onInput=${set('work_start')} />`)}
        ${field('Work end', html`<input type="time" value=${form.work_end || ''} onInput=${set('work_end')} />`)}
        ${field('GPS accuracy (metres)', html`<input type="number" min="0" max="5000" step="1" value=${form.accuracy_metres || ''} onInput=${set('accuracy_metres')} placeholder="Empty skips this check" />`)}
        ${field('Status', html`<select value=${form.status || 'active'} onChange=${set('status')}><option value="active">Active</option><option value="inactive">Inactive</option></select>`)}
        ${field('Linked project', html`<select value=${form.entity_id || ''} onChange=${set('entity_id')}><option value="">None</option>${projects.map((row) => html`<option key=${row.id} value=${String(row.id)}>${row.name}</option>`)}</select>`, true)}
        <p class="muted span-2">Anyone who can mark attendance can check in at this place. They still have to be inside the radius.</p>
        <div class="span-2 row-actions"><button class="ghost" type="button" onClick=${async () => {
            setError('');
            try {
                const here = await readPlace();
                setForm({ ...form, latitude: String(here.lat), longitude: String(here.lng) });
            } catch (e) { setError(e.message); }
        }}>Use my current location</button></div>
        <${MapPick} lat=${form.latitude || ''} lng=${form.longitude || ''} onPick=${(lat, lng) => setForm({ ...form, latitude: lat, longitude: lng })} />
    <//>`;
}
function AttendanceBoard({ user }) {
    const now = new Date();
    const [year, setYear] = useState(now.getFullYear());
    const [month, setMonth] = useState(now.getMonth() + 1);
    const [rows, setRows] = useState([]);
    const [todayRows, setTodayRows] = useState([]);
    const [people, setPeople] = useState([]);
    const [error, setError] = useState('');
    const [marking, setMarking] = useState(null);
    const [teams, setTeams] = useState([]);
    const [teamId, setTeamId] = useState('');
    const [area, setArea] = useState(null);
    const [sessions, setSessions] = useState([]);
    const [showPlaces, setShowPlaces] = useState(false);
    const [permissions, setPermissions] = useState(user.permissions || []);
    useEffect(() => { call('/api/v1/me').then((me) => setPermissions(me.permissions || [])).catch(() => {}); }, []);
    const canCorrect = permissions.includes('attendance.correct');
    const canMark = permissions.includes('attendance.mark') || canCorrect;
    const today = dayKey(now);
    function load() {
        setError('');
        const key = year + '-' + String(month).padStart(2, '0');
        const todayKey = today.slice(0, 7);
        call('/api/v1/attendance?month=' + encodeURIComponent(key)).then((data) => setRows(Array.isArray(data) ? data : [])).catch((e) => setError(e.message));
        if (todayKey === key) {
            call('/api/v1/attendance?month=' + encodeURIComponent(todayKey)).then((data) => setTodayRows(Array.isArray(data) ? data : [])).catch(() => {});
        } else {
            call('/api/v1/attendance?month=' + encodeURIComponent(todayKey)).then((data) => setTodayRows(Array.isArray(data) ? data : [])).catch(() => {});
        }
        call('/api/v1/staff').then((data) => setPeople(Array.isArray(data) ? data : [])).catch(() => {});
        call('/api/v1/teams').then((data) => setTeams(Array.isArray(data) ? data : [])).catch(() => {});
        call('/api/v1/company').then((data) => setArea(data)).catch(() => {});
        call('/api/v1/attendance/sessions?date=' + today).then((data) => setSessions(Array.isArray(data) ? data : [])).catch(() => {});
    }
    useEffect(() => { load(); }, [year, month]);
    const staff = people.filter((person) => person.status !== 'inactive' && person.status !== 'relieved' && String(person.attendance_applicable) !== '0' && (!teamId || String(person.team_id || '') === String(teamId)));
    const days = new Date(year, month, 0).getDate();
    const byPerson = {};
    rows.forEach((row) => {
        const id = String(row.user_id);
        if (!byPerson[id]) byPerson[id] = {};
        byPerson[id][String(row.work_date).slice(0, 10)] = row;
    });
    const todayMap = {};
    todayRows.forEach((row) => {
        if (String(row.work_date).slice(0, 10) === today) todayMap[String(row.user_id)] = row;
    });
    const years = [now.getFullYear() - 1, now.getFullYear(), now.getFullYear() + 1];
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    function openMark(person, date) {
        if (!canMark) { setError('This sign-in cannot mark attendance.'); return; }
        if (!canCorrect && String(person.user_id) !== String(user.id)) { setError('You can mark only your own attendance.'); return; }
        const existing = (byPerson[String(person.user_id)] || {})[date] || (date === today ? todayMap[String(person.user_id)] : null);
        setMarking({ person, date, status: existing?.status || 'present', action: existing?.check_in && !existing?.check_out ? 'out' : 'in', note: existing?.note || '', self: String(person.user_id) === String(user.id) });
    }
    return html`<div class="sites attend">
        ${error ? html`<p class="error">${error}</p>` : null}
        <div class="site-tools">
            <label>Year<select value=${String(year)} onChange=${(event) => setYear(Number(event.target.value))}>${years.map((value) => html`<option key=${value} value=${String(value)}>${value}</option>`)}</select></label>
            <label>Month<select value=${String(month)} onChange=${(event) => setMonth(Number(event.target.value))}>${months.map((label, index) => html`<option key=${label} value=${String(index + 1)}>${label}</option>`)}</select></label>
            <label>Team<select value=${teamId} onChange=${(event) => setTeamId(event.target.value)}><option value="">All teams</option>${teams.map((team) => html`<option key=${team.id} value=${String(team.id)}>${team.name}</option>`)}</select></label>
            <button class="ghost" type="button" onClick=${() => { setYear(now.getFullYear()); setMonth(now.getMonth() + 1); }}>Today</button>
            <button class="ghost" type="button" onClick=${() => {
                const teamName = Object.fromEntries(teams.map((team) => [String(team.id), team.name]));
                const allowed = new Set(staff.map((person) => String(person.user_id)));
                downloadSheet('Attendance ' + months[month - 1] + ' ' + year, ['User', 'Team', 'Date', 'Status', 'Check in', 'Check out', 'Sessions'], rows.filter((row) => allowed.has(String(row.user_id))).map((row) => {
                    const person = staff.find((item) => String(item.user_id) === String(row.user_id));
                    return [row.user_name || person?.name || '', teamName[String(person?.team_id || '')] || '', String(row.work_date || '').slice(0, 10), ATTEND_LABEL[row.status] || row.status || '', row.check_in ? clock(row.check_in) : '', row.check_out ? clock(row.check_out) : '', row.session_count || 0];
                }));
            }}>Download</button>
            ${canCorrect ? html`<button class="ghost" type="button" onClick=${() => setShowPlaces(!showPlaces)}>${showPlaces ? 'Hide locations' : 'Locations'}</button>` : null}
            ${canMark ? html`<button class="primary" type="button" onClick=${() => { const person = staff.find((row) => String(row.user_id) === String(user.id)) || (canCorrect ? staff[0] : null); if (person) openMark(person, today); else setError('No staff row is available to mark.'); }}>Mark attendance</button>` : null}
        </div>
        ${showPlaces ? html`<${LocationDesk} />` : null}
        <div class="attend-layout">
            <section class="panel-card">
                <h3>${months[month - 1]} ${year}</h3>
                <p>Each letter is that person’s mark. P present, Lt late, L leave, A absent.</p>
                <div class="table-card attend-sheet"><table class="board-table"><thead><tr>
                    <th>User</th>
                    ${Array.from({ length: days }, (_, index) => html`<th key=${index} class=${year === now.getFullYear() && month === now.getMonth() + 1 && index + 1 === now.getDate() ? 'today-col' : ''}>${index + 1}</th>`)}
                </tr></thead><tbody>
                    ${staff.map((person) => html`<tr key=${person.user_id}>
                        <td class="attend-name"><strong>${person.name}</strong><em>${person.role_name || person.profile || ''}</em></td>
                        ${Array.from({ length: days }, (_, index) => {
                            const date = year + '-' + String(month).padStart(2, '0') + '-' + String(index + 1).padStart(2, '0');
                            const mark = (byPerson[String(person.user_id)] || {})[date];
                            const letter = mark ? (ATTEND_MARK[mark.status] || '·') : '';
                            return html`<td key=${date} class=${year === now.getFullYear() && month === now.getMonth() + 1 && index + 1 === now.getDate() ? 'today-col' : ''}><button type="button" class=${'day-mark ' + (mark ? ATTEND_TONE[mark.status] || '' : '')} onClick=${() => openMark(person, date)}>${letter}</button></td>`;
                        })}
                    </tr>`)}
                </tbody></table></div>
            </section>
            <aside class="panel-card attend-today">
                <h3>Today</h3>
                <p>${prettyDate(today)}</p>
                ${staff.map((person) => {
                    const mark = todayMap[String(person.user_id)];
                    return html`<article key=${person.user_id} class="attend-person">
                        <span class="avatar sm">${String(person.name || 'A').slice(0, 1)}</span>
                        <div>
                            <strong>${person.name}</strong>
                            <em>${person.role_name || ''}</em>
                            ${mark ? html`<span class=${'tag ' + (ATTEND_TONE[mark.status] || 'muted')}>${ATTEND_LABEL[mark.status] || mark.status}</span><small>${mark.check_in ? clock(mark.check_in) : '—'}${mark.check_out ? ' – ' + clock(mark.check_out) : ''}</small>` : html`<span class="tag muted">Not marked</span>`}
                            ${sessions.filter((row) => String(row.user_id) === String(person.user_id)).map((row) => html`<small key=${row.id}>${row.location_name || 'Location'}${row.category_name ? ' · ' + row.category_name : ''}${row.entity_name ? ' · ' + row.entity_name : ''} · ${CHECK_WORD[row.check_status] || row.check_status}</small>`)}
                        </div>
                        ${canMark && (canCorrect || String(person.user_id) === String(user.id)) ? html`<button class="ghost" type="button" onClick=${() => openMark(person, today)}>${mark?.check_in && !mark?.check_out ? 'Out' : 'Mark'}</button>` : null}
                    </article>`;
                })}
            </aside>
        </div>
        ${marking ? html`<${AttendForm} key=${marking.person.user_id + marking.date} marking=${marking} area=${area} onDone=${() => { setMarking(null); load(); }} onClose=${() => setMarking(null)} />` : null}
    </div>`;
}
function AttendForm({ marking, area, onDone, onClose }) {
    const [form, setForm] = useState({ status: marking.status, action: marking.action, note: marking.note, location_id: '' });
    const [places, setPlaces] = useState([]);
    const [error, setError] = useState('');
    const set = (key) => (event) => setForm({ ...form, [key]: event.target.value });
    useEffect(() => {
        const path = marking.self ? '/api/v1/attendance/places' : '/api/v1/attendance/locations';
        call(path).then((rows) => {
            const list = (Array.isArray(rows) ? rows : []).filter((row) => marking.self || row.status === 'active');
            setPlaces(list);
            const open = list.find((row) => row.check_status === 'open');
            if (open) setForm((current) => ({ ...current, location_id: String(open.id), action: 'out' }));
            if (!marking.self || list.length === 0) return;
            readPlace().then((here) => call('/api/v1/attendance/places?lat=' + here.lat + '&lng=' + here.lng)).then((near) => {
                if (Array.isArray(near)) setPlaces(near);
            }).catch(() => {});
        }).catch((e) => setError(e.message));
    }, []);
    const needsGps = marking.self && (form.status === 'present' || form.status === 'late') && (places.length > 0 || Number(area?.attendanceKm) > 0);
    async function submit(event) {
        event.preventDefault();
        setError('');
        try {
            if (marking.self && places.length > 0 && (form.status === 'present' || form.status === 'late') && !form.location_id) {
                setError('Choose an attendance location.');
                return;
            }
            const place = needsGps ? await readPlace() : {};
            await call('/api/v1/attendance', { method: 'POST', body: { user_id: marking.person.user_id, work_date: marking.date, status: form.status, action: form.action, note: form.note, location_id: form.location_id ? Number(form.location_id) : undefined, lat: place.lat, lng: place.lng, accuracy: place.accuracy } });
            onDone();
        } catch (e) { setError(e.message); }
    }
    return sheet('Mark attendance', error, submit, onClose, html`
        ${xrow('User', html`<input value=${marking.person.name} readOnly />`)}
        ${xrow('Date', html`<input type="date" value=${marking.date} readOnly />`)}
        ${xrow('Status', html`<select value=${form.status} onChange=${set('status')}>${Object.entries(ATTEND_LABEL).map(([value, label]) => html`<option key=${value} value=${value}>${label}</option>`)}</select>`, true)}
        ${places.length > 0 && (form.status === 'present' || form.status === 'late') ? html`<div class="place-list">${places.map((row) => html`<button key=${row.id} type="button" class=${'place-card' + (String(form.location_id) === String(row.id) ? ' on' : '')} onClick=${() => setForm({ ...form, location_id: String(row.id), action: row.check_status === 'open' ? 'out' : form.action })}>
            <strong>${row.name}</strong>
            <span>${row.category_name || 'Location'}${row.entity_name ? ' · ' + row.entity_name : ''}</span>
            <em>${row.distance_metres == null ? '' : row.distance_metres + ' m away'}${row.check_status ? (row.distance_metres == null ? '' : ' · ') + (CHECK_WORD[row.check_status] || '') : ''}</em>
        </button>`)}</div>` : null}
        ${xrow('Action', html`<select value=${form.action} onChange=${set('action')}><option value="in">Check in</option><option value="out">Check out</option></select>`)}
        ${xrow('Note', html`<input value=${form.note} onInput=${set('note')} />`)}
        ${needsGps && places.length > 0 ? html`<p class="muted">Check-in and check-out use this device’s location and must be inside the selected place.</p>` : null}
        ${needsGps && places.length === 0 ? html`<p class="muted">Check-in and check-out use this device’s location and must be inside ${area.attendanceKm} km of the company.</p>` : null}
    `, true, 'Save');
}
function SendWhatsApp({ lead, onDone, onClose }) {
    const [templateId, setTemplateId] = useState('');
    const [templates, setTemplates] = useState([]);
    const [error, setError] = useState('');
    useEffect(() => { call('/api/v1/whatsapp/templates').then((rows) => setTemplates(Array.isArray(rows) ? rows : [])).catch((e) => setError(e.message)); }, []);
    const picked = templates.find((row) => String(row.id) === String(templateId));
    async function submit(event) {
        event.preventDefault();
        if (!templateId) { setError('Choose a template.'); return; }
        setError('');
        try {
            await call('/api/v1/leads/' + lead.id + '/whatsapp', { method: 'POST', body: { template_id: Number(templateId) } });
            onDone();
        } catch (e) { setError(e.message); }
    }
    return sheet('Send WhatsApp', error, submit, onClose, html`
        ${xrow('Lead', html`<input class="locked" value=${lead.name || ''} readOnly />`)}
        ${xrow('Number', html`<input class="locked" value=${lead.phone_number || lead.phone || ''} readOnly />`)}
        ${xrow('Subject', html`<select value=${templateId} onChange=${(event) => setTemplateId(event.target.value)}><option value="">Choose a template</option>${templates.map((row) => html`<option key=${row.id} value=${String(row.id)}>${row.name}</option>`)}</select>`, true)}
        ${picked ? xrow('Message', html`<textarea readOnly value=${picked.body || ''}></textarea>`, false, true) : null}
    `, true, 'Queue');
}
function sendStatus(row) {
    const key = String(row.status || 'failed');
    const tone = key === 'delivered' || key === 'read' || key === 'sent' ? 'ok' : key === 'queued' ? 'warn' : 'stop';
    return html`<span class=${'tag ' + tone}>${row.status_label || 'Not delivered'}</span>`;
}
function WhatsAppBoard() {
    const [tab, setTab] = useState('sent');
    const [sent, setSent] = useState([]);
    const [templates, setTemplates] = useState([]);
    const [text, setText] = useState('');
    const [status, setStatus] = useState('');
    const [adding, setAdding] = useState(false);
    const [error, setError] = useState('');
    function load() {
        setError('');
        call('/api/v1/whatsapp/messages').then((rows) => setSent(Array.isArray(rows) ? rows : [])).catch((e) => setError(e.message));
        call('/api/v1/whatsapp/templates').then((rows) => setTemplates(Array.isArray(rows) ? rows : [])).catch(() => {});
    }
    useEffect(() => { load(); }, []);
    const q = text.trim().toLowerCase();
    const rows = sent.filter((row) => {
        if (status && String(row.status_label) !== status) return false;
        if (!q) return true;
        return [row.lead_name, row.to_phone, row.subject, row.body, row.user_name].join(' ').toLowerCase().includes(q);
    });
    return html`<section class="notice-page">
        <div class="site-tabs">
            <button type="button" class=${tab === 'sent' ? 'on' : ''} onClick=${() => setTab('sent')}>Sent <em>${sent.length}</em></button>
            <button type="button" class=${tab === 'templates' ? 'on' : ''} onClick=${() => setTab('templates')}>Templates <em>${templates.length}</em></button>
        </div>
        ${error ? html`<p class="error">${error}</p>` : null}
        ${tab === 'sent' ? html`<section class="panel-card">
            <div class="card-head"><div><h3>Sent report</h3><p>Lead, number, subject, and delivery status for each WhatsApp message.</p></div></div>
            <div class="board-tools">
                <label class="page-size">Status<select value=${status} onChange=${(event) => setStatus(event.target.value)}><option value="">All</option><option>Not delivered</option><option>Queued</option><option>Sent</option><option>Delivered</option><option>Read</option></select></label>
                <div class="board-search"><input placeholder="Search lead, number, subject" value=${text} onInput=${(event) => setText(event.target.value)} /><span>${rows.length} messages</span></div>
            </div>
            ${rows.length === 0 ? html`<p class="empty-line">No WhatsApp messages yet. Open a lead and use Send WhatsApp.</p>` : html`<div class="table-card"><table class="board-table"><thead><tr><th>When</th><th>Lead</th><th>Number</th><th>Subject</th><th>Message</th><th>Status</th><th>Sent by</th></tr></thead><tbody>
                ${rows.map((row) => html`<tr key=${row.id}><td>${prettyWhen(row.created_at)}</td><td>${row.lead_name || '—'}</td><td>${row.to_phone || '—'}</td><td>${row.subject || '—'}</td><td>${row.body || '—'}</td><td>${sendStatus(row)}</td><td>${row.user_name || '—'}</td></tr>`)}
            </tbody></table></div>`}
        </section>` : html`<section class="panel-card">
            <div class="card-head"><div><h3>Templates</h3><p>Subjects you can send to a lead.</p></div><button class="primary" type="button" onClick=${() => setAdding(true)}>Add template</button></div>
            ${templates.length === 0 ? html`<p class="empty-line">No templates yet.</p>` : html`<div class="table-card"><table class="board-table"><thead><tr><th>Name</th><th>Language</th><th>Message</th></tr></thead><tbody>
                ${templates.map((row) => html`<tr key=${row.id}><td>${row.name || '—'}</td><td>${row.language || '—'}</td><td>${row.body || '—'}</td></tr>`)}
            </tbody></table></div>`}
        </section>`}
        ${adding ? html`<${TemplateForm} onDone=${() => { setAdding(false); load(); }} onClose=${() => setAdding(false)} />` : null}
    </section>`;
}
function NoticeBoard() {
    const [notes, setNotes] = useState([]);
    const [error, setError] = useState('');
    function load() {
        setError('');
        call('/api/v1/notifications').then((rows) => setNotes(Array.isArray(rows) ? rows : [])).catch((e) => setError(e.message));
    }
    useEffect(() => { load(); }, []);
    const unread = notes.filter((row) => !row.read_at).length;
    return html`<section class="notice-page">
        ${error ? html`<p class="error">${error}</p>` : null}
        <section class="panel-card">
            <div class="card-head"><div><h3>Notices <em class="count-badge">${unread}</em></h3><p>Notices for this sign-in only.</p></div><button class="ghost" type="button" onClick=${async () => { await call('/api/v1/notifications/read', { method: 'POST', body: {} }); load(); }}>Mark all read</button></div>
            ${notes.length === 0 ? html`<p class="empty-line">No notices yet.</p>` : html`<div class="notice-list">${notes.map((row) => html`<article key=${row.id} class=${row.read_at ? 'note read' : 'note'}><header><strong>${row.title || 'Notice'}</strong><small>${prettyWhen(row.created_at)}</small></header><p>${row.body || ''}</p></article>`)}</div>`}
        </section>
    </section>`;
}
function App() {
    const [user, setUser] = useState(null);
    const [company, setCompany] = useState(null);
    const [ready, setReady] = useState(false);
    const [screen, setScreen] = useState('home');
    const [setup, setSetup] = useState(() => new URLSearchParams(location.search).get('setup') || '');
    const [passwordOpen, setPasswordOpen] = useState(false);
    const [splash, setSplash] = useState(true);
    const fetchedLogo = useCompanyLogo(company?.logo ? (company.updatedDate || 'logo') : '');
    const logoUrl = fetchedLogo || (company?.logo !== false && window.__atriumLogo ? window.__atriumLogo : '');
    useEffect(() => {
        if (!localStorage.getItem(tokenKey)) {
            setReady(true);
            return;
        }
        call('/api/v1/me').then(setUser).catch(() => localStorage.removeItem(tokenKey)).finally(() => setReady(true));
    }, []);
    useEffect(() => {
        if (!user) { setCompany(null); return; }
        call('/api/v1/company').then(setCompany).catch(() => {});
    }, [user]);
    useEffect(() => {
        const job = window.__atriumBoot || Promise.resolve();
        job.finally(() => setSplash(false));
    }, []);
    useEffect(() => {
        if (setup || (ready && !splash)) {
            document.getElementById('boot')?.remove();
        }
    }, [setup, ready, splash]);
    if (setup) return html`<${SetPassword} token=${setup} onDone=${() => { history.replaceState({}, '', apiPath('/desk/')); setSetup(''); }} />`;
    if (!ready || splash) return null;
    if (!user) return html`<${Login} onIn=${setUser} />`;
    const spec = lists[screen];
    const title = screen === 'settings' ? 'Settings' : (modules.find((item) => item[1] === screen)?.[0] || 'CRM');
    const section = screen === 'settings' ? ['Settings'] : nav.find((group) => group[1].some((item) => item[1] === screen));
    const initial = String(user.name || 'A').slice(0, 1);
    return html`<div class="shell">
        <aside class="sidebar">
            <div class="brand">${logoUrl ? html`<img class="brand-logo" src=${logoUrl} alt="" />` : brandIcon()}<span><strong>${company?.companyName || user.workspace || 'Company'}</strong></span></div>
            <div class="nav-scroll">
                ${nav.map(([label, items]) => html`<section class="nav-group" key=${label}>
                    <div class="nav-label">${label}</div>
                    ${items.map(([text, id]) => html`<button key=${id} class=${screen === id ? 'active' : ''} onClick=${() => setScreen(id)}>${text}</button>`)}
                </section>`)}
            </div>
            <button class=${screen === 'settings' ? 'nav-pin active' : 'nav-pin'} type="button" onClick=${() => setScreen('settings')}>Settings</button>
            <button class="sign-out" onClick=${() => { localStorage.removeItem(tokenKey); window.__atriumLogo = ''; setUser(null); }}>Sign out</button>
        </aside>
        <div class="workspace">
            <header class="topbar">
                <div><p class="crumb">${section ? section[0] : 'Desk'} / ${title}</p><h1>${title}</h1></div>
                <div class="top-tools">
                    <input class="search" placeholder="Search..." readOnly />
                    <button class="icon-btn" aria-label="Notifications" onClick=${() => setScreen('notifications')}><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9"/><path d="M10 20a2 2 0 0 0 4 0"/></svg><span class="dot"></span></button>
                    <div class="user-pill"><span class="avatar">${initial}</span><div><strong>${user.name}</strong><em>${user.role}</em></div></div>
                    <button class="ghost" type="button" onClick=${() => setPasswordOpen(true)}>Password</button>
                </div>
            </header>
            <main class="main">
                ${screen === 'home' ? html`<${Home} onOpen=${setScreen} />` : null}
                ${screen === 'reports' ? html`<${Reports} />` : null}
                ${screen === 'api' ? html`<${Console} />` : null}
                ${screen === 'notifications' ? html`<${NoticeBoard} />` : null}
                ${screen === 'whatsapp' ? html`<${WhatsAppBoard} />` : null}
                ${spec && spec.kind === 'sites' ? html`<${SitesBoard} key=${screen} start=${spec.start} />` : null}
                ${spec && spec.kind === 'attendance' ? html`<${AttendanceBoard} key="attendance" user=${user} />` : null}
                ${spec && spec.kind === 'company' ? html`<${CompanyBoard} onSaved=${async () => { setUser(await call('/api/v1/me')); setCompany(await call('/api/v1/company')); }} />` : null}
                ${spec && screen !== 'notifications' && screen !== 'whatsapp' && spec.kind !== 'sites' && spec.kind !== 'attendance' && spec.kind !== 'company' ? html`<${Resource} key=${screen} title=${title} lede=${spec.lede} spec=${spec} />` : null}
                ${passwordOpen ? html`<${ChangePassword} onClose=${() => setPasswordOpen(false)} />` : null}
            </main>
        </div>
    </div>`;
}

createRoot(document.getElementById('root')).render(html`<${App} />`);
