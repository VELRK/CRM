# Atrium CRM — product plan

Status: plan only. No application code until this document is accepted.

Working name: **Atrium**. Rename before the repo is created if you want a different brand.

Date: 23 Oct 2026.

Atrium is a multi-tenant sales CRM for teams of about 3 to 50 people. It holds people, companies, a pipeline, tasks, quotes, and invoices. Home answers four questions: who to call today, which deals moved, which tasks are due, and what is unpaid.

The product is a website in this folder. There is no mobile app. People use it in the browser. CodeIgniter renders the pages. The same app exposes `/api/v1` for those pages.

## Scope lock

Records move only when a person moves them.

Out of the product:

- Workflow builder
- Drip sequences and campaign mail
- Auto-assignment and lead scoring
- Chatbots
- Stale-deal nudges
- Cron jobs that change CRM records

A reminder fires only when a person set that reminder. The only outbound mail is invite, password reset, and a tapped Send on a quote or invoice.

## Recommended edition

Build and sell **Enterprise**. Starter and Growth are the same app with lower limits. One codebase. The plan row turns modules and limits on or off.

| | Starter | Growth | Enterprise (recommended) |
|---|---|---|---|
| Price / workspace / month | ₹1,499 | ₹4,999 | ₹14,999 |
| Annual (two months included) | ₹14,990 | ₹49,990 | ₹1,49,990 |
| Users | 3 | 15 | 100 |
| Contacts | 2,000 | 25,000 | Unlimited (fair use 250,000) |
| Pipelines | 1 | 5 | Unlimited |
| Tasks, notes, files | Yes | Yes | Yes |
| Quotes and invoices | No | Yes | Yes |
| Reports | Home figures only | Full | Full |
| Roles | 8, access editable | 25, access editable | 50, access editable |
| Audit log | No | No | Yes |
| File storage | 2 GB | 20 GB | 100 GB |
| API | No | Read | Read and write |
| Support | Email | Email and chat | Priority |

Prices are planning numbers for India, GST extra. A USD mirror at checkout is about $18, $60, and $180 per month before tax. Upgrading a workspace changes the subscription row. Data is not migrated to a second product.

## Roles and access

A workspace can add roles. Access is allocated to the role, never to a single user. A member has one role in that workspace. Changing the role replaces what they can do. There is no per-person permission list in v1.

On create, the workspace receives five starter roles. Owner is locked: every permission, cannot be renamed, cannot be deleted, and the workspace must keep at least one Owner. The other four are starters. Their access can be changed. They cannot be deleted. Any further role is custom: name it, allocate access, rename it, and delete it after its members are moved to another role.

| Starter role | Access it ships with |
|---|---|
| Owner | Every permission, including billing. Locked |
| Admin | Everything except billing |
| Manager | View and edit the book, tasks, quotes, and invoices. Cannot manage roles or billing |
| Member | View and edit records they own. Can log work and create quotes |
| Viewer | View only |

Someone with `roles.manage` opens **Roles**, taps add, names the role, and turns permissions on or off. The catalog is fixed. A role cannot invent a new permission. Saving writes the set onto that role. The next API call from a member on that role uses the new set.

### Permission catalog

Each key is `module.action`. The role editor groups them under the module name. Icons match the module icons already in this plan. The access screen itself uses `key-round`.

| Group | Keys that can be allocated |
|---|---|
| People | `contacts.view` `contacts.create` `contacts.update` `contacts.delete` `contacts.assign` |
| Companies | `companies.view` `companies.create` `companies.update` `companies.delete` |
| Pipeline | `deals.view` `deals.create` `deals.update` `deals.delete` `deals.move_stage` |
| Work | `tasks.view` `tasks.create` `tasks.update` `tasks.delete` `activities.create` |
| Catalog | `products.view` `products.create` `products.update` `products.delete` |
| Quotes | `quotes.view` `quotes.create` `quotes.update` `quotes.delete` `quotes.send` |
| Invoices | `invoices.view` `invoices.create` `invoices.update` `invoices.delete` `invoices.send` `payments.record` |
| Files | `files.view` `files.attach` `files.delete` |
| Reports | `reports.view` |
| Team | `team.view` `team.invite` `team.deactivate` `roles.manage` |
| Workspace | `workspace.settings` `billing.manage` |

`contacts.assign` is the right to set a record owner by hand. It is not an assignment rule.

The app checks the caller's role on every page and every API route. A missing permission returns 403. The website hides buttons the role does not have, and still treats the server as the source of truth.

Plan caps count every role, including the five starters: Starter 8, Growth 25, Enterprise 50.

## Tenancy

One MySQL database. Every business table has `tenant_id`. The API reads the workspace from the access token. A tenant id in the request body is ignored. Plan limits (users, contacts, pipelines, storage) are checked in one filter before the controller runs.

## Modules

One icon family for the whole site: **Lucide**, stroke 1.75, as SVG files in `public/icons/`. Do not mix a second icon set. The icon column is the file name. No page invents its own icon.

### Book of business

| Module | Lucide icon | What a person does |
|---|---|---|
| Workspace | `building-2` | Company profile, logo, currency, fiscal year |
| People | `users` | Contacts: name, phone, email, owner, tags |
| Companies | `landmark` | Accounts linked to one or many contacts |
| Pipeline | `kanban` | Deals on stages. A drag is a manual move |

### Daily work

| Module | Lucide icon | What a person does |
|---|---|---|
| Activities | `messages-square` | Notes, logged calls, meetings written by hand |
| Tasks | `square-check` | A task with an owner, a due time, an optional reminder |
| Calendar | `calendar` | Meetings the user creates. No auto-booking |

### Quotes and invoices

| Module | Lucide icon | What a person does |
|---|---|---|
| Catalog | `package` | Products, price, tax rate |
| Quotes | `file-text` | A quote the user builds and taps Send |
| Invoices | `receipt` | Invoice, balance, payment the user records |

### Team, insight, system

| Module | Lucide icon | What a person does |
|---|---|---|
| Reports | `chart-column` | Pipeline value, won, overdue tasks, unpaid invoices |
| Team | `shield` | Invite, deactivate, add a role. Access uses `key-round`. Audit log on Enterprise |
| Search | `search` | Name, phone, deal title, invoice number |
| Files | `paperclip` | Attach to a contact, deal, quote, or invoice |
| Notices | `bell` | Assignment, a due reminder the user created, a paid invoice |

Shared chrome icons: `log-in`, `user`, `handshake`, `layout-dashboard`, `menu`, `settings`, `credit-card`, `key-round`, `trash-2`, `ellipsis`, `chevron-left`, `sliders-horizontal`, `phone`, `mail`, `inbox` (empty states).

### Core tables

`tenants`, `users`, `tenant_users`, `roles`, `permissions`, `role_permissions`, `plans`, `subscriptions`, `contacts`, `companies`, `contact_company`, `pipelines`, `stages`, `deals`, `activities`, `tasks`, `products`, `quotes`, `quote_items`, `invoices`, `invoice_items`, `payments`, `files`, `notifications`, `audit_logs`, `refresh_tokens`.

`tenant_users.role_id` points at one role. `permissions` is the global catalog. `role_permissions` is the access allocated to a role.

Soft delete on business rows. Money is integer minor units plus a currency code.

### Left for a later decision

Custom fields, email inbox sync, WhatsApp, inventory, a public partner marketplace, SSO. None of these return as an automation engine.

## Screens

A left sidebar on every signed-in page. Create and edit open on their own pages. Search sits in the top bar on Home, People, and Pipeline.

| Nav | Icon | Opens |
|---|---|---|
| Home | `layout-dashboard` | Today: calls, due tasks, unpaid, recently moved deals |
| Pipeline | `kanban` | Board by stage, then deal detail |
| People | `users` | Contacts and companies |
| Work | `square-check` | Tasks and calendar on one segmented control |
| Roles | `shield` | Add a role and allocate access |
| Settings | `settings` | Profile, workspace, billing |

| Screen | Icon | Primary action |
|---|---|---|
| Sign in | `log-in` | Continue |
| Create workspace | `building-2` | Create workspace |
| Home | `layout-dashboard` | New task |
| Contact list | `users` | New contact |
| Contact | `user` | Log call |
| Company | `landmark` | New deal |
| Board | `kanban` | New deal |
| Deal | `handshake` | Move stage |
| Task list | `square-check` | New task |
| Calendar | `calendar` | New meeting |
| Quote editor | `file-text` | Send quote |
| Invoice | `receipt` | Record payment |
| Reports | `chart-column` | Change date range |
| Team | `shield` | Invite |
| Roles | `shield` | Add role |
| Role access | `key-round` | Save access |
| Settings | `settings` | Save profile |
| Plan and billing | `credit-card` | Manage plan |
| Notices | `bell` | Mark read |
| Empty state | `inbox` | The one action that fills the list |

Home composition: greeting, then four figures (due today, open pipeline, quotes waiting, unpaid), then one list titled Today. Reports stay on their own screen.

List row, used everywhere: 36px icon well, title, one meta line, trailing amount or chevron.

## Design — Quiet Ink

Editorial and quiet. Warm paper, near-black ink, one pine accent. Closer to a well-set notebook than to a chart wall.

| Token | Light | Dark | Use |
|---|---|---|---|
| Paper | `#F6F4F1` | `#141311` | App background |
| Surface | `#FFFFFF` | `#1C1B19` | Sheets, rows, inputs |
| Ink | `#1A1916` | `#F4F1EC` | Titles and body |
| Muted | `#6F6A63` | `#A39E96` | Meta, placeholders |
| Line | `#E7E2DA` | `#2C2A27` | Hairlines, input borders |
| Accent | `#1F6B5A` | `#7DBFB0` | Primary button, active tab, links |
| Danger | `#9B3A3A` | `#E09A9A` | Delete and overdue money |

Type: Geist or Satoshi. Screen title 28/34. Section 18/24. Body 15/22. Meta 13/18. Weights: regular and semibold. No all-caps labels.

Space: 4, 8, 12, 16, 24, 32. Screen padding 20. List row min height 64. Icon well 36, radius 10, icon 20 inside it.

Shape: cards radius 14, inputs 10, pills full round, sheet top radius 20. Motion 180ms ease-out. Haptic on primary confirm only.

Dark mode ships in the same release as light mode.

## Stack

Pin these when the repo is created. Re-check Packagist that week, then lock the patch.

| Layer | Pin | Why |
|---|---|---|
| PHP | 8.4.x latest patch | Requested. Installed runtime is 8.4.27. Security fixes through Dec 2028 |
| CodeIgniter | 4.7.5 | Current 4.x release. It runs on PHP 8.4. Pages and JSON live in one app |
| Auth | Session cookie for the website. Bearer token on `/api/v1` | Same user, same role check |
| MySQL | 8.4 LTS, or the local XAMPP MySQL while developing | utf8mb4, InnoDB |
| Composer | 2.8+ | CI4 install |
| Icons | Lucide SVG in `public/icons/` | One outline set |
| Billing | Stripe Billing | Workspace subscription, later phase |

XAMPP Apache on this machine is PHP 8.0, which cannot run CodeIgniter 4.7. Run the site with the PHP 8.4 server (`php spark serve`) until Apache is switched to PHP 8.4. Production is PHP 8.4 FPM, Nginx, MySQL 8.4, HTTPS, and a private disk or S3-compatible bucket for files.

### Repo layout (when coding starts)

```
crm/
  PLAN.md
  app/Controllers/                     Website pages
  app/Controllers/Api/V1/              JSON, same models as the pages
  app/Filters/                         Auth, Tenant, Permission, PlanLimit, Cors, Throttle
  app/Database/Migrations/             All schema. No hand-edited production SQL
  app/Views/                           Quiet Ink layouts
  public/                              Web root, CSS, Lucide SVGs
  public/icons/                        One SVG per icon name
```

### API

Base path `/api/v1`. JSON only. `Authorization: Bearer` access token. Access token 15 minutes. Refresh token rotates and is stored hashed. Lists accept `page`, `per_page` (max 50), `q`, and one sort field. Errors use one shape: `error.code`, `error.message`, `error.fields`.

| Group | Examples | Notes |
|---|---|---|
| Auth | `POST /auth/login`, `/auth/refresh`, `/auth/logout` | Website pages use the session. These routes are the token API |
| Workspace | `GET/PATCH /workspace`, `POST /workspace/invites` | Invite mail is transactional. Invite body includes `role_id` |
| Roles | `GET /permissions`, `GET/POST /roles`, `PUT /roles/{id}/access` | Access body is the list of permission keys. Owner cannot be edited |
| People | `/contacts`, `/companies` | Owner is a user id the client sends |
| Pipeline | `/pipelines`, `/stages`, `/deals` | Stage change is `PATCH` `deal.stage_id` |
| Work | `/tasks`, `/activities` | Reminder is a timestamp column |
| Money | `/products`, `/quotes`, `/invoices`, `/payments` | Send is an explicit POST |
| Files | `POST /files` | Stored outside `public/`. Signed download URL |
| Reports | `GET /reports/summary` | Read only. No side effects |
| Billing | `POST /billing/checkout` | Stripe Customer Portal for plan changes |

## Build order

Estimate for one CodeIgniter developer building the website and the API together. Twenty weeks to the Enterprise feature set.

| Phase | Weeks | Done when |
|---|---|---|
| 0 · Foundation | 1 | PHP 8.4, CI 4.7, Quiet Ink CSS, Lucide icons |
| 1 · Identity | 2 | Signup, login, refresh, workspace, invite, add a role, allocate access, assign that role |
| 2 · Book | 4 | Contacts, companies, pipeline board, manual stage move |
| 3 · Work | 3 | Notes, logged calls, tasks, reminders, files, push |
| 4 · Money | 3 | Products, quotes, send, invoices, record payment |
| 5 · Insight | 2 | Home figures, reports, search, Enterprise audit log |
| 6 · Billing | 2 | Stripe plans, limits filter, customer portal |
| 7 · Polish | 3 | Empty states, icon on every row, dark mode |

### Phase 0, first coding slice

1. `composer create-project codeigniter4/appstarter` on PHP 8.4, in this folder.
2. Session login for the website. Bearer tokens on `/api/v1`.
3. Quiet Ink stylesheet and `public/icons/`.
4. Migrations for `tenants`, `users`, `roles`, `permissions`, and `role_permissions`. Later modules add their own migrations.

Pipeline work starts after a workspace can be created and the website can sign in.

## Acceptance of this plan

Coding starts when you accept this file. Tell me if the name, the INR prices, or the Enterprise-first scope should change before Phase 0.
