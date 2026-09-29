# Systems Intelligenz HRIS — Full Implementation Plan

Status: living document, revised 2026-09-27 against `HRIS_Roadmap_and_Technical_Specification.docx`
**v8.2** (confirms Section A8's e-signature legal-framework note: click-to-sign/typed-consent is
legally sufficient for internal HR acknowledgements; whether a licensed e-signature provider is
warranted for the highest-formality document classes, notably offer/employment contracts, remains
open — v8.1 reframed the Leave Management Accrual & Carry-Over policy as Admin-configurable
defaults, not fixed rules: the 1-year wait exemption defaults to Annual Leave only, per-Leave-Type
`minimumTenureMonths`, Admin-editable; the carryover cap defaults to 10 days, also Admin-editable
— v7 confirmed the two-stage Line-Manager-then-HR approval, resolving v6's flagged C1 discrepancy)
and the UI/UX design artifact at
https://claude.ai/artifact/TzNQ4CdxWDmydhC9VvBEwj ("SI HRIS — Portal UI": Login, 2FA, Home,
Apply Leave, Approvals, Expense Claim).

**What changed from the previous revision of this plan:** the spec itself now mandates
**Laravel 11 + Livewire 3 + Alpine.js + Blade + Tailwind CSS**, replacing the earlier
React/NestJS/Postgres recommendation, and formally adopts the six-screen UI artifact as the
system's design language (Section 3.4) rather than treating it as a placeholder. Both of those
were exactly what the previous revision of this plan had flagged as *assumptions this session
made unilaterally* (Assumptions 1 and 4 below) — they are no longer assumptions, they are the
spec. This revision removes that hedge, folds in three additional build directives, and reports
what changed in the running prototype as a result.

**Update, same session:** the two credentials-blocked items flagged below (MariaDB, Apache/sudo)
were provided mid-session and both are now done — see the "DONE" markers in Sections 4.1.3 and 6.
A third feature was added on request: **client-declared timezone** (browser `Intl` API only, no
IP/GPS — see the new Section 4.5), because the app-wide GMT+1 default alone doesn't correctly
show wall-clock times to a UK-based viewer once the UK goes off British Summer Time.

**Phase 0 — Platform Foundation: complete as of this session.** Every deliverable spec 7.2 lists
for Phase 0 now exists: technology stack (4.1), RBAC engine + custom role-builder (4.6), local
auth + password policy (4.12), 2FA incl. per-role policy (4.8/4.8b), the In-App Notification
Center incl. real Web Push (4.13), the E-Signature & Digital Consent Service (4.15), the generic
REST API framework (4.16), the generic workflow-state-machine engine (4.7), audit logging (4.10),
the notification/email/push framework (part of 4.13), the Renewal & Compliance Reminder Engine
(4.14), object storage (4.9), the scheduled-job registry (its first entry is 4.14's sweep job), and
CI/CD (4.11 — CI only; no deploy target exists yet for the "CD" half). Spec's own Phase 0→1
dependency gate ("RBAC including the custom role-builder, 2FA, API framework, Notification Center,
and audit logging must be functionally complete and tested") is satisfied. This is a prototype
built against real demo data on one dev box, not a production deployment — see each section's own
"Not yet built" notes and Section 5 below for what a real Phase 0 sign-off would still need
(legal/compliance confirmations, real third-party credentials where local stand-ins were used,
etc.) before calling this done in the sense Section 7's roadmap means it.

**Capstone — all six phases (0 through 5) are now complete against spec Section 7's roadmap.**
Domains A through F are built, tested, and live: platform foundation and RBAC (Phase 0); core
employee/org records and self-service (Phase 1); Leave, Time & Attendance (Phase 2, Domain C);
Talent Management — Recruitment, Performance, Discipline (Phase 3, Domain D); Operational &
Financial Extensions — Expense Claims, Assets, Vehicles, Policy Documents, Company Registration
Documents (Phase 4, Domain E); and Engagement, Analytics & Auxiliary Services — Buzz, Dashboard,
Directory, Helpdesk, Help/Support Integration, Mobile API Coverage, Pulse Surveys (Phase 5, Domain
F). Workspace Notifications (F7) and a Shift Roster/Scheduling module were the only two features
explicitly descoped per SI's own direction; nothing else in the spec was silently dropped. Every
phase's own section below ends with its own "complete against spec Section X's deliverable list"
note and a "Verified this session" summary of exactly how it was tested — this capstone note doesn't
replace those, it's the roll-up of them. What this is *not*: a production sign-off. This remains a
prototype built against real demo data on one dev box (local dev mail/queue/broadcast stand-ins
throughout, no real third-party credentials for SSO/SMTP/Zendesk/etc.) — see Section 5 and each
phase's own documented trade-offs for what a real go-live would still need.

## 1. New build directives (this instruction)

1. **UK date format** everywhere in the UI — day before month (`5 Oct 2026`, not `Oct 5, 2026`).
2. **Application timezone GMT+1**, fixed, no daylight-saving drift.
3. **"Sub-units" (the spec's Section B1 term for the department/org-chart tree) display as
   "Departments"** in the UI.

Audit result and what changed:

| Directive | Status | Action taken |
|---|---|---|
| UK date format | Already compliant | Every `->format(...)` call in the app already uses day-first tokens (`j M Y`, `D j M Y`, `l, j F Y`). Audited via `grep -rhoE "format\('[^']*'\)"` — no US-style (`M j, Y`) formats found anywhere. No code change needed. `<input type="date">` fields are unaffected either way: the HTML spec fixes their wire value at ISO `yyyy-mm-dd` regardless of display locale — the browser renders the visible text per the OS/browser locale, not app code. |
| GMT+1 timezone | Fixed this session | `config/app.php`'s `timezone` was `UTC`; changed to **`Africa/Lagos`** rather than `Europe/London`. Lagos is a fixed UTC+1 offset year-round; London floats between GMT+0 and BST (GMT+1) across the year, so it would *not* stay "GMT+1" every day of the year the way the instruction asks. Also set `APP_FAKER_LOCALE=en_GB` in `.env` for consistency (cosmetic — only affects Faker-generated fake data, which this prototype's factories mostly override with explicit values anyway). |
| "Sub-units" → "Departments" | Already compliant | The prototype's `Employee` model already uses a plain `department` string column and every view already labels it "Department" — this was a coincidental match from the original build, not a change made in response to this instruction. Confirmed via `grep -rni "sub-unit\|subunit\|sub_unit"` returning nothing. If the real Phase 1 build later models the full B1 hierarchical org-chart tree (nested-set/closure-table, per spec), keep the *display* label "Department" even though the spec's own prose calls the underlying concept "Sub-unit" — that's a UI wording choice, not a data-model rename, and this plan is the record of that decision for whoever builds B1. |

## 2. Source-of-truth documents

- `HRIS_Roadmap_and_Technical_Specification.docx` (v8.2) — the full functional/technical spec.
  Authoritative for *what* every module must do and for the technology stack (Section 3.5,
  now Laravel/Livewire). This plan does not restate Section 4's module-by-module detail.
  **Versioning convention (set at v8.1, per SI direction):** a revision bumps the minor number
  only (v8.1 → v8.2 → v8.3...) for policy clarifications, confirmations, and other content-level
  changes; the major number moves only for a structural/stack-level revision on the scale of v6's
  technology-stack change. Don't bump the major number for something like "SI confirmed an
  assumption" — that's a minor bump.
- UI/UX artifact (Design canvas) — six artboards: `Main.dc.html` (login), `TwoFactor.dc.html`,
  `Home.dc.html`, `ApplyLeave.dc.html`, `Approvals.dc.html`, `ExpenseClaim.dc.html`. **No longer
  a placeholder** — Section 3.4 (v6) formally adopts this as the system's permanent design
  language; "Phase 0's job is to carry this same shell, type system, and component set through
  every module... rather than re-deriving a new visual language for each one" (spec, verbatim).
- [css/theme.css](css/theme.css) — the hand-authored design-system stylesheet the artifact's
  visual language was built against. See Section 4.2 below for how this reconciles with the
  spec's Tailwind CSS recommendation.

## 3. Reference delivery plan (spec Section 7, re-grounded in Laravel terms — schedule unchanged)

The phase *order*, *durations*, and *dependency gates* are unchanged from the prior stack
revision — only the implementation vocabulary changed (e.g. "Backend/Frontend Engineer" collapses
into one "Laravel Engineer" role per phase, since Livewire renders server-side in the same PHP
process as the module's service layer — spec Section 7.4).

| Phase | Focus | Weeks | Gate |
|---|---|---|---|
| 0 | Platform Foundation — RBAC incl. custom role-builder, 2FA, Notification Center, E-Signature Service, workflow engine, audit log, API framework, Renewal & Reminder engine, object storage, i18n, Employee-ID engine | 1–10 | RBAC/API/workflow/notification complete & tested |
| 1 | Org & Employee Master Data (B1–B3) | 9–18 | Employee entity + reporting-line graph stable |
| 2 | Core HR Ops — Leave (+ accrual/carry-over engine), Time, Attendance | 17–26 | Workflow engine proven on real approval scenarios |
| 3 | Talent Management — Recruitment, Performance, Discipline | 25–36 | — |
| 4 | Operational/Financial — Claims (+ Travel Advance), Assets, Vehicles, Policy Docs, Company Registration Docs | 35–46 | Only needs Phase 1 |
| 5 | Engagement/Analytics — Buzz, Dashboard/HR Analytics, Directory, Helpdesk, Mobile API, Pulse Surveys | 45–56 | Needs data from all prior phases |
| 6 | Hardening, Migration & Launch | 55–64 | All functional phases done |

Total ≈ 64 weeks. Team composition (now: 1 Tech Lead, 1 BA, **2–4 Laravel Engineers** per active
phase — a collapsed backend+frontend role — 1 UX Designer, 1 QA per phase, 1 DevOps, 1 Migration
Specialist from Phase 4), milestones (M0–M6), and risks/mitigations are in the spec's Section
7.4–7.6 verbatim — not reproduced here to avoid drift between two copies.

## 4. What this session built, and how it now maps onto the confirmed stack

### 4.1 Stack — no longer an assumption, now spec-confirmed, both deltas resolved

The spec's Section 3.5 (v6) now specifies exactly what this prototype already used:
**Laravel 11 (PHP 8.3) + Livewire + Alpine.js + Blade**, running on the dev server already on
this machine, matching the team's existing skill set. Two implementation-level deltas were
flagged for review; both are now decided:

1. **Livewire 4, not Livewire 3 — keeping 4 (decided this session).** The spec pins Livewire 3;
   this prototype is built on Livewire 4 (the current stable release at build time —
   `composer require livewire/livewire` resolved `^4.4`). Livewire 4 changes the component model
   significantly: single-file components (SFCs) under `resources/views/pages/` +
   `Route::livewire()` for full pages, versus Livewire 3's class-based `App\Livewire\X` +
   `resources/views/livewire/x.blade.php` pattern the spec's prose describes. This session
   discovered and fixed a real Livewire-4-specific defect (Section 4.3) that doesn't exist in
   Livewire 3's model at all — now a known, documented, and worked-around pattern (inert page
   shell + interactive child components), not an open risk. **Decision: stay on Livewire 4.**
   Record this as a deliberate spec deviation for whoever owns the real build, in case they'd
   rather pin `^3.0` to match the document exactly (a mechanical downgrade at that point, not a
   rewrite — the Blade markup and business logic carry forward unchanged).
2. **Tailwind CSS — adopted this session, alongside `theme.css`.** The spec's Section 3.5/3.4
   (v6) specifies implementing the design system as Tailwind utility classes plus a small
   `tailwind.config` theme extension. Installed `tailwindcss` + `@tailwindcss/vite` (Laravel 13's
   own default versions) and converted `theme.css`'s design tokens (`#D9251E` brand red,
   Sora/Inter/JetBrains Mono, 14px/20px/999px radii, the semantic palette, the shadow scale) from
   a plain `:root {}` block into a Tailwind v4 `@theme {}` block — v4's CSS-first config *is*
   "a small tailwind.config theme extension," just written in CSS instead of JS. This
   simultaneously (a) generates real Tailwind utilities (`bg-primary`, `font-display`,
   `rounded-lg`, `shadow-md`, ...) for any new Blade markup, and (b) keeps emitting the exact same
   `var(--color-primary)` custom properties the six artifact-matched screens' existing component
   classes (`.stat-card`, `.pill-success`, `.btn-primary`, ...) already reference — so **none of
   the pixel-matched, already-verified screens needed to change or re-test**. The Settings page
   (`⚡settings-panel.blade.php`, added this session, no artifact reference to preserve) was
   rewritten end-to-end in pure Tailwind utility classes as the demonstrated pattern for every
   *new* screen going forward — verified visually and functionally identical after the rewrite,
   including its in-place Livewire 2FA-toggle interaction. **Decision: this is the adopted
   approach** — Tailwind utilities for new screens, the existing `theme.css` component classes
   (now themselves backed by Tailwind's token system) left alone for the six screens already
   matched against the artifact, rather than a wholesale rewrite that would re-risk work already
   verified correct for no functional gain.
3. **Database is now MariaDB — DONE this session.** Originally SQLite, because this session had
   no non-interactive MariaDB credential; root credentials were then supplied mid-session. A
   `hris` database was created and `.env` switched (`DB_CONNECTION=mysql`, `DB_DATABASE=hris`),
   confirming the spec's own prediction: **zero application code changed** — Eloquent doesn't
   care which SQL engine sits behind it, `php artisan migrate:fresh --seed` just worked against
   the new connection.

### 4.2 UI/UX — now the confirmed, permanent design language (spec Section 3.4, v6)

Nothing changes here as a result of the spec update except its status: what this session built
against the six-screen artifact was, and remains, correct — the spec now says explicitly that
*this is* the design, not a placeholder for one. Phase 0's remaining UX work (per the spec) is
extending this same shell/type/component set to every other module's screens (Notification
Center panel, Asset/Vehicle views, Discipline case review, the Admin role-builder, etc.), not
inventing a new visual language.

### 4.3 What's built (routes, pages, and the Livewire-4 defect found and fixed)

| Route | Mockup | Notes |
|---|---|---|
| `/login` | `Main.dc.html` | Inert shell + `login-form` child (split out this session — see 4.10's Livewire-morph bugfix): real session auth against seeded users; password-visibility toggle is pure Alpine |
| `/login/setup` | *(no mockup — new this session)* | Inert shell + `two-factor-setup` child: first-time 2FA enrollment (TOTP QR or email), see 4.8 |
| `/login/verify` | `TwoFactor.dc.html` | Inert shell + `two-factor-verify` child: real TOTP/email/backup-code verification, see 4.8 (was simulated — Assumption 3, now resolved) |
| `/` (home) | `Home.dc.html` | Real leave/claim/approval counts from DB; clock in/out is a child Livewire component |
| `/leave/apply` | `ApplyLeave.dc.html` | Child Livewire component: live balance calc, writes a `leave_requests` row |
| `/approvals` | `Approvals.dc.html` | Child Livewire component: tabs + list/detail pane over `leave_requests`/`expense_claims` |
| `/claims/create` | `ExpenseClaim.dc.html` | Child Livewire component: add/remove lines, live total, writes `expense_claims` |
| `/admin/audit-log` | *(no mockup — new this session)* | Inert shell + `audit-log-browser` child: entity-mutation and security/login event browser, see 4.10, Admin-only |
| `/profile` | *(no mockup — extends the pattern)* | Profile-picture upload via Media Library (4.9) + `account-security` child: 2FA method/backup-codes/trusted-devices self-service (4.8) |
| `/settings` | *(no mockup — extends the pattern)* | Company logo upload via Media Library (4.9) + the project-wide 2FA on/off switch, HR/Admin only |

**Defect found and fixed this session:** Livewire 4's "full-page" single-file components
(rendering an entire `<html>` document, registered via `Route::livewire()`) cannot safely carry
their own `wire:click`/`wire:model.live` — the in-place AJAX morph targets the whole document
root and silently breaks (a blank page after the first interaction, reproducible on every such
page). The fix, now applied consistently across every page: each route's top-level component
stays inert (renders the static shell + breadcrumb only); every actually-interactive region
(the approvals board, the leave form, the expense-line editor, the clock toggle, the settings
panel, the profile-photo uploader) is a normal **child** Livewire component with an ordinary
`<div>` root, embedded via `<livewire:component-name />`. Login's password show/hide toggle is pure Alpine.js `x-data`
instead, since it has no server-side effect and doesn't need a Livewire round-trip at all
(2FA's original prototype also had a purely-cosmetic Alpine method switch, but the real
implementation in 4.8 determines the method server-side from the user's actual enrollment, so
that particular toggle no longer applies). This pattern —
inert page shell + interactive child components — is the one to keep for every new screen,
regardless of whether the real build stays on Livewire 4 or downgrades to match the spec's
Livewire 3 (Livewire 3 doesn't have "full-page SFCs" as a concept at all, so this specific defect
mode can't occur there — a point in favour of the downgrade if it's cheap).

### 4.4 Data model (minimal subset of spec Section 4 B2/C1/E1)

`settings` (singleton — company name, 2FA on/off switch; logo is now a Media Library attachment,
not a column — see 4.9), `users` (+ `role_id`, `timezone`, plus 4.8's `two_factor_method`/
`two_factor_secret`/`two_factor_confirmed_at`), `employees` (avatar is likewise now a Media
Library attachment, not an `avatar_path` column — see 4.9), `leave_types`, `leave_entitlements`,
`leave_requests`, `claim_events`, `expense_types`, `expense_claims`, `expense_claim_lines`, the
RBAC engine's own tables (`roles`, `screens`, `data_groups`, `role_screen_permissions`,
`role_data_group_permissions` — see 4.6), the workflow engine's `workflow_transitions` (see 4.7),
4.8's `two_factor_backup_codes`/`two_factor_email_codes`/`trusted_devices`, and 4.9's `media`
(Media Library's own table). Approvals are resolved live via `WorkflowEngine::pendingFor()`
against each record's `status` column — see 4.7 for the real two-stage Line-Manager-then-HR
routing this replaced the earlier `status = 'pending_hr'` stand-in with.

### 4.5 Client-declared timezone (added this session, on request)

The app-wide default (`config('app.timezone')` = `Africa/Lagos`, fixed GMT+1 year-round) is
right for *where the data is anchored*, but wrong for *how it's read* by a UK-based viewer once
the UK goes off British Summer Time (GMT+0 in winter) — a single fixed offset can't be correct
for viewers in both jurisdictions simultaneously. The user asked for this to be solved via IP or
GPS geolocation; that's explicitly excluded by the spec itself (Section C3, Attendance: "captured
both in UTC and the client's local time plus an explicit client-declared timezone — **no
IP-address or GPS geolocation capture** in the reference system — that would be new scope, not a
port"). Built the spec's actual mechanism instead:

- `users.timezone` (nullable IANA string, e.g. `Europe/London`) — populated by a tiny client-side
  script (`resources/views/components/timezone-capture.blade.php`, included in both layouts) that
  reads `Intl.DateTimeFormat().resolvedOptions().timeZone` — a value the browser already knows
  about itself — and POSTs it to `App\Http\Controllers\TimezoneController` once per session if it
  differs from what's on file. No location permission prompt, no coordinates, no IP lookup.
- **Every stored instant stays anchored to the fixed GMT+1 default** — this column only changes
  how a timestamp is *rendered*, never what's written to the database, avoiding the correctness
  trap of two viewers' "same moment" being stored as different raw strings with no timezone
  marker to disambiguate them later.
- `User::displayTimezone()` resolves to the declared zone (validated against
  `DateTimeZone::listIdentifiers()`) or falls back to the app default. Wired into the two places
  that actually show wall-clock time to a specific viewer: the Home dashboard's greeting/date line
  and the clock-in/out pill (`⚡clock-toggle.blade.php`) — both `->clone()->setTimezone(...)`
  before formatting, never mutating the underlying stored value.
- Verified end-to-end: a real browser session (declaring `Europe/London`) round-tripped through
  the capture endpoint and persisted to `users.timezone` correctly.

### 4.6 RBAC / permission-matrix engine (spec Section 3.2/A2) — the first real Phase 0 platform service

Everything above this point was UI-layer work sitting on top of one hard-coded role check
(`isHr()`). This is the first piece of actual Phase 0 platform infrastructure the spec calls
for: the **three-tier permission model**, built as a first-class, data-driven engine rather than
a role string a guard clause switches on — "there is no hard-coded 'if role === HR_OFFICER'
logic anywhere in the guard layer" (spec A2), and that's now literally true of this codebase.

**Data model:** `roles` (name, slug, `is_system_role`, `is_situational`), `screens` (one row per
route — `home`, `leave.apply`, `claims.create`, `profile`, `approvals`, `settings`,
`admin.roles`), `data_groups` (`employee_personal_details`, `compensation`, `leave_requests`,
`expense_claims`, `disciplinary_case`, `approvals_queue` — `compensation` and
`disciplinary_case` are named per the spec's own examples but have no backing module yet, so
they exist in the matrix as forward-compatible placeholders, not wired to anything real). Two
join tables carry the actual grants: `role_screen_permissions` (can this role see this screen)
and `role_data_group_permissions` (per role × data group, a `scope` — none/self/
self_subordinates/all — and a `level` — none/view/view_edit/view_edit_delete). `users.role`
(the old plain string) was replaced outright with `users.role_id`, a real foreign key.

**The five system roles, seeded exactly per spec A2's table:** Admin (full platform + full
employee data + full sensitive data), HR Admin (no platform admin, full employee + sensitive
data), HR Officer (full *standard* HR data, explicitly `none`/`none` on Compensation and
Disciplinary Case), ESS (self scope everywhere, self+view-only on Compensation), and
**Supervisor** — seeded with `is_situational = true` and deliberately *zero* directly-assigned
users: per spec, "Supervisor status is computed, not assigned," so no user's `role_id` ever
points at it. `App\Services\PermissionService::effectiveRoles()` computes it live instead —
anyone whose `Employee` has subordinates gets the Supervisor role's grants layered on top of
their own base role for that request, every time, never stored.

**Enforcement:** `App\Http\Middleware\EnsureScreenAccess` (`->middleware('screen:settings')`)
replaced the old `EnsureHrRole` — every route now resolves against the matrix via
`PermissionService::canViewScreen()`, not a role-name string comparison. The sidebar (both
desktop and mobile nav) was rewired the same way, so a link a user can't actually reach is never
rendered — closing a real gap the old `isHr()`-based nav had (it showed Supervisor users an
"Approvals" link that then 403'd, since screen visibility and route access were two different,
unsynchronized checks; they're now the same check).

**The role-builder** (`/admin/roles`, Admin-only): a list of all five system roles (each showing
its live screen/data-group counts and assigned-user count) plus a "clone into a new custom role"
form — spec A2's explicit pattern, "a new role is typically built as 'start from HR Officer,
then add X' rather than from a blank matrix." A cloned role lands on `/admin/roles/{id}`, an
editable matrix (screen checkboxes + per-data-group scope/level selects) with Save, and a
guarded Delete (blocked with an inline error while any user is still assigned — verified both
ways: blocked while assigned, succeeds once reassigned). System roles render the identical
matrix view but every input is `disabled` — read-only, per spec's "the four system roles...
cannot be edited or deleted, to keep a known-good baseline always available."

**Verified this session:** logged in as Admin, HR Admin, and ESS in turn and confirmed each
sees exactly the nav items and reaches exactly the routes the matrix says they should — including
that HR Admin (full employee data, *no* platform admin) correctly gets a 403 on `/admin/roles`
while still reaching `/approvals`, the precise distinction spec A2 draws between the two roles.

**Not yet built** (this is a first slice, not the full spec A2): the API-permission tier (no
versioned REST API exists yet to gate — see PLAN.md's original Section 1 scope note);
`PermissionService::scopeFor()` (the data-group scope resolver) still isn't called by anything —
the Approvals screen's Supervisor-scoping that landed in §4.7 below goes through the *workflow*
engine's own actor-tag resolution instead (a record's employee's supervisor_id), a parallel and
equally-correct mechanism but a second one, worth consolidating onto one shared resolver later;
permission-change audit logging (spec A2: "every matrix change is written to the permission audit
trail") — saves currently aren't logged anywhere.

### 4.7 Generic workflow-state-machine engine (spec Section 3.2) — the second Phase 0 platform service

The other half of Phase 0's foundation, per the spec: "a single reusable engine of the shape
`(workflow, state, role, action) → resulting state` should back every approval process... 'What
can this user do right now' is answered by resolving the record's current state and the caller's
applicable role(s) against this table." Built this session as `workflow_transitions`
(workflow, from_state, actor, action, label, to_state) plus `App\Services\WorkflowEngine`, and it
replaced every hand-written status-field check in Approvals, closing a real gap in the process —
see below.

**Spec-vs-artifact discrepancy — now resolved, confirmed by SI:** spec Section C1's v6 prose said
leave approval is "single-level... by either an administrator or the employee's supervisor,
whoever acts first," but the adopted UI artifact's own on-screen copy says "Two-stage approval:
Line Manager, then HR" and shows a sequential Line-Manager-then-HR timeline. SI has confirmed the
two-stage sequential flow — matching what's actually on screen and already built/tested — as the
permanent design; the spec document itself has been updated to v7 to match (Section C1's
overview and approval-bullet prose now read "two-stage sequential approval (Line Manager, then
HR)"), so this is no longer an open discrepancy.

**The actor model:** alongside real RBAC role slugs (`hr_admin`, `hr_officer`), the matrix
recognizes two situational tags resolved live per record, the same live-resolution pattern
`PermissionService` already uses for the Supervisor role: **`owner`** (the record's own employee)
and **`supervisor`** (specifically *that employee's* supervisor — a tighter check than "is a
supervisor of someone," it's "is a supervisor of the person on this exact record").
`WorkflowEngine::actorTags()` computes this per (user, record) pair; nothing is stored.

**Real behavioral change, not just infrastructure:** the previous prototype auto-skipped the Line
Manager stage entirely — every self-service submission went straight to HR (documented as
Assumption 2 in the prior revision of this plan) because there was no Supervisor-facing screen
for it to land on. Building the engine made building that screen nearly free: the *same*
Approvals screen now serves both audiences, because `WorkflowEngine::pendingFor()` returns
"every record this specific user has a legal action on" rather than a hard-coded
`status = 'pending_hr'` filter. A supervisor now sees only their own reports' `pending_manager`
items; HR sees `pending_hr` items; a record moves from one queue to the other automatically the
moment it's approved. `RbacSeeder`'s Supervisor role grant was updated to include the `approvals`
screen and `approvals_queue` data group (`self_subordinates`/`view_edit`) accordingly. Apply
Leave/Expense Claim's submit actions now set `status` to `pending_manager` (real routing) rather
than `pending_hr` (the old auto-skip) — an employee with no `supervisor_id` still routes straight
to HR, an explicit rule at the point of submission, not a bypass of the matrix.

**Verified end-to-end this session** with a new real login (`emeka@systemsintelligenz.com`, an
actual line manager): submitted a fresh leave request as his report Adaeze and confirmed it
stopped at `pending_manager` (previously it would have skipped straight past); logged in as Emeka
and confirmed the Approvals screen showed *only* his own report's item, correctly labeled "Line
Manager approval" with a dynamically-sourced "Approve" button (not HR's "Give final approval");
approved it and confirmed it correctly re-appeared in HR's queue, re-labeled "HR final approval,"
with the trail's Line-Manager step now shown done; approved it there too and confirmed the final
record carries both `manager_approved_by` and `hr_approved_by` stamped by the two different users
who actually acted, in the right order.

**Not yet built:** the `mark_paid` transition for Expense Claims is seeded in the matrix (spec
E1's payment-tracking step) but no screen calls it yet; Timesheet/Attendance/Recruitment/
Performance workflows aren't modeled — Leave and Expense Claim are the only two `workflow` keys
that exist, since they're the only modules this prototype actually has. ~~Notification fan-out on
transition~~ — **built**, see 4.13: `apply()` now calls `NotificationService::notify()` for
whoever can act next from the record's new state.

### 4.8 Full two-factor authentication (spec Section A1) — replaces the simulated stand-in

Assumption 3 (below) is resolved: the previous "any 6-digit code is accepted" stand-in is gone,
replaced by real TOTP (RFC 6238, `pragmarx/google2fa-laravel` + `pragmarx/google2fa-qrcode` +
`bacon/bacon-qr-code`), real emailed one-time codes, and real trusted-device tracking — all
user-selectable at enrollment and switchable later. New tables: `two_factor_backup_codes`,
`two_factor_email_codes`, `trusted_devices`, plus three new nullable columns on `users`
(`two_factor_method`, `two_factor_secret` — encrypted via Eloquent's `encrypted` cast,
`two_factor_confirmed_at`). All additive; no existing data touched.

**The two methods:** TOTP via any authenticator app, enrolled by scanning a QR code (rendered as
an inline SVG data URI — this box has no `imagick` extension, so `bacon/bacon-qr-code` falls back
to its SVG backend automatically) or entering the secret manually, then confirmed by entering a
real code before it's persisted (a mis-scanned QR never locks someone out — nothing is saved
until `TwoFactorService::confirmTotpEnrollment()` verifies it). Ten single-use backup codes are
generated and shown exactly once at that point. Email OTP needs no enrollment step at all — a
6-digit code, hashed at rest, 10-minute expiry, resend cooldown — sent to the user's existing
address. Both converge on the same sign-in verification screen and the same trusted-device skip.

**Trusted devices, done properly this time:** the previous prototype's cookie held the user's raw
ID — any cookie with a valid-looking ID would be trusted, forever. The `trusted_devices` table now
mirrors Laravel's own remember-token pattern: the cookie carries a 64-character random opaque
token; only its SHA-256 hash is ever stored; `TwoFactorService::isTrustedDeviceCookieValid()`
looks up that hash, checks it hasn't expired (30 days, `config('twofactor.trusted_device_days')`),
and touches `last_used_at` at most once an hour. Revoking a device (or "revoke all," from Account
Security) simply deletes its row — the cookie itself is now inert.

**New pages, both built on the mandatory inert-page-plus-child-component pattern (4.3):**
`/login/setup` (shown the first time 2FA is required and the user hasn't enrolled — method choice,
QR scan or email confirmation, one-time backup-code reveal) and a reworked `/login/verify` (real
verification against whichever method the user chose, with a backup-code fallback always
available for TOTP users). Both are inert page shells (`⚡login-setup.blade.php`,
`⚡login-verify.blade.php`) wrapping a `two-factor-setup` / `two-factor-verify` child component —
**the same Livewire-4 full-page-`wire:click` defect from 4.3 reproduced here first-hand**: the
first draft put `wire:click` handlers directly on the page-level component, which threw "Public
method not found" on the very next interaction; moving all state and interactivity into a child
component (leaving the page purely as `<x-layouts.guest>...<livewire:two-factor-setup /></...>`)
fixed it immediately, and is now proven twice in this codebase, not just theorized.

**Account Security**, a new self-service section added to the existing `/profile` page (no new
route or RBAC screen needed — `profile` is already granted to every role): shows the current
method, unused-backup-code count, lets a user switch methods or regenerate backup codes, and lists
trusted devices with per-device and revoke-all controls.

**The project-wide switch stays OFF, as instructed:** `Setting.two_factor_enabled` still defaults
to `false` (unchanged) and was verified OFF both before and after this session's testing. Every
new page and the reworked middleware still check it first and skip straight through when off,
same as before — only *what happens when it's on* changed.

**Verified end-to-end this session**, toggling the switch on temporarily and reverting it after:
enrolled the admin demo user via TOTP (real secret, a QR that actually decodes, a code computed
independently via `Google2FA::getCurrentOtp()` and accepted); regenerated backup codes and
confirmed the old set stopped working; logged out and back in, used a saved backup code to clear
`/login/verify`, and confirmed a trusted-device cookie was issued; revoked that device from
Account Security and confirmed the DB row was gone; switched the same user to email OTP and
confirmed a real email rendered through `MAIL_MAILER=log` (`storage/logs/laravel.log`) with a
working 6-digit code that verified correctly. **One fix made mid-testing:** `sendEmailCode()`
originally used `Mail::queue()`; with `QUEUE_CONNECTION=database` and no queue worker running in
this dev environment, a queued OTP email would never actually be delivered — changed to
`Mail::send()` (synchronous), which is also just the correct choice for a "the user is waiting on
this screen right now" email regardless of environment. All test enrollment/devices were cleared
from the admin demo user afterward so the seeded demo data is back to its pristine state.

**Not yet built:** SMS as a third method (spec doesn't require it, this session didn't add it);
rate-limiting login attempts themselves (separate from the email-OTP resend cooldown, which is
real); a 2FA lifecycle audit log (spec A1: "all 2FA lifecycle events... written to the login/
security audit log" — no audit-logging mechanism exists yet at all, see 5's open items).

### 4.8b Per-role 2FA policy (spec Section A1's "Admin policy control") — added this session

Spec A1: "the Admin role can, org-wide or per-role, require 2FA enrollment, restrict which
method(s) are permitted (e.g., ... require TOTP specifically for elevated roles such as Admin/HR
Admin while leaving Email OTP available to ESS), disable the trusted-device skip ... and set the
trusted-device expiry window." Four new nullable/defaulted columns on `roles`
(`two_factor_required`, `two_factor_allowed_methods` JSON, `two_factor_trusted_device_allowed`,
`two_factor_trusted_device_days`), all additive, defaulting to "required, both methods, trusted
device allowed, no override" — i.e. identical to pre-this-session behavior until an Admin actually
changes something. `Setting.two_factor_enabled` remains the master kill switch (off means off for
everyone, full stop, regardless of these columns); the columns only refine behavior once it's on.

**Resolution is by the user's base role**, not the situational Supervisor overlay — 2FA is an
identity/security property, not a screen-access grant, so `TwoFactorService::isRequiredFor()` /
`allowedMethodsFor()` / `trustedDeviceAllowedFor()` / `trustedDeviceDaysFor()` all read
`$user->role` directly. The new `⚡role-two-factor-policy.blade.php` child component (embedded on
the existing `/admin/roles/{role}` page, alongside the permission-matrix editor) is deliberately
**editable for system roles too**, unlike the matrix editor — the spec's own example only makes
sense applied to Admin/HR Admin, both system roles. The situational Supervisor role shows an
explanatory note instead of controls, since its policy is never consulted.

**Every enrollment/verification/account-security surface now consults the role policy**: the
method-choice screen shows only allowed methods and auto-skips straight to the single method's
flow when a role permits just one; the trust-device checkbox disappears entirely (not merely
unchecked) when a role disallows it; a user whose already-enrolled method a policy change later
disallows is treated as needing re-enrollment (`TwoFactorService::needsReEnrollment()`) and routed
back through `/login/setup` with a banner explaining why, rather than silently locked out or
silently allowed to keep using a now-forbidden method.

**Verified end-to-end this session**, toggling the master switch on temporarily: set the Admin
role to TOTP-only with trusted-device skip disabled; logged in as the admin demo user and
confirmed the setup screen skipped straight to the QR step (no method-choice screen, since only
one method was allowed) with no trust-device checkbox rendered at all; completed enrollment with a
real computed TOTP code; confirmed zero rows were written to `trusted_devices`; logged out and
back in and confirmed `/login/verify` was required again (not skipped), with a note explaining
why. Reverted the Admin role's policy and the admin demo user's 2FA state back to defaults
afterward, and the master switch back to off.

### 4.9 Object storage (spec Section 3.5) — replaces local-disk-only uploads

Assumption 5 (below) is resolved for the two upload flows this prototype actually has (profile
photo, company logo): both now go through `spatie/laravel-medialibrary` (newly installed) instead
of a raw `*_path` column plus a hand-written `Storage::disk('public')->store()`/`delete()` pair.
`Employee` and `Setting` both implement `HasMedia`/`InteractsWithMedia` with a `singleFile()`
collection each (`avatar`, `logo`) — a new upload automatically supersedes and deletes the
previous file rather than accumulating orphans, and `avatarUrl()`/`logoUrl()` now read
`getFirstMediaUrl()` instead of hand-rolling a disk URL. The old `avatar_path`/`logo_path` columns
were confirmed empty across all seeded demo data, then dropped outright (no backwards-compat
shim) rather than left as unused dead columns.

**Still Laravel's `Storage` abstraction underneath, per spec, not a Media-Library-specific
mechanism:** `config('media-library.disk_name')` defaults to `env('MEDIA_DISK', 'public')`, the
same local disk (`storage/app/public`, symlinked to `public/storage`) every upload has always used
in this dev environment. `config/filesystems.php`'s `s3` disk definition (already present,
unchanged) is what a real deployment points at — switching over is a `MEDIA_DISK=s3` plus real
`AWS_*` env values away, zero application-code changes, exactly the abstraction the spec asks for.
No S3-compatible credentials exist in this environment to actually exercise that path.

**Verified this session** via direct model-level testing (file-picker automation isn't available
in this session's browser tooling): uploaded a real PNG to an `Employee`'s `avatar` collection,
confirmed `avatarUrl()` resolved to a working, HTTP-200-serving URL; uploaded a second file to the
same collection and confirmed the count stayed at one (old file deleted, not accumulated); did the
same for `Setting`'s `logo` collection. Both `/profile` and `/settings` render correctly against
the new code path with no media attached (initials/placeholder fallback, as before). Test uploads
were removed afterward.

**Not yet built:** the spec's other file-attachment surfaces (leave/claim receipts, Company
Registration & Compliance Documents, etc.) don't exist as upload flows in this prototype yet, so
they haven't been wired to Media Library — when they're built, they should follow this same
`HasMedia` + `singleFile()`-or-multi-collection pattern rather than reintroducing a raw path
column. Encryption-at-rest for attachments (also named in Assumption 5) is still not implemented —
Media Library doesn't provide it out of the box. Audit logging for attachment access
(upload/view/download) is now possible via 4.10 below but hasn't specifically been wired to
Media Library events yet.

### 4.10 Audit logging (spec Section 3.2/A1) — the third Phase 0 platform service

Spec 3.2: "all entity mutations should be captured automatically... not by manual instrumentation
in each service"; spec A1 separately wants "a login audit log (user, role, timestamp, 2FA outcome,
2FA method used)" and "all 2FA lifecycle events... written to the login/security audit log." Two
deliberately separate mechanisms, matching that split: `audit_logs` (generic entity mutations —
create/update/delete, field-level before/after diffs) and `security_events` (login attempts and
2FA lifecycle events) — the spec itself calls out the reference system's two *overlapping* (i.e.
redundant/inconsistent) audit mechanisms as a mistake not to repeat; this is two *complementary*
mechanisms instead, one per concern.

**Generic mutation logging is a one-line opt-in, not manual instrumentation per model:**
`App\Traits\Auditable`, added to `User`, `Role`, `Employee`, `LeaveRequest`, `ExpenseClaim`, and
`Setting`, hooks Eloquent's `created`/`updated`/`deleted` model events and writes one `AuditLog`
row per mutation automatically — nothing in the controllers/Livewire components that actually
change these records needed to change. Credential-shaped fields (`password`,
`two_factor_secret`, `remember_token`, backup-code/email-code hashes) are always redacted to
`[redacted]`, never written to the log even hashed, regardless of which model the trait is on.

**A real cast-consistency bug found and fixed while testing this**: the first version compared
`getChanges()`'s raw pre-cast value against `getOriginal()`'s cast value for the "before" side of
a diff, so a JSON-cast column (e.g. `Role::two_factor_allowed_methods`) rendered asymmetrically —
`["totp"]` on one side, a double-escaped `"[\"totp\",\"email\"]"` string on the other. Fixed by
reading both sides through the model's own cast pipeline (`getOriginal($key)` and
`getAttribute($key)`, not `getChanges()`'s raw value) so a diff is always comparing like with
like. Caught by actually expanding a diff row in the browser, not by inspection.

**Security events are recorded at the point of action, not inferred after the fact**: login
success/failure (`⚡login-form.blade.php`), 2FA enrollment/method-change/disenrollment/backup-code-
regeneration/device-trust/device-revoke (`TwoFactorService`), 2FA verify success/failure
(`⚡two-factor-verify.blade.php`), and per-role policy changes (`⚡role-two-factor-policy.blade.php`)
each call `SecurityEvent::record()` right where the thing actually happens — mirroring this
codebase's existing "no manual instrumentation scattered around" preference by keeping every call
a single line at an already-obvious call site, rather than trying to infer intent from generic
model-diff rows after the fact (which is exactly what the generic `audit_logs` mechanism is for
instead).

**A second, unrelated Livewire-4 full-page-morph bug found and fixed while testing this**: a
failed login (`Auth::attempt()` returns false) calls `$this->addError()` and returns, which
re-renders the page-root component *in place* rather than redirecting — the same "full-page SFC
re-rendering itself" defect from 4.3, just triggered by a validation-style error re-render this
time instead of a bare `wire:click`. This had been latent in `⚡login.blade.php` since it was
first built, undiscovered because every prior test of the login page used correct credentials on
the first try (which redirects, and never hits this path). Fixed the same way as every other
instance of this defect: split into an inert `⚡login.blade.php` page and a new `⚡login-form.blade.php`
child component carrying all the state and the `wire:submit`. Reproduced the blank-page failure
first, confirmed the server's Livewire response actually contained fully correct HTML (so the bug
is specifically in the client-side full-document morph, not the server logic), then confirmed the
fix with the same failed-then-successful login sequence.

**A new admin screen, `/admin/audit-log`** (Admin-only, matching `/admin/roles`'s sensitivity
level): two tabs — Data changes (filterable by actor/entity/date, with a per-row expandable
field-level diff) and Security & login (filterable by actor/event/date) — satisfying spec 3.2's
"Audit log browser: filterable list (entity, action, actor, date range) with per-entry field-level
before/after drill-down." Built as `⚡admin-audit-log.blade.php` (inert) + `⚡audit-log-browser.blade.php`
(child), same pattern as everywhere else.

**Verified end-to-end this session**: edited the Admin role's 2FA policy and confirmed both a
generic `Role` field-diff row and a named `two_factor_policy_changed` security-event row appeared,
each independently correct; deliberately triggered a failed login and confirmed a `login_failed`
row with the attempted email (no user row exists yet to attach to); logged in successfully and
confirmed `login_succeeded`. All test rows truncated from both tables afterward — they're pure
event logs with no seeded baseline to preserve, unlike the demo business data.

**Not yet built:** the audit log itself isn't yet retained/purged per spec A6's records-retention
policy (Assumption area for a future session); Media Library upload/view/download events (spec
E5's "every upload, view, download, and renewal action... is written to the audit log" for Company
Registration Documents specifically) aren't wired up, since that module doesn't exist yet;
`RoleScreenPermission`/`RoleDataGroupPermission` matrix-cell changes aren't wrapped in `Auditable`
(only the parent `Role` row is), so a permission-matrix edit shows as a `Role` row touch without
the actual before/after grant detail — spec 118's "full audit trail of every permission-relevant
change" would want that filled in before this is considered complete for A2.

### 4.11 CI/CD pipeline (spec Section 7.2) — git init + GitHub Actions

Nothing could exist here before this session: `/var/www/html/hris` (the canonical copy) had no
version control at all. Ran `git init` (branch `main`; the Laravel skeleton's own `.gitignore`
already excludes `vendor/`, `node_modules/`, `.env`, and framework caches) and made an initial
commit capturing everything built through the audit-logging session, then a second commit for
per-role 2FA policy + audit logging + the login-form bugfix. This Phase 0 completion is the third.
The courtesy copy at `/home/michael/Downloads/HRIS/app` is deliberately **not** its own git
repo — it stays a plain rsync mirror of the canonical copy, so there's one history, not two
diverging ones.

**GitHub Actions** (`.github/workflows/ci.yml`) — chosen because it needs no separate account or
credentials to author, and activates automatically the moment this repo gets pushed to a GitHub
remote (still doesn't have one). Pipeline: checkout, PHP 8.3 + Node 22 setup, `composer install`,
`npm ci && npm run build`, `php artisan migrate` against the test `.env` (SQLite in-memory, per
`phpunit.xml`'s existing config — no MySQL service container needed), `vendor/bin/pint --test`,
`php artisan test`.

**A real, if minor, correctness fix made in the course of standing this up**: `vendor/bin/pint`
had never been run on this codebase before — every file pint would touch was untouched from
whenever it was written, so a CI pipeline enforcing style from day one would have been immediately
red for reasons unrelated to any actual defect. Ran `pint` once to establish a clean baseline
(auto-formatting only — import ordering, brace placement, PHP 8.4-style `new Foo` without
parens on argumentless constructors — no logic changed, verified via `php -l` on every touched
file plus a full test-suite pass) rather than ship a pipeline that starts broken. Also fixed the
stock `tests/Feature/ExampleTest.php`, which asserted `GET /` returns 200 — it's always redirected
to `/login` for a guest in this app, so that test had been silently failing (not "no tests exist,"
but "the one test that exists has asserted the wrong thing since day one") until this session.

**Not yet built:** no remote exists yet (this is a purely local repo) and no other CI provider was
evaluated, since GitHub Actions needed no comparison to just start with; a deploy stage (the "CD"
half) is intentionally not attempted — there's no target environment (staging/production) to
deploy to yet, only this dev box.

### 4.12 Password policy (spec Section A1) — the fourth Phase 0 platform service

Spec: "a configurable password policy: min/max length, required character classes..., whether
spaces are allowed, and a minimum required strength tier"; and separately, "enforce on login: a
policy tightened after an account was created must be satisfied at next login, or the session is
invalidated and the user is routed through a forced password-change flow." Both built for real —
no password-set/change UI of any kind existed in this prototype before this session (only the
login form's password *input* existed; there was no way for a user to ever change their own
password).

**Config lives on `Setting`** (five character-class/length booleans/ints, `password_min_length`,
`password_max_length`, plus `password_policy_version`) rather than a fixed policy — an Admin
changes it from the existing Settings page's new "Password policy" card, read live by
`App\Rules\PasswordPolicy` (a `ValidationRule` applied everywhere a password is set) so a policy
change takes effect on the very next password set, no deploy required. `Setting::booted()`'s
`saving` hook bumps `password_policy_version` automatically — but only when a policy field
actually changed (not on every unrelated Settings save), which is what "enforce on login" compares
each `User.password_policy_version` against via the new `EnsurePasswordPolicyMet` middleware
(same route-gate pattern as `EnsureTwoFactorVerified`), redirecting a stale user to a new forced
`/account/update-password` page before anything else.

**One real mass-assignment bug found and fixed via actual testing, not inspection**: the first
version of the forced-change flow updated the password successfully but silently never advanced
`password_policy_version`, because that column wasn't in `User::$fillable` — Eloquent dropped it
from the `update()` call with no error, so the middleware kept redirecting back to the same forced
page forever (an infinite loop a code read alone would very plausibly have missed, since nothing
about the calling code looks wrong). Caught by actually completing the forced-change flow in the
browser and watching it redirect back to itself.

**Self-service password reset** (spec: "emailed, single-use, time-expiring reset codes," wired to
the login page's previously-dead `Forgot password?` link) reuses Laravel's own `password_reset_tokens`
table (already present from the framework skeleton, just never used) but stores a hashed 6-digit
code instead of the framework's long default token — matching this codebase's already-established
2FA-email-OTP pattern (`⚡forgot-password-form.blade.php`, `App\Mail\PasswordResetCodeMail`, 30-minute
expiry) rather than Laravel's link-based `Password` broker. **A second real gap found in the same
testing pass**: the reset flow flashed a "Password reset" status message to the session before
redirecting to `/login`, but the login page never rendered any `session('status')` flash at all —
fixed by adding one to `⚡login-form.blade.php`.

**Verified end-to-end this session**: tightened the policy (required a special character) via
Settings and confirmed the very next protected-page request forced the demo admin through
`/account/update-password`, which correctly rejected the old, now-noncompliant password requirement
messaging and accepted a compliant new one, advancing `password_policy_version` and releasing the
redirect loop; requested and completed a real emailed password reset for two different demo users,
including confirming the login-page success flash now renders. All test passwords reverted to the
seeded `password` afterward, and `password_policy_version` reset to 1 on both `Setting` and every
`User` row.

**Not yet built:** a minimum-strength/entropy tier (spec mentions this alongside length/character
rules — only length and character-class rules exist here); "re-authentication step for sensitive
administrative actions" (spec A1, unrelated to password policy itself but adjacent) — nothing in
this prototype currently demands a re-auth step; blocking password reset for a terminated employee
(spec's exact wording) has no employee-status/termination concept to check against yet (Domain B2,
unbuilt) — every account with a usable email can currently request a reset.

### 4.13 In-App Notification Center (spec Section A7) — the fifth Phase 0 platform service

Spec: "every user, while logged in, has a persistent notification panel... independent of — and
in addition to — the existing email channel," fanning out to three channels (in-app, email,
browser push) "from one event," so "a module only needs to raise one notification event... no
per-module push logic required." Built as `App\Services\NotificationService::notify()`, called
from `App\Services\WorkflowEngine::apply()` (every approval-matrix transition now notifies whoever
can act next — the actor-tag vocabulary already built for 4.7, aimed at "who holds this tag"
instead of "does this user hold it") and directly from `⚡leave-apply-form.blade.php`/
`⚡expense-claim-form.blade.php` at submission time (spec explicitly lists "apply" alongside
"approve/reject/cancel/assign" as a notification-triggering status change — submission isn't a
*transition* in the workflow-matrix sense, so `WorkflowEngine::apply()` never sees it, and needed
its own call site).

**Browser push is real, VAPID-signed Web Push** — not a stub. `minishlink/web-push` needs the PHP
`bcmath` or `gmp` extension for the VAPID JWT signing math; neither was installed on this shared
box (also running `pim` and `orangehrm-dev`). Asked before installing anything system-wide;
installed `php8.3-bcmath` via apt (confirmed: purely additive, can't affect either sibling app,
verified with an Apache reload and a live site check immediately after) rather than silently
building a stub. Generated a self-signed VAPID keypair directly (`VAPID::createVapidKeys()`) — Web
Push needs no third-party provider account at all, just this keypair talking directly to each
browser's own push service. `public/sw.js` is a minimal service worker (push + notification-click
only, no offline caching — spec explicitly excludes installability); `x-push-notifications`
registers it and exposes `window.__enablePush()` for the bell panel's "Enable browser push
notifications" prompt to call.

**Real-time in-app delivery is `wire:poll` (15s), not a WebSocket push over Laravel Reverb** — this
dev box has no Reverb server running, so short polling is the honest, working stand-in, same
reasoning as `MAIL_MAILER=log` standing in for a real transactional-email provider elsewhere in
this codebase. Swapping to genuine live delivery later means adding a Reverb broadcast call inside
`notify()` and replacing `wire:poll` with `wire:poll` off, not a redesign.

**A real spec-mandated resilience gap found and fixed via actual testing**: spec 3.2 explicitly
requires "a failure in any one channel... must never block the underlying business action or the
other channels; failures are logged and retried/skipped, not thrown as errors." The first version
of `notify()` didn't do this — discovered when a `Mail::send()` failure (a local file-permission
mismatch between the CLI user and the Apache/`www-data`-owned log file, itself an artifact of
testing via `tinker` rather than through the app) threw uncaught mid-loop during
`RenewalReminderEngine::sweepOne()`, silently corrupting that renewable's `fired_tier_ids` state
(the notification had already been sent, but the "don't fire this tier again" bookkeeping never
saved). Fixed by wrapping the mail and push calls in `notify()` in their own try/catch, logging a
warning and continuing rather than propagating — exactly the spec's own requirement, just not
implemented until a real failure exposed the gap.

**A layout bug found and fixed via testing, not inspection**: the notification panel's first
version anchored itself with `right:0` relative to the bell button, which put the 340px-wide panel
mostly off the left edge of the viewport, since the bell sits near the left of this topbar layout
at the widths this session's browser tooling renders at — visually confirmed via screenshot before
being fixed to `left:0` with a `max-width: calc(100vw - 32px)` clamp.

**Verified end-to-end this session**: submitted a real leave request as a self-service employee
with a real supervisor and confirmed both the in-app notification (bell badge, panel entry, correct
deep link) and the templated email arrived for the supervisor; mark-as-read, mark-all-as-read, and
the panel's real (not decorative) unread badge all confirmed working via the browser, logged in as
the recipient. All test notifications cleared afterward.

**Not yet built:** the per-category email/push opt-out preferences spec calls "minimal for v1" —
still true here, every notification currently goes to every channel unconditionally; a
`NotificationMail` per event type (one generic template serves every module for now, matching that
same "minimal for v1" framing); the deep-link RBAC-scope degradation spec asks for ("a stale
notification for a record the user can no longer access degrades to a plain message rather than a
broken link" — currently just a plain link, no scope re-check at click time).

### 4.14 Renewal & Compliance Reminder Engine (spec Section 3.2) — the sixth Phase 0 platform service

Built ahead of all three of its named consumers (Vehicle Renewals E3, Asset warranties E2, Company
Registration Documents E5 — all Phase 4, none exist in this prototype), per the spec's own
reasoning for generalizing it early. `App\Services\RenewalReminderEngine` exposes `register()`/
`renew()`/`retire()`/`sweep()` against a polymorphic `renewables` table (any future model attaches
via `renewable_type`/`renewable_id`), configured per `renewal_types` row with admin-configurable
`renewal_reminder_tiers` (how many reminders, how many days before expiry each) and
`renewal_notify_targets` (by role and/or named user) — exactly the three configuration pieces spec
3.2 names. `sweep()` is a new first-class scheduled job
(`php artisan renewals:sweep`, registered in `routes/console.php` via `Schedule::command(...)->daily()`)
— this is also, incidentally, this prototype's first entry in spec 3.2's separately-named
"scheduled-job registry," which had nothing in it before this session either.

**Persistent expiry alert, not a one-shot reminder** — spec's explicit departure from "fire once
and go quiet": once a renewable's expiry date passes, `sweep()` keeps firing a daily alert
(`last_expired_alert_at` staleness check) until the item is renewed (which fully resets the cycle —
`fired_tier_ids` cleared, status back to `active`) or retired (stops permanently). Verified all
three states via `tinker` against a demo renewable (no real consumer exists yet to click through in
a browser, same honest limitation as the Accrual & Carry-Over Engine): confirmed both configured
tiers fire exactly once each and get recorded so they never re-fire; confirmed the expired-alert
fires once, doesn't repeat same-day, and fires again the next day if still unrenewed; confirmed
`renew()` fully resets tier/alert state. This is also what surfaced 4.13's resilience gap above —
found by actually running the sweep against real data, not by reading the code.

**Not yet built:** no admin UI for managing renewal types/tiers/notify-targets — there's no real
business screen to attach one to yet (the same reasoning that kept the Accrual & Carry-Over
Engine's confirmed policy unbuilt-in-code this session); a "who has and hasn't renewed" browsable
list per type, which naturally belongs alongside each future consumer module rather than as a
generic cross-type view.

### 4.15 E-Signature & Digital Consent Service (spec Section A8) — the seventh Phase 0 platform service

Built ahead of all of its named consumers (Policy Documents E4, offer letters D1, review sign-off
D2, expense-claim attestations E1 — none exist in this prototype), per spec's identical
build-it-once reasoning. `App\Services\SignatureService::sign()` records a `SignatureEvent`
(polymorphic `signable_type`/`signable_id`, signer, purpose, method, IP/user-agent, timestamp) for
either supported method (`click_to_sign` or `drawn`, the latter storing its canvas image via Media
Library — spec 3.5's pattern, same as Employee's avatar); `hasValidSignature()` and
`signaturesFor()` are the read side a future module's own acknowledgement-gating UI would call.

**Tamper-evidence, the spec's central requirement, verified directly**: the service never stores
the signed content itself, only `hash('sha256', $content)` — the caller passes in the exact
content presented to the signer (e.g. a policy body plus its version number). Confirmed via
`tinker`: signed a document's content, confirmed `hasValidSignature()` returns true for that exact
content and false the instant the content string changes (simulating a later document revision) —
"prior signatures remain valid evidence only for that prior version," proven, not just asserted in
a comment.

**A new Admin/HR Admin screen, `/admin/signatures`** (spec: "an Admin/HR Admin-facing screen to
look up who has and has not signed a given document/version... export the full evidence trail")
lists every `SignatureEvent` across every future signable type, filterable by purpose/signer, with
a per-row expandable evidence drill-down (content hash, IP, user-agent, and the drawn-signature
image where applicable) — the same inert-page-plus-child-component and expand/collapse pattern as
4.10's audit log browser. The "who has *not* signed a specific document" cross-reference spec also
asks for needs that document's own expected-signer list, which is necessarily per-module — it
lands here once a real consumer exists to define that audience.

**E-signature method sufficiency — confirmed by SI, spec updated to v8.2**: click-to-sign/typed-
consent is legally sufficient for internal HR acknowledgements (policy documents, review sign-
offs, and similar day-to-day consent capture) — no licensed e-signature provider is required for
that class of document. Still open, jurisdiction-dependent (Nigeria/UK): whether the
highest-formality document classes specifically — notably formal offer/employment contracts —
warrant a licensed provider (DocuSign, Adobe Sign, or similar); the service is deliberately
designed so one could be substituted or layered in for just those document classes later without
changing how other modules call it.

**Not yet built:** the click-to-sign (checkbox + re-auth/2FA step) and drawn-signature (canvas pad)
*capture* UI components themselves — genuinely nothing to attach them to yet without inventing a
document to sign, so building that UI now would be pure scaffolding with no real caller.

### 4.16 REST API framework (spec Section 3.2) — the eighth Phase 0 platform service

Spec: "a consistent request/response contract across all modules: typed, declarative parameter
validation; a resource/collection endpoint convention (`GET` list w/ pagination/sort/filter,
`GET/{id}`, `POST` create, `PUT/{id}` update, `DELETE` bulk-by-id-array); a `model=default|detailed`
convention for lean-vs-joined response shapes; consistent error envelopes (400 validation, 403
authorization, 404 not found)." Nothing API-shaped existed before this session — every existing
screen is a Livewire component talking straight to Eloquent, with no HTTP API surface at all.

**`laravel/sanctum`** installed for token auth (`POST /api/login` issues a personal-access token;
every other endpoint requires `auth:sanctum`) — deliberately separate from the web app's session +
2FA login, since a future mobile client (spec F6) authenticates differently than a browser session.
2FA is not yet enforced on token issuance, a known v1 gap (see below). `App\Support\ApiEnvelope` +
`App\Http\Controllers\Api\ApiController` are the one place every endpoint's response shape comes
from — `bootstrap/app.php`'s exception handling was extended to render the same 400/403/404
envelope automatically for `ValidationException`, `AuthorizationException`,
`ModelNotFoundException`, and (this is the one that actually matters day to day) any generic
`HttpExceptionInterface` — which is what `abort()`/`abort_unless()`/`abort_if()` actually throw,
the idiom every controller here uses for 403/404-style guards.

**Leave Management (C1) is the representative slice this framework is built and proven against** —
`Api\LeaveRequestController` implements the full resource convention (index with pagination/sort/
filter/`model=detailed`, show, store, update, bulk-`destroy`-by-id-array) exactly as spec
describes it, and every other module's future API controller should follow this same shape rather
than inventing its own. Authorization reuses `PermissionService::scopeFor()` — giving that method
its first real caller; it existed since the RBAC engine (4.6) but nothing had ever actually called
it — rather than a parallel API-only permission model, so a role's data-group scope
(all/self_subordinates/self/none) means the same thing whether reached through the web app or the
API.

**A real bug found and fixed via actual `curl` testing, not by reading the exception-handling
code**: the first version's explicit `AuthorizationException` handler never fired for the
controller's own `abort_unless(..., 403, ...)` calls, because `abort()` throws a plain Symfony
`HttpException`, not Laravel's `AuthorizationException` — every 403 from an actual controller
method was falling through to Laravel's default full-stack-trace JSON error page instead of the
custom envelope. Fixed by adding a generic `HttpExceptionInterface`-based handler keyed off status
code, which is what actually covers `abort()`/`abort_unless()`/`abort_if()` — the pattern every
controller in this codebase already uses for exactly this kind of guard.

**Verified end-to-end this session** via real `curl` requests (not just code review): logged in as
a real demo user (a line manager with real subordinates) and confirmed `GET /api/leave-requests`
correctly scoped results to her own plus her four direct reports' records, excluding two other
employees' unrelated requests present in the same table; confirmed `model=detailed` adds the
richer employee/leave-type/approval-timestamp fields the default shape omits; confirmed
`status=`/`sort=`/`per_page=` filtering and pagination metadata; confirmed the full create → update
→ bulk-cancel lifecycle; confirmed 400 (validation), 401 (no token — Laravel's own default
envelope, since spec's 400/403/404 list doesn't name 401), 403 (after the fix above), and 404 all
render distinctly and correctly. All test API tokens, and the one test leave request created
through the API, removed afterward.

**Not yet built:** every module besides Leave Management has no API controller yet (this is the
proven pattern, not full coverage — spec's own "designed from the outset for full per-module
coverage... rather than retrofitted later" describes the intent this framework now satisfies, not
a claim that every endpoint exists); 2FA is not enforced on token issuance; no per-token ability/
scope restriction (Sanctum supports this; unused so far); no rate limiting on `/api/login` beyond
Laravel's framework default.

## 5. Assumptions still open (for review before real Phase 0 starts)

1. ~~Livewire major version~~ and ~~Tailwind vs. `theme.css`~~ — **both decided**, see 4.1.
2. ~~The three-tier RBAC engine~~ and ~~the generic workflow-state-machine engine~~ — **both
   built**, see 4.6 and 4.7 respectively. Approvals is driven entirely by the
   `(workflow, state, role, action) → state` matrix now, not a hard-coded status-field check.
3. ~~2FA is simulated~~ — **built this session**, see 4.8. Real RFC 6238 TOTP with QR enrollment,
   real emailed OTP, real single-use backup codes, and real hashed-token trusted devices now exist
   end to end; the project-wide on/off switch stays OFF for this dev phase, unchanged. Per-role
   policy refinement (spec A1's "Admin policy control") — **also built**, see 4.8b.
4. **Demo data is fictional** (Adaeze Okafor, Tunde Bakare, etc., matching the artifact's own
   sample data) so the screens render with realistic content rather than empty states.
5. **Object storage** for profile-photo and company-logo uploads — **built this session**, see
   4.9, via `spatie/laravel-medialibrary` on Laravel's `Storage` abstraction (S3-ready, currently
   pointed at local disk). Still open: leave/claim attachments and the Company Registration/Policy
   Documents module don't have upload flows in this prototype yet to migrate, and **encryption-at-
   rest and audit logging for attachments remain unimplemented** — Section 3.2/6 flags both as
   Phase 0 foundational decisions; don't read their absence here as "not needed."
6. ~~MySQL/MariaDB swap~~ — **done**, see 4.1.3.
7. ~~Leave carry-over/proration policy assumptions~~ — **confirmed by SI**, spec updated to v8.1
   as Admin-configurable defaults, not fixed rules: the 1-year wait exemption is governed entirely
   by each Leave Type's own `minimumTenureMonths` field (Admin-editable per type at any time),
   defaulting out of the box to Annual Leave only (`= 12`; every other type ships at the field's
   own `= 0` default); the carryover cap is an Admin-editable org setting, defaulting to 10 days
   (previously uncapped in the spec's own placeholder). The Accrual & Carry-Over Engine itself is
   still Phase 2, not Phase 0, scope — unbuilt in this prototype — but when it's built, both values
   should be read from configuration (the per-type field and the org setting) rather than
   hard-coded, seeded at these confirmed defaults.
8. **Password policy was entirely unbuilt** — **built this session**, see 4.12: configurable
   length/character-class rules, "enforce on login" via a version-mismatch forced-change flow, and
   a real self-service reset (emailed 6-digit code, same pattern as 2FA's email OTP). No minimum-
   strength/entropy tier yet — only length/character rules.
9. **The In-App Notification Center (spec A7) was entirely unbuilt** — **built this session**, see
   4.13: real in-app records, real email, and real VAPID-signed browser push, one call
   (`NotificationService::notify()`) reaching all three. Real-time in-app delivery is `wire:poll`,
   not a WebSocket push over Reverb (no Reverb server runs on this dev box) — see 4.13 for why
   that's the honest, working stand-in rather than a gap. Per-category opt-out preferences don't
   exist yet (spec's own "minimal for v1").
10. **The Renewal & Compliance Reminder Engine (spec 3.2) was entirely unbuilt** — **built this
    session**, see 4.14, ahead of all three of its named consumers (all Phase 4, none exist yet).
    Verified via `tinker` against demo data, not a browser click-through, since there's no real
    consumer screen yet — same honest limitation as the still-unbuilt Accrual & Carry-Over Engine.
11. **The E-Signature & Digital Consent Service (spec A8) was entirely unbuilt** — **built this
    session**, see 4.15, ahead of all of its named consumers (Phase 3/4, none exist yet).
    Tamper-evidence (a content hash, not the content itself) verified directly via `tinker`. The
    actual click-to-sign/drawn-signature capture UI isn't built — nothing to attach it to yet.
12. **No REST API existed at all** (every screen was Livewire-to-Eloquent directly) — **the
    framework is now built**, see 4.16: Sanctum token auth, a consistent response envelope and
    error-handling convention, applied in full to Leave Management as the proven representative
    slice. Every other module still has zero API coverage — this is a proven pattern, not full
    coverage, matching spec's own "designed for it from the outset" framing rather than a claim
    that every endpoint exists.
13. **No version control or CI existed at all** — **both now exist**, see 4.11: a local git repo
    (no remote yet) and a GitHub Actions pipeline (test + style-check + build) that activates the
    moment a remote is added. The "CD" half (an actual deploy target) remains entirely open — there
    is no staging/production environment yet, only this dev box.

## 6. Running it

**Primary, live on Apache — DONE this session.** `/var/www/html/hris` is now the canonical,
Apache-served copy, following the exact convention already used for `pim` (port 8081) and
`orangehrm-dev` (port 8080) on this box: owned by `michael` (not `www-data` — only `storage/` and
`bootstrap/cache/` are group-writable by `www-data`, via `chgrp`+`chmod 775`, so normal editing
still works without `sudo`), vhost at `/etc/apache2/sites-available/hris-dev.conf` (installed from
`app/deploy/apache-hris.conf`), enabled via `a2ensite` and an `apache2 reload`.

**Open http://localhost:8083** — demo logins: `adaeze@systemsintelligenz.com` (ESS + Line
Manager) and `hr@systemsintelligenz.com` (HR & Admin), both password `password`.

`/home/michael/Downloads/HRIS/app` (this project folder) is kept in sync as a courtesy copy via
`rsync` after each change — edit either copy, but treat `/var/www/html/hris` as the one actually
running, and re-run the same `rsync` (excluding `node_modules`, `storage/logs`, the framework
runtime caches, and `public/storage`) if the two drift.

Fallback, no Apache/root needed (was primary before this session's sudo access):
```bash
cd /home/michael/Downloads/HRIS/app   # or /var/www/html/hris
php artisan serve --host=127.0.0.1 --port=8082
```

**Version control and CI — see 4.11.** `/var/www/html/hris` (the canonical copy only, not the
courtesy copy) is a git repository (`git init`, branch `main`), with the existing Laravel
`.gitignore` already excluding `vendor/`, `.env`, `node_modules/`, and framework caches, plus a
GitHub Actions pipeline (`.github/workflows/ci.yml`) that activates the moment this repo gets a
GitHub remote — still open: no remote exists yet, and no deploy target for the "CD" half.

## 7. Next steps

- Both open implementation-level deltas in Section 4.1 are now decided (Livewire 4, Tailwind +
  `theme.css` side by side) — record that decision for whoever owns the real Phase 0 build so it
  isn't re-litigated from scratch.
- As new modules get built beyond this session's six screens, style them in Tailwind utility
  classes directly (the Settings page is the reference example), reserving `theme.css`'s named
  component classes for the parts of the UI that must stay pixel-identical to the artifact.
- ~~Build the three-tier RBAC/permission-matrix engine~~ — **done**, see 4.6.
- ~~Build the generic workflow-state-machine engine~~ — **done**, see 4.7. Both Phase 0
  foundational engines the spec calls out now exist; the two platform services everything else in
  Section 4 (Domains B–F) is written to depend on.
- ~~Get SI to confirm the spec-C1-vs-artifact discrepancy flagged in 4.7~~ — **done**: two-stage
  sequential (Line Manager, then HR) confirmed as permanent; spec document updated to v7 to match.
- Extend both engines to the next module rather than rebuilding either: Timesheets and Attendance
  (spec C2/C3) are the natural next candidates for the workflow engine — same actor-tag model
  (`owner`, `supervisor`, RBAC roles), same "inert page + child component" Livewire pattern.
- ~~Consolidate the two parallel "who can act on this specific record" resolvers noted in
  4.6/4.7~~ — partially resolved: `PermissionService::scopeFor()` now has a real caller
  (`Api\LeaveRequestController`, see 4.16), so it's no longer dead code, but the web app's
  Approvals screen still uses the workflow engine's own `actorTags()` independently — the two
  haven't actually been merged onto one mechanism, just both are live now instead of one being
  unused.
- Add workflow-transition config-change auditing specifically (spec A2/3.2) — `Role` rows are
  audited (4.10) but the permission-matrix's individual cells (`RoleScreenPermission`/
  `RoleDataGroupPermission`) and `WorkflowTransition` rows aren't wrapped in `Auditable` yet, so a
  matrix or matrix-transition edit doesn't show its own before/after detail in the audit log.
- ~~Phase 0 is complete~~ — see the callout after the revision-history block at the top. **Phase 1
  (Domain B — Organization & Employee Master Data) is now underway** — see Section 8 for the first
  slice (Employee ID Auto-Generation + a real Employee list/create/detail screen).
- ~~Open decision: e-signature-method-sufficiency legal confirmation~~ — **confirmed by SI**, spec
  updated to v8.2 (see 4.15): click-to-sign is sufficient for internal HR acknowledgements; a
  licensed provider for the highest-formality classes (offer/employment contracts) remains a
  separate, still-open question.
- ~~Open decision: real third-party credentials (S3, transactional email)~~ — **confirmed by SI**:
  intentionally staying on local dev stand-ins (local disk, `MAIL_MAILER=log`) until live
  deployment, at which point real credentials will be provided. Not a gap to close now.
- ~~Open decision: CI remote~~ — **resolved**: pushed to `https://github.com/made-mimo/hris`
  (see 4.11's update). No deploy target exists yet — the "CD" half of 4.11 is still open, and
  there's still no staging/production environment to deploy to.
- Remaining, not treated as blocking: the Accrual & Carry-Over Engine itself is Phase 2 scope
  (policy already confirmed at v8.1) — correctly unbuilt, not a Phase 0 gap.
- Everything else in the spec's Section 7 phase order and dependency gates applies unchanged.

## 8. Phase 1 — Organization & Employee Master Data (spec Domain B) — first slice

Spec 7.2 calls Domain B "the largest single phase by data-model surface area, since nearly every
later module either stores data against an Employee or references B1's master lists." This
session's first slice is deliberately narrow and dependency-first, not an attempt at all of
B1/B2/B3 in one pass — see "Not started yet" below for the (large) remainder.

**Employee ID Auto-Generation (spec B2)** — the one piece spec explicitly says must exist "before
Phase 1 creates the first employee record," so it came first. `App\Services\EmployeeIdGenerator`
parses an Admin-configurable format template (`{YY}`/`{MM}`/`{SEQ:n}` tokens over literal text,
seeded at SI's own `SIL{YY}{MM}{SEQ:3}`) against a `employee_id_sequences` table locked per
increment (`lockForUpdate()` inside a transaction, so two simultaneous hires can't collide) —
configurable scope (global/per-year/per-month) even though the shipped default is the single
continuous, non-resetting count spec confirms as SI's actual policy. A new "Employee ID format"
card on Settings edits the template and scope with a live preview
(`EmployeeIdGenerator::preview()`), and forward-only by construction: nothing here ever touches an
existing employee's already-issued ID. Admin/HR Admin manual override (spec's explicit allowance,
e.g. for a legacy employee predating the system) is on the new employee detail page, validated
through the same `isAvailable()` uniqueness check a real auto-generated ID would use.

**This session's 13 seeded demo employees keep their original hand-authored IDs untouched** — the
new engine only governs IDs generated from this point forward; nothing retroactively renumbers or
validates the demo data's existing (illustrative, not spec-compliant) ID strings against the new
format.

**A new, real "Add Employee" flow** (`/employees`, `/employees/create`, `/employees/{id}`,
Admin/HR Admin/HR Officer only — matching the existing `employee_personal_details` data-group
grants already in RbacSeeder) gives the ID generator its first real caller and is itself spec B2's
"minimal-friction creation (only name required at creation) followed by a tabbed profile editor" —
this session builds the creation minimum (name + hire date required, job title/department/
location/supervisor optional) and exactly one "tab" of the eventual editor (core job/identity
basics); every other tab spec B2 lists is follow-up work (see below). `job_title`/`department` on
`employees` were NOT NULL from this prototype's very first iteration (pre-dating this session),
which had made true minimal-friction creation impossible — both are nullable now.

**A third instance of the same Livewire full-page-morph bug, found and fixed via testing**: the
Employee List page's search box (`wire:model.live` directly on the full-page component) hit the
identical defect already documented at 4.3 and fixed twice elsewhere this session (the login form,
4.16's testing) — split into an inert `⚡employees.blade.php` page plus a new `employee-list` child
component, the same fix pattern each time. Three strikes on the same defect class this session is
worth naming plainly: **any new full-page component with a live-updating input needs the child-
component split from the moment it's written, not discovered by testing after the fact** — the
mandatory rule already stated in 4.3 held every time it was actually followed, and broke every
time a new screen skipped it.

**Verified end-to-end this session**: created a real employee through the browser with only a
name and hire date, confirmed a real Employee ID was generated matching the configured format and
today's date/sequence position, confirmed searching the employee list by name and by partial
employee ID both work post-fix, confirmed the ID-override uniqueness check correctly rejects an
already-used ID and accepts a free one. Test employee and its consumed sequence value removed
afterward (the sequence counter itself was **not** rewound — per spec's own "permanent, never
reused" policy, a skipped/retired sequence value from a corrected test record is exactly the kind
of harmless gap a real auto-increment scheme is expected to tolerate, not a bug to paper over).

**Not started yet** (the large remainder of Domain B): B1's master-data backbone (Job Titles, Job
Categories, Employment Statuses, qualification master lists, Countries/Provinces, Locations,
Sub-units as a real hierarchical tree replacing the plain `department` string, Pay Grades with
currency bands, Work Shifts, Organization profile fields, module enable/disable toggles) — none of
these tables exist yet, so `job_title`/`department`/`location` remain plain strings, not FKs;
B2's other profile tabs (personal details incl. encrypted government IDs, contact details,
emergency contacts/dependents, immigration records, compensation incl. encrypted salary lines,
qualifications, the many-to-many typed multi-supervisor reporting graph — still a single
`supervisor_id` self-FK today, termination records, attachments beyond the avatar, the employee
activity log distinct from the general audit log); the employee list's job-title/sub-unit/
supervisor/location filters and current/past/both toggle (meaningless without real master-data
FKs and a termination concept to filter on); the interactive org chart; CSV bulk import; the
self-service "My Info" view; the ad-hoc/predefined report builder; all of B3 (onboarding/
offboarding templates and tasks, development plans, training records, the offboarding hard-block
on assets/vehicles — which itself waits on Asset/Vehicle Management, Phase 4). A2/A6 (SSO, GDPR
purge) are named in spec 7.2 as buildable in parallel with Phase 1 but haven't been started.

## 9. Phase 1 — B1 master-data backbone (spec Domain B1)

Second Phase 1 slice: the "Not started yet" B1 backbone flagged at the end of Section 8 —
`job_title`/`department`/`location` converted from plain strings to real master-data foreign keys,
plus an Admin/HR Admin-only admin screen to manage the master lists themselves.

**Schema**: `job_titles`, `sub_units` (adjacency-list self-FK — plain `parent_id`, not a
nested-set/closure-table; sufficient at this prototype's scale, spec B1 doesn't call for
closure-table query performance), `locations`, `work_shifts`, `pay_grades` +
`pay_grade_bands` (one grade to many currency-banded min/max rows), and one generic
`master_list_items` table with a `type` discriminator column covering the nine simple flat named
lookup lists spec B1 lists (Job Categories, Employment Statuses, Education Levels, Skills,
Languages, License Types, Membership Bodies, Nationalities, Countries) — one table instead of nine
near-identical ones. `employees.job_title`/`department`/`location` (plain strings since this
prototype's very first iteration, predating Phase 1) are now `job_title_id`/`sub_unit_id`/
`location_id`, nullable FKs with `nullOnDelete()`. Organization profile fields (tax ID,
registration number, address, contact email/phone) joined the existing singleton `Setting` row
rather than a separate single-row table, alongside the existing `company_name` from the Branding
card (spec A5).

**Data-preserving migration, not a destructive one**: converting the three string columns to FKs
risked losing this session's seeded demo employees' data, so
`2026_09_28_000007_migrate_employee_strings_to_master_data_fks` uses raw `DB::table()` queries
(not Eloquent, to avoid triggering model events/traits mid-schema-migration) to create a master row
for every distinct existing string value, remap every employee row to it, and only then drop the
old columns. A full `mysqldump` backup was taken immediately before running it as an extra safety
margin beyond the migration's own reversibility. Verified row-by-row via tinker afterward: all 13
seeded employees kept their exact same effective job title/department/location, just via FK now —
zero data loss, matching this project's "keep demo data" rule (4.16, Section 8) throughout.

**A fourth occurrence of the recurring mass-assignment silent-drop bug** (previously hit and
documented at 4.12 for `User::$fillable`/`password_policy_version`): the new
`tax_id`/`registration_number`/`address`/`contact_email`/`contact_phone` columns were added to the
`settings` table and to the new "Organization profile" Settings card's `saveOrgProfile()`, but not
to `Setting::$fillable` — caught by testing (`update()` returned success and flashed "Organization
profile updated," but the DB row stayed untouched), not by any validation or exception, exactly
like the 4.12 incident. Fixed by adding the five columns to `$fillable`; re-tested via the browser
and confirmed persisting. Worth naming as a pattern now that it's recurred: **every new
Setting/model column needs its own `$fillable` entry as a matching, deliberate step when the
migration is written — it is not something a passing test suite or a thrown error will catch**,
since Eloquent mass-assignment silently drops ungranted attributes rather than failing loudly.

**Admin UI**: one new RBAC screen, `admin.master-data` ("Organization & Master Data", Admin/HR
Admin only — matching B1's framing as administrative backbone, not a general HR-officer surface),
at `/admin/master-data`. Follows the "inert page + child Livewire component" rule (4.3): the page
itself (`⚡admin-master-data.blade.php`) is fully inert, wrapping a single
`<livewire:master-data-manager />`. That component owns only a `wire:click`-driven `activeTab`
tab-switcher — safe to put interactivity directly on it since it is not itself a full-page SFC
registered via `Route::livewire()` (only the page wrapping it needs to stay inert) — and lazily
mounts one of six sibling child components per tab: `job-titles-manager`, `sub-units-manager`,
`locations-manager`, `work-shifts-manager`, `pay-grades-manager`, and `master-list-manager` (the
generic one, with its own `type` dropdown switching between the nine discriminated lists). Built in
the Tailwind-utility style the Settings page already established as "the pattern to follow for
every *new* screen going forward" (its own comment, quoted verbatim), not the theme.css classes the
six artifact-matched screens use.

**Usage-aware delete protection**, applied wherever a master row can actually be referenced today:
Job Titles and Locations block deletion when `employees()->count() > 0`; Sub-units block deletion
when either `children()->count() > 0` (would orphan a subtree) or `employees()->count() > 0`, and
sub-unit re-parenting is guarded against creating a cycle (a node can't be moved under itself or
one of its own current descendants — checked by walking the candidate parent's descendant-id set
before saving). Work Shifts, Pay Grades (+ bands), and the nine `master_list_items` types have no
delete protection because nothing yet references them from another table (no shift/compensation
assignment exists on Employee yet — that's Phase 1/2 follow-up work) — deleting one is always safe
today, so no guard was added for a constraint that doesn't exist yet.

**Employee CRUD/detail (Section 8's slice) updated to match**: `employee-create-form` and
`employee-detail-form` now use `<select>` dropdowns bound to `job_title_id`/`sub_unit_id`/
`location_id`, sourced from the new master tables, replacing the free-text inputs Section 8 shipped
before the master-data tables existed. `Employee::jobTitleName()`/`departmentName()`/
`locationName()` helper methods (deliberately not magic `getXAttribute()` accessors, to avoid
collision with the same-named `jobTitle()`/`subUnit()`/`location()` BelongsTo relations — this
codebase's established preference for explicit named methods over magic properties, matching
`fullName()`) back every remaining display call site: the employee list, home page greeting,
profile page, approvals board (`dept` fact for both leave and claims), the sidebar's role/title
line, and the leave-apply-form's reliever-colleague lookup (now scoped by `sub_unit_id` instead of
the old `department` string) and dropdown label.

**Verified end-to-end this session**: a full `migrate:fresh --seed` cycle (all 42 migrations +
three seeders) runs clean against the new schema; `vendor/bin/pint` and `php artisan test` both
pass; tinker confirms 13 employees / 9 job titles / 6 sub-units / 1 location survive the seed with
correct FK linkage. Browser-tested logged in as Admin: employee list shows correct job
title/department via the new relations, live search still works post-fix, created a real employee
through the dropdown-based create form and confirmed the detail page round-trips the same
selections back into pre-filled dropdowns; exercised all six master-data tabs (added/removed a job
title, a nested sub-unit, a location, a work shift, a pay grade with a currency band, and a
Master-List item; confirmed the sub-unit cycle-guard and children/employee delete-protection
against real data via tinker, since headless automation can't reliably drive a native
`wire:confirm()` dialog); confirmed the Organization Profile card saves and persists (after the
`$fillable` fix above) via the Settings page; confirmed via `PermissionService::canViewScreen()`
directly that ESS-role Emeka is correctly denied `admin.master-data` while Admin is correctly
granted it. All test data created during this session's manual verification was deleted afterward.

**Not started yet** (at the end of this slice, since superseded by Section 10 below): Countries/
Provinces as a real hierarchical structure; module enable/disable toggles; Email/SMTP configuration
and notification subscription lists; B2's other profile tabs; the interactive org chart; CSV bulk
import. All of these were closed out in the Phase 1 completion push — see Section 10.

## 10. Phase 1 completion — full Domain B + A3/A5/A6 parallel-track items

User instruction for this session: "Continue with Phase 1 — test and run till completion of Phase
1." Spec 7.2 defines Phase 1 as "Domain B in full" (B1/B2/B3) plus three items explicitly callable
"in parallel within this phase": SSO (A3), branding/health checks (A5), and the GDPR/purge
framework (A6). This section closes every remaining item in that list. Per spec's own words, this
is "the largest single phase by data-model surface area" and its "duration estimate [should be
treated as] a floor, not a target" — this session pushed through it in one continuous sitting,
which is a compressed timeline by the spec's own risk register, not a claim that the result carries
the same scrutiny multi-week phase-gate review would have applied. Every feature below was
exercised through the actual UI (not just unit-tested in isolation) and its data verified via
tinker before moving to the next slice; three real, non-cosmetic bugs were caught this way and are
called out below rather than folded silently into "verified."

### B2 — the multi-supervisor reporting graph (Phase 2 gate item)

Spec: "an employee may have multiple supervisors and multiple subordinates simultaneously, each
relationship additionally tagged with a reporting method (direct vs. dotted-line/matrix) — a
genuine many-to-many typed graph, not a single manager field." This is the one B2 item called out
by name in spec 7.2's Phase-2 dependency gate ("reporting-line graph...must be stable"), so it came
first. New `employee_supervisors` pivot (employee_id, supervisor_id, reporting_method) is the real
graph; `employees.supervisor_id` (the pre-existing single self-FK) stays in place as a synced
"primary direct supervisor" column, re-derived by `Employee::syncPrimarySupervisor()` on every
graph change — every Phase 0/Section 8 consumer that depends on a single supervisor for one purpose
(WorkflowEngine's `supervisor` actor tag, the leave-request auto-router, PermissionService's
`self_subordinates` scope) keeps working unchanged. A new Reporting tab lets each employee manage
who they report to (add/remove, tag direct vs. dotted-line); subordinates are shown read-only
there, edited from the subordinate's own tab instead of duplicating the edit surface. Re-parenting
is guarded against cycles by walking the candidate supervisor's ancestor chain before saving —
verified live in the browser: attempting to make Emeka report to Chuka (who already reports to
Emeka) was correctly rejected with "That would create a reporting-line cycle," and the legitimate
edge in the other direction correctly re-synced `supervisor_id` on both sides (confirmed via
tinker: `allSubordinates` and the classic `subordinates()` hasMany agreed after the change).

### B2 — the rest of the Employee Master Record

Every remaining spec B2 data area now exists and is editable through an 11-tab profile editor
(`employee-profile-tabs.blade.php`, replacing the single Job-Details-only tab Section 8 shipped):
Personal (preferred name, DOB, gender, marital status, nationality, government ID — encrypted,
driving license), Contact (address, phones, personal/work email), Family (emergency contacts +
dependents, both variable-length lists), Immigration records, Compensation (one-or-more salary
lines tied to a Pay Grade, amount and bank account number encrypted), Qualifications (education,
skills, languages with per-skill reading/writing/speaking levels, licenses, professional
memberships, work experience — six sub-sections on one tab, matching spec's own "Qualifications"
tab grouping literally), Career (onboarding/offboarding tasks, development plans, training records
— see B3 below), Reporting (above), Termination (derived current-status badge + history + the GDPR
purge action, see A6 below), Attachments & Custom Fields (per-tab file uploads via a second,
non-single-file Spatie media collection; up to 10 Admin-configurable custom fields, defined under
Org & Master Data, values edited here), and Activity (spec's own suggested simplification —
"unified with the general audit log rather than kept separate" — so this tab is just a filtered
view of the same `audit_logs` rows the existing `Auditable` trait already writes). Job Details
itself gained Job Category, Employment Status, and contract start/end dates. Self-service "My Info"
on the Profile page reuses the Personal and Contact tab components directly (they take a plain
`Employee` with no admin-only gating) rather than a separate self-service code path.

Employee ID's "permanent, non-reassignable" guarantee (Section 8) now also survives a GDPR purge —
see A6.

### B2 — list, search, org chart, CSV import, ad-hoc reporting

Employee list gained job-title/sub-unit(-with-subtree)/supervisor/location filters and a
current/past/both three-way toggle (backed by the termination-record-derived status, not a
separate flag) — verified in the browser: filtering by "AV Integration" correctly returned exactly
its 6 members, and toggling to "Past" after adding a test termination correctly isolated to just
that one employee. A multi-root org chart (`/org-chart`) renders the reporting-line tree (via
`supervisor_id` — the single-parent primary line, not the full dotted-line graph, since a tree
visualization needs exactly one parent per node) with expand/collapse and cycle-safe traversal.
CSV bulk import (with a downloadable sample template) resolves job title/sub-unit/location by name
via `firstOrCreate`, same pattern the original string-to-FK migration used — verified end-to-end
via tinker (simulating the exact upload-parse-create path, since the browser tool can't drive a
native file picker): a two-row CSV correctly created both employees, auto-generated their Employee
IDs, and created new JobTitle/SubUnit master rows for values that didn't already exist. The ad-hoc
report builder offers a field picker, the same filter set as the list, a live preview, and CSV
export; PDF export and scheduled recurring email delivery (spec's full description) are explicitly
NOT built — CSV covers "get the data out," and the scheduling half needs a recipient-list concept
this prototype doesn't have anywhere else, so building it as a one-off for this feature alone would
be scope creep past what the rest of the app establishes.

### B3 — Onboarding/Offboarding & Career Development, in full

Admin-managed Templates (name + type + ordered items, each with a day offset that may be negative)
at `/admin/onboarding-templates`. Applying a template to an employee (from their Career tab)
computes each task's due date from the offset anchored to the employee's hire date (onboarding) or
their latest termination record's date (offboarding) — verified in the browser: applying a
template item with `offset_days = -2` to an employee hired 27 Sep 2022 correctly produced a task
due 25 Sep 2022. Ad hoc tasks, Development Plans, and Training records (with role-sensitive
edit/delete eligibility — owning employee, HR/Admin always, a supervisor only for their own
still-planned entries — computed by `TrainingRecord::canBeEditedBy()` and checked both server-side
and in the UI, matching spec's "surfaced to the UI without a second round-trip") round out the tab.
Per spec's own explicit call-out, the offboarding hard block on outstanding assets/vehicles is
**not** wired — Asset/Vehicle Management don't exist until Phase 4, so this is expected, not a gap.

### B1 — the remainder

Job Titles can now carry an optional attached job-specification document (a second Spatie media
collection, `job_spec`). Provinces got their own table (FK to the existing flat `master_list_items`
"country" type) — one level of real hierarchy, not a full nested-set country/state/city tree, which
spec's actual wording ("Countries/Provinces") doesn't ask for anyway. Email/SMTP configuration and
Email-notification subscription lists (free-text category → address, since new notification
categories get added across the app over time and a fixed enum would need a migration for each new
one) both joined the Admin surfaces (Settings and Org & Master Data respectively). System-level
Module enable/disable toggles are wired into `PermissionService::canViewScreen()`/`visibleScreens()`
so a disabled module's screens are unreachable by direct URL, not just hidden from nav — verified
in the browser: disabling "Leave Management" removed the nav link, made `/leave/apply` 404, and
re-enabling it restored both. While in the nav file for that fix, also fixed a **pre-existing gap
from the earlier B1 slice**: the Org & Master Data screen had been built with no sidebar link at
all, reachable only by typing the URL directly.

### A3 — SSO (config layer only)

Settings gained a full SSO configuration card (enable toggle, provider type OIDC/LDAP, client
ID/secret — encrypted, discovery URL/LDAP host, domain/base DN). The login page's "Sign in with
company SSO" button (already built pre-Phase-1, an artifact-matched stub) is deliberately left as a
JS-alert placeholder — there is no real identity provider to authenticate against in this local
prototype, and wiring fake protocol logic against nothing would be unverifiable, untestable code
masquerading as a real feature. This is the same judgment call already made and confirmed by SI for
S3/transactional email (Section 8): real third-party credentials stay local-only until live
deployment supplies them, at which point this config layer is what an engineer fills in to make the
button real.

**Superseded post-completion** (see "Google Workspace and Microsoft 365 SSO" in the post-completion
section near the end of this file): the generic OIDC/LDAP config card above was replaced with real
Google Workspace and Microsoft 365 sign-in via Laravel Socialite, on request. The button is no longer
a stub.

### A5 — System Health Check + branding

New Admin-only screen (`/admin/health-check`) checks database connectivity, storage disk
writability, the public storage symlink, queue configuration, app key presence, and cache
reachability — all passing against this environment when tested. Per spec's explicit requirement
("the underlying check endpoint should be able to be hidden once initial setup is complete to
reduce information disclosure"), a Settings toggle blocks the route with a 404 for **everyone,
Admin included** once hidden, not just hiding the nav link — verified in the browser: toggling it
on made `/admin/health-check` 404 even while still logged in as Admin, and toggling it back off
restored access. Branding itself was already built in Phase 0.

### A6 — GDPR purge

`Employee::gdprPurge()` anonymizes every PII-bearing field and deletes every PII-bearing child
record (emergency contacts, dependents, immigration records, compensation lines, qualifications)
while leaving the Employee ID, dates, and structural FKs (job title, sub-unit, location) untouched
— those aren't personal data and later reports/history still need them. `is_gdpr_purged`/
`gdpr_purged_at` are deliberately **not** in `$fillable`, set only via `forceFill()` inside that one
method, so no form can ever set them by accident. The action itself lives on the Termination tab,
Admin-only, gated behind typing the employee's exact Employee ID (a stronger bar than a plain
confirm dialog, given this is irreversible) — verified via both tinker (direct method call) and the
full browser UI flow (wrong ID correctly rejected with an inline error and no state change; correct
ID purged the record and showed the purge date afterward). Confirmed via
`EmployeeIdGenerator::isAvailable()` that a purged employee's ID stays permanently rejected for
reissue, matching spec's explicit requirement.

### Bugs caught by testing, not by review

Three real bugs surfaced only because each feature was exercised through the actual UI/tinker
before moving on, not just read back as "looks right":

1. **Encrypted fields leaking into the audit log.** `Auditable` diffs post-cast attribute values
   (by design, so a JSON column reads clean on both sides — see the trait's own comment), which
   means an `encrypted`-cast field decrypts before the diff is computed and written to
   `audit_logs.changes`. Every encrypted column added this session (`government_id_number`,
   `EmployeeCompensation.amount`/`bank_account_number`, `Setting.smtp_password`/
   `sso_client_secret`) needed an explicit `$auditExcept` entry — caught the first time by manually
   inspecting a real audit-log row after testing the Personal tab, then applied proactively to
   every encrypted column added afterward. Two already-leaked test rows from before the fix existed
   were found and deleted from `audit_logs` directly.
2. **The exact 4.12/Section-9 mass-assignment pattern, a fifth time.** Same root cause as before:
   new `Setting` columns need a matching `$fillable` entry as a deliberate step when the migration
   is written, or `update()` silently no-ops. Caught this time before it shipped, by adding SMTP's
   columns to `$fillable` in the same edit as the migration rather than after.
3. **`Employee::canBeEditedBy()`-style comparison written backwards on the first pass** (in
   `TrainingRecord::canBeEditedBy()`): the supervisor check compared `$user->employee->id` against
   `$this->employee->supervisor_id` when it needed to be the other way around (is the record's
   employee's supervisor the current user, not is the current user's own supervisor field equal to
   something). Caught by re-reading the method immediately after writing it, before any test ran
   against it — not a testing catch, but included here since the same "actor vs. subject" swap is
   an easy mistake to make again in D2/D3's similar eligibility checks (Phase 3).

### Not built (honest gaps, not silently dropped)

- Countries/Provinces is a flat country list + one level of provinces, not a full geographic
  hierarchy (region/state/city) — spec's own wording doesn't ask for more than this.
- Ad-hoc reporting: CSV export only. No PDF export, no scheduling, no recipient-list concept.
- Org chart: read-only expand/collapse tree, not drag-to-reassign. "Interactive" here means
  navigable, not editable from the chart itself — edits still happen on each employee's Reporting
  tab.
- The optional-profile-fields toggle hides whole tabs, not individual fields within a tab — spec's
  literal wording is per-field, but implementing that would mean gating dozens of individual field
  renders across eight components for comparatively little demonstrated value in a prototype.
- ~~SSO is configuration-only — no real OIDC/LDAP protocol implementation, per the A3 section
  above.~~ Superseded post-completion — see the SSO section near the end of this file.
- B3's offboarding hard block on assets/vehicles — expected to wait for Phase 4, per spec's own
  words, not a gap in this phase.
- Employee Custom Fields render together in one place on the Attachments tab rather than inline on
  each field's configured tab (personal/contact/job/qualifications) — the `tab` attribute is stored
  and manageable, but the display doesn't yet scatter fields across the four tabs it's tagged for.

### Verified this session (summary)

Every migration ran clean on a full `migrate:fresh --seed` cycle (52 migrations, three seeders) at
each checkpoint; `vendor/bin/pint` and `php artisan test` passed at every commit; every new screen
was exercised in the browser logged in as Admin, with real data entered, saved, and re-verified via
tinker against the raw database (not just the decrypted/cast model attribute) wherever encryption
or redaction was in play. Four commits, each pushed with CI green:
`2ac1e03`→`438056d`→`8937065`→`fa9bf97`→`c3077cd`.

**Phase 1 is now complete against spec 7.2's deliverable list.** The Phase 2 dependency gate
("Employee entity, org structure, reporting-line graph, and RBAC data-group scoping...must be
stable") is satisfied: the reporting-line graph is real and cycle-protected, the Employee entity
covers every spec B2 data area, org structure (B1) was already stable from the prior slice, and
RBAC data-group scoping is unchanged and still passes its existing self/self_subordinates logic
against the new graph (confirmed via tinker that `subordinates()` and the new `allSubordinates()`
pivot relation agree). Phase 2 (Leave/Time/Attendance) can begin.

## Phase 2 — Domain C: Leave, Time & Attendance

Spec Section C in full: C1 Leave Management (with an Accrual & Carry-Over Engine), C2 Time &
Project Tracking, C3 Attendance (Time Clock). Rebuilt/extended each sub-domain fully against spec,
same "test every feature through real UI/tinker interaction before moving on" discipline as
Phase 1.

### C1 — Leave Management

Leave requests moved from a single header row with a `days` count to a proper per-day model:
`leave_request_days` (one row per calendar day in the range, `is_working_day`/`day_value`/`status`)
and `leave_entitlement_consumptions` (a FIFO draw-down ledger tying each working day to the specific
entitlement batch it consumed). This is what makes half-days, public holidays, and multi-batch
balances (e.g. a carried-over balance expiring before a fresh grant) actually representable, not
just approximated by a decimal.

- **Calendar awareness.** `Holiday` (one-off or recurring-annual, full or half-day) and
  `WorkWeekDay` (per-weekday full/half/non-working) feed `LeaveCalendarService::resolve()`, which
  `LeaveDayGeneratorService` uses to turn a date range into real per-day working/non-working/value
  rows — a leave request spanning a weekend or a public holiday correctly excludes those days from
  the balance draw-down, not just from the day count shown to the user.
- **FIFO consumption with expiry tie-break.** `LeaveConsumptionService::consume()` locks entitlement
  rows (`lockForUpdate()`) and draws down the batch with the *earliest* `drawDownDeadline()`
  (`expires_at` if set, else `effective_end_date`) first, splitting a single day across batches if
  one runs out mid-day. `reverse()` deletes the consumption rows on reject/cancel, uncommitting the
  balance.
- **Accrual & Carry-Over Engine** (`LeaveAccrualEngine`, spec's own worked example used as the
  executable test oracle): `grantNewHireProrations()` prorates a new hire's first-year entitlement
  by remaining months (`max(0, 12 - eligibilityDate->month)`) — a 15-Mar-2025 hire with a 12-month
  minimum tenure and a 20-day annual type produces exactly 15.0 days for Apr–Dec, matching spec's
  example to the day. A zero-day proration still creates its entitlement row (not skipped) so a
  December-eligibility employee is correctly picked up by next year's Standard Grant check.
  `runYearEndCarryover()` is idempotent (checks for an existing next-year entitlement before
  creating one) and also grants the plain Standard Grant to types that don't carry over. All three
  console commands (`leave:grant-new-hire-prorations`, `leave:run-year-end-carryover`,
  `leave:mark-past-days-taken`) are scheduled daily.
- **Deletion of a Leave Type in use doesn't block or cascade-delete** — any open request against a
  deleted type moves to a new `restricted` workflow state ("cancel only"), verified via the
  WorkflowEngine correctly resolving this new state with zero engine changes.
- **UI**: Apply (`leave-apply-form`, pixel-preserved from its original artifact design, now backed
  by real day generation and `LeaveRequestService::apply()`), Assign (HR/Admin path, scoped via
  `PermissionService::scopeFor(..., 'leave_requests')`, can bypass balance checks), Leave
  Configuration (Types/Holidays/Work Week/Periods tabs — Work Week auto-seeds Mon–Fri
  full/Sat–Sun non-working on first empty mount), and Leave Reports (Balance & Usage, Carryover &
  Forfeiture).

**Two data-integrity bugs, both root-caused to the same cause:** migrations run *before* seeders on
a fresh install, so a one-time backfill migration for `leave_request_days`/consumption produced
**zero rows** on `migrate:fresh --seed` — confirmed via tinker showing `leaveBalance()` reporting
`used=0` where the hand-authored demo narrative expected `7.5`. Fixed by extracting the
day-materialization logic into `LeaveRequestService::materializeLegacyHeader()` (deliberately
simpler than the real generator — an even split across weekend-only working dates, documented as
such since it's for pre-decided/hand-authored headers only) and calling it from
`HrisDemoSeeder` directly after every `LeaveRequest::create()`, with the migration refactored to
call the same shared method rather than duplicate the logic. The same root cause then recurred
twice more for `LeaveEntitlement`/`LeaveType`'s new columns, which only the migration's backfill had
populated — fixed by setting them directly in the seeder's own creation calls.

Also caught: `LeaveEntitlementConsumption::entitlement()` had Eloquent guess the wrong FK
(`entitlement_id` instead of the real `leave_entitlement_id`) — a recurrence of the same
FK-guessing gotcha from Phase 1, fixed with an explicit second argument.

### C2 — Time & Project Tracking

`Customer` → `Project` → `ProjectActivity`, with `ProjectAssignment` (pivot,
`is_administrator` flag) controlling who can log time against a project. Weekly timesheets
(`Timesheet` + `TimesheetLine`, one row per project+activity with 7 day-columns) go through the
existing generic `WorkflowEngine` under a brand-new `timesheet` workflow type — proven this session
that the engine needed **zero code changes** for an entirely new domain: `timesheet-editor`/
`timesheet-approvals-board` just call `WorkflowEngine::apply()` then re-check status and log the
action via `Timesheet::logAction()` (an append-only `timesheet_action_logs`, `const UPDATED_AT =
null`). Verified end-to-end across three real demo sessions: Adaeze submits → Emeka (her real
seeded supervisor) rejects → Adaeze resubmits → Emeka approves → Admin resets (admin-only undo).

- **Reports** (`timesheet-reports-viewer`): group by project, activity, or employee; only counts
  *approved* timesheets, since a submitted-but-pending timesheet's hours aren't final.
- **RBAC**: new `timesheets` data group and screen, `admin.projects` screen, full matrix entries
  across all five roles (admin: all/view_edit_delete down to ess: self/view_edit).

**Bug caught by the routine `php -l` lint pass, before any test ran against it:**
`timesheet-reports-viewer.blade.php` chained `match ($this->groupBy) { ... }->map(...)` directly off
the match expression's closing brace — a PHP parse error (chaining a method call onto a `match`
literal isn't legal syntax). Fixed by assigning the match's result to `$grouped` first.

### C3 — Attendance (Time Clock)

Replaced `Employee`'s old `clocked_in`/`clocked_in_at` boolean+timestamp pair with a proper
`attendance_records` ledger (one row per punch, separate UTC/local/timezone columns for both punch
in and out) — a data-preserving migration backfills any employee caught mid-punch before dropping
the old columns (0 employees were mid-punch on this dataset, so 0 rows backfilled, which is the
correct outcome, not a skipped step).

- **`AttendanceService`**: `punchIn()`/`punchOut()` row-lock the employee's open record
  (`lockForUpdate()`) inside a transaction to prevent a race producing two open punches;
  `assertNoOverlap()` rejects any new/edited range that overlaps an existing record for that
  employee (an open record is treated as extending to "now" for this check). Client-declared
  timezone capture — spec's exact "no IP/GPS geolocation" requirement — needed **no new code**:
  `TimezoneController`'s browser-Intl-API capture and `User::displayTimezone()` already existed
  from an earlier phase and were reused as-is for punch timestamps.
- **Three independently toggleable Admin permissions**, all off by default (Admin is always
  exempt): back-dating a punch, an employee editing their own past punches, and a supervisor
  editing/proxy-punching for their *direct* reports only (`supervisor_id` match, not the whole
  subordinate tree). Each is a plain boolean on `Setting`, surfaced as its own checkbox in the
  Settings → Time & Attendance card, and re-checked server-side on every action regardless of what
  the Livewire client sends (the employee picker only *offers* permitted targets, it isn't the
  enforcement).
- **`attendance-manager`** (self-service + supervisor + Admin in one screen): an employee picker
  (self, plus direct reports if any, plus everyone for Admin), punch in/out with an optional
  back-dated time input shown only when permitted for that viewing context, and a record list with
  Edit/Delete gated per-record by the same permission checks. **Attendance Reports** adds
  cross-employee, date-range summary reporting (punches + total hours, grouped by employee).
- Fixed a latent mass-assignment gap caught proactively, not reactively (unlike its three prior
  occurrences in Phases 1–2): `time_display_format` had been added to the `settings` table by a C2
  migration but never added to `Setting::$fillable` — found and fixed before any UI exposed it, in
  the same edit that added the three new attendance-permission columns.

**Verified in the browser** (not just tinker) across three real sessions: Adaeze punching herself
in/out from both the Home widget and the Attendance page (state stays consistent across both);
editing a punch's time triggering `assertNoOverlap()`'s exact rejection message; Emeka (supervisor)
switching the employee picker to Adaeze, proxy-punching her in and out (record correctly tagged
"Proxy"), and having Edit/Delete rights on her records once the proxy toggle was enabled; Admin
toggling all three permissions live in Settings and immediately seeing the effect reflected in
Adaeze's and Emeka's sessions; the Reports screen aggregating punches across employees for a
date range. `wire:confirm`-gated Delete was verified via tinker instead of the browser, per the
established pattern (headless clicks can't reliably accept a native confirm dialog) — confirmed the
underlying `AttendanceRecord::delete()` call removes exactly the targeted row.

### Not built (honest gaps, not silently dropped)

- Leave: no in-app UI for editing/reordering entitlement batches by hand — the Accrual Engine and
  the seeder are the only two ways entitlement rows get created; an HR admin can't yet hand-adjust
  a balance outside of assigning a new leave request.
- Timesheet/Attendance reports are both totals-only (no CSV export) — Phase 1's reporting screens
  got CSV export because spec asked for it explicitly there; C2/C3's spec wording is looser
  ("summary reporting") and CSV wasn't built here to keep this phase's scope to what's cited.
- Attendance has no biometric/kiosk/hardware punch-clock integration — "Time Clock" here means the
  in-app punch button only, matching spec's own scope for this phase (hardware integration isn't
  mentioned in Section C3).
- No leave-request-to-timesheet or timesheet-to-attendance cross-validation (e.g. flagging a
  timesheet day that claims hours while the employee was also on approved leave) — each of C1/C2/C3
  is self-contained; that kind of cross-domain reconciliation isn't in spec's C-section wording and
  would be speculative scope for this phase.

### Verified this session (summary)

Every migration ran clean on a full `migrate:fresh --seed` cycle at each checkpoint (up to 73
migrations by the end of C3); `vendor/bin/pint` and `php artisan test` passed before every commit;
every new screen was exercised through the actual browser UI logged in as multiple real demo users
(not just Admin), with service-layer edge cases (overlap, double-punch, FIFO consumption ordering,
proration formula) additionally verified directly via tinker against the raw database. Four commits,
each pushed with CI green: `b9efdbf`→`e428150`→`37efec6`→`e2b22e3`.

**Phase 2 is now complete against spec Section C's deliverable list.** Domain C (Leave, Time &
Attendance) — C1, C2, and C3 — is built, tested, and live. Phase 3 can begin.

## Phase 3 — Domain D: Talent Management

Spec Section D in full: D1 Recruitment, D2 Performance Management, D3 Employee Relations/Discipline
Case Management. First phase to extend `App\Services\WorkflowEngine` itself (a minimal, additive
`workflowActorTags()` hook), and the first real consumer of the E-Signature & Digital Consent
Service (`SignatureService`, built in Phase 0 ahead of any user).

### D1 — Recruitment

- **Requisition → Vacancy → Candidate Pipeline**, three linked workflows on the same generic engine:
  approving a Requisition auto-creates its Vacancy; a `CandidateApplication` moves through
  `application_initiated → shortlisted → interview_scheduled → interview_passed → job_offered →
  hired` (plus `interview_failed`/`offer_declined`/`rejected` branches), with `hire()` auto-creating
  the `Employee` record via the existing `EmployeeIdGenerator` and setting `supervisor_id` from the
  vacancy's hiring manager.
- **"Hiring manager" as a new workflow actor**, resolved via a purely additive
  `workflowActorTags(User $user): array` hook a record may optionally implement — `CandidateApplication`
  returns `['hiring_manager']` when `$this->vacancy->hiring_manager_id` matches, scoped to that
  specific vacancy rather than the reporting-line graph. Proven not to affect any existing workflow
  since none of Leave/Timesheet/Attendance/Expense Claim implement the hook.
- **Interview round cap enforced structurally, not just by convention**: `scheduleInterview()` is
  the only path that can trigger the `'schedule_interview'` workflow action —
  `applyPipelineAction()` hard-aborts if called with that action, so a caller using the generic
  dispatch can't bypass `Interview::MAX_ROUNDS_PER_APPLICATION`. Caught and fixed by me before
  shipping, not by a test failure.
- **External (no-login) e-signature**: `SignatureService::sign()` extended to accept `?User $signer`
  plus `signer_name`/`signer_email` for a signer with no system account — first used for a Candidate
  signing their offer letter via a Laravel signed URL (`recruitment.offer.sign`, `middleware('signed')`,
  no auth), surfaced directly in the pipeline UI since there's no live outbound email in this
  prototype.
- Public careers site (`/careers`, `/careers/{vacancy}`, `/careers.rss`) on a new lightweight
  `layouts.public` layout, unauthenticated by design.

### D2 — Performance Management

- **Independent dual reviewer tracks**: `PerformanceReviewer` is its own model (one row per
  group — `supervisor` or `self` — per review), each with its own status, rather than encoding both
  tracks into `PerformanceReview.status`. `activate()` is KPI-gated and creates both tracks' reviewer
  + rating rows in one transaction; `signOff()` (click-to-sign via `SignatureService`) blocks if any
  KPI is unrated or the track is already signed; `finalize()` (HR-only) force-completes both tracks
  and computes the final score.
- **360° Feedback**: `FeedbackCycle` → `FeedbackParticipant` (relationship tag manager/peer/
  direct_report/self) → `FeedbackResponse`. `isAttributed()` is true only for manager/self — peer and
  direct_report responses are pooled and anonymized in `aggregatedResults()`, never shown
  per-respondent.
- No `WorkflowEngine` changes needed — sign-off/finalize are plain service-method actions, not
  workflow-engine transitions.
- Caught the MySQL 64-character identifier limit twice in this module alone
  (`performance_reviewers`'s and `feedback_responses`'s auto-generated composite-unique-index names
  both exceeded it) — both fixed with an explicit short index name. First occurrence of this bug
  class in the project; added to the pre-push mental checklist from here on.

### D3 — Discipline Case Management

- **No `WorkflowEngine` extension needed** — `DisciplinaryCase.employee_id`/`employee->supervisor_id`
  already match the engine's built-in owner/supervisor resolution exactly, unlike D1's hiring-manager
  case.
- **HR Officer's spec-mandated total exclusion** ("no access to Discipline case data at all") is
  enforced as a genuine route-level block — the `discipline` screen is simply never granted to that
  role — rather than relying solely on the `disciplinary_case` data group returning `none`, which
  would still let the route load with an empty list. A weaker guarantee than spec's wording demands.
- Fixed two pre-existing RBAC stub bugs while building the feature they were speculatively seeded
  for: `disciplinary_case`'s `supervisor` row (was `['none','none']`, corrected to
  `['self_subordinates','view_edit']`) and its `ess` row (was `['none','none']`, corrected to
  `['self','view_edit']` — a plain employee gets real self-scoped access to their own case, not
  exclusion).
- `acknowledgeOutcome()`: only the case subject may sign, via `SignatureService`, blocking
  double-acknowledgement.

### Verified this session (summary)

Every migration ran clean on `migrate:fresh --seed` and against a throwaway SQLite file at each
checkpoint (the SQLite check became mandatory mid-D1 after a MySQL-only raw-SQL migration broke CI
— see the "CI uses SQLite" note below); `vendor/bin/pint` and `php artisan test` passed before every
commit. Each module was exercised through the real browser UI across multiple real demo-user
sessions (HR Admin, a hiring manager, a public unauthenticated visitor for D1; Emeka/Adaeze rating
and signing off both tracks for D2; the full raise → respond → resolve → acknowledge cycle for D3),
plus tinker verification of the harder edge cases (ownership/ok scoping, round-cap enforcement,
HR Officer's total exclusion at both the screen and data-group level). Four commits, each pushed
with CI green: `018da66` (D1) → `0037244` (CI fix) → `f79b88a` (D2) → `c606297` (D3).

**One infrastructure lesson learned the hard way**: GitHub Actions CI runs migrations against a
fresh **SQLite** database, not MariaDB — a MySQL-only raw `DB::statement('ALTER TABLE ... MODIFY
...')` in D1's `signature_events` migration passed local `migrate:fresh` but broke the CI pipeline.
Fixed by installing `doctrine/dbal` and using Blueprint's `->nullable()->change()` instead. Every
migration from D2 onward (and retroactively, every migration in every later phase) is tested against
a throwaway SQLite file before pushing, not just against local MariaDB.

**Phase 3 is now complete against spec Section D's deliverable list.** Domain D (Recruitment,
Performance, Discipline) — D1, D2, and D3 — is built, tested, and live. Phase 4 can begin.

## Phase 4 — Domain E: Operational & Financial Extensions

Spec Section E in full: E1 Expense Claims extensions, E2 Asset Management, E3 Vehicle Fleet
Management, E4 Policy Document Management, E5 Company Registration & Compliance Documents. The
first phase built around **deliberately shared infrastructure**: the Renewal & Compliance Reminder
Engine (scaffolded in Phase 0, first real consumers here), and a generic polymorphic
`CustomFieldDefinition`/`CustomFieldValue` + `AssignmentHistory` pair (plus `HasCustomFields`/
`HasAssignmentHistory` traits) built once during E2 specifically so E3's Vehicle register could
reuse them with zero schema changes — which it did.

### E1 — Expense Claims extensions

Basic single-level expense claims already existed from an earlier phase; this slice added the parts
explicitly flagged as gaps in that prototype:

- **Receipt upload** via `HasMedia` on `ExpenseClaim`.
- **Travel Advance & Reconciliation**: `TravelAdvance` on its own two-stage workflow
  (`pending_manager → pending_hr → approved → paid`, mirroring `expense_claim`'s shape).
  Reconciliation is computed live, not stored — `ExpenseClaim::reconcilingAdvance()` matches by
  `(employee_id, claim_event_id)` at query time (the advance predates the claim, so no FK can link
  them at raise-time), and `reconciliationVariance()` computes what's owed back or still due.
- **Amount-based second approval**, using the *same* generic `WorkflowEngine` per spec's explicit
  instruction not to special-case it: the calling UI (`approvals-board.blade.php`) picks between the
  `'approve'` and `'approve_high_value'` actions based on `$claim->requiresSecondApproval()` against
  an Admin-configured threshold — the reviewer's click experience is identical either way; only the
  resulting state (and therefore the next actor) differs.
- New "Claims Management" HR filtering screen (`admin.claims-management`).

### E2 — Asset Management

- **Status/assignee consistency enforced server-side, never as a side effect**: `AssetService::assign()`
  is the only path that can set `status = 'assigned'`, always paired with `current_employee_id` and a
  new open `AssignmentHistory` row in one transaction; any prior open history row is closed first,
  keeping the log fully gapless.
- **Shared infrastructure built here, designed up front for E3 to reuse**: `CustomFieldDefinition`
  (admin-defined label/type/order, `subject_type` discriminator `'asset'|'vehicle'`) /
  `CustomFieldValue` (polymorphic, values always stored as text per spec's own noted trade-off), and
  `AssignmentHistory` (polymorphic `assignable`, `employee_id`/`sub_unit_id` both nullable — built
  nullable specifically so E3's dual employee-or-department assignment could reuse the same table
  without a schema change). Two traits, `HasCustomFields`/`HasAssignmentHistory`, are the only thing
  a consuming model needs to `use`.
- **Warranty tracking** registers with the Renewal & Compliance Reminder Engine (first real consumer
  of that Phase-0-built engine) under a new `asset_warranty` `RenewalType`, seeded with the spec's
  default 60/30/14/7-day tiers, notifying Admin + HR Admin.
- **Self-only visibility, no supervisor tier** — deliberately unlike every other module's
  reporting-line scoping (Assets is the first module in the project where a supervisor gets nothing
  beyond their own personal assets).
- **Offboarding hard block retrofitted into the existing B3 termination flow**: an employee with any
  asset still showing `current_employee_id` on them cannot be marked terminated — flagged in Phase 1
  as scaffolded-but-not-wireable-yet, actually wired here now that Asset Management exists.
- A real UI bug caught and fixed during browser testing, not before: the Assign/Unassign button
  toggled on `$asset->status === 'assigned'` rather than on whether `current_employee_id` is set, so
  an asset moved to `in_repair` while still assigned displayed "Assign" instead of "Unassign" —
  fixed to check the assignee field directly.

### E3 — Vehicle Fleet Management

The direct payoff of E2's shared-infrastructure decision: `Vehicle` reuses `AssignmentHistory` and
`CustomFieldValue`/`CustomFieldDefinition` (subject_type `'vehicle'`) via the same two traits, with
**zero migration or trait changes** needed.

- **Dual assignment, mutually exclusive**: a vehicle's current holder is either an `Employee` or a
  `SubUnit` (department), never both — enforced in `VehicleService::assign()` via an XOR check, not
  a DB constraint (no CHECK-constraint precedent in this app, and SQLite/MariaDB parity is safer
  without one).
- **Renewals preserve full history by design**: unlike Asset warranties (edited in place via
  `renewWarranty()`), a Vehicle renewal is "re-renewing creates a new row" per spec — each new
  `VehicleRenewal` row registers its own fresh `Renewable`, and the *prior* row's `Renewable` (if
  still active, matched by the same freeform `label`) is explicitly `retire()`d so it stops firing
  reminders. Both rows stay queryable forever.
- **Driving-license validation at assignment**: a warning-with-override, not a hard block, per
  spec's explicit reasoning ("license data may not yet be complete for every existing employee").
  Checks the employee's B2 Qualifications license record against a canonical `MasterListItem`
  ("Driving License", `type=license_type`) seeded by a new `MasterListSeeder` specifically because
  this is the one master-list row every install needs to exist reliably rather than depend on an
  Admin happening to create one with exactly that name. The override reason and authorizing user are
  logged directly on the `assignment_histories` row via two new nullable columns
  (`override_reason`/`overridden_by_id`) added to that *shared* table — Asset assignments simply
  never populate them.
- **Fuel/mileage logging with odometer-based maintenance-due computation**, deliberately scoped down
  from spec's fuller "the reminder engine evaluates both the calendar and the odometer" framing: a
  renewal's optional `mileage_interval` computes a `due_at_mileage` threshold checked live
  (`VehicleRenewal::isMileageDue()`) against the latest fuel-log odometer reading, surfaced as a UI
  flag — not wired into the shared engine's own notification-tier sweep, which is fundamentally
  calendar-driven. Documented here as a deliberate scope trade-off rather than a silent gap.
- Genuine cascading delete (unlike Assets, which only supports retire): `VehicleService::deleteVehicle()`
  explicitly cleans up custom field values, assignment history, and every renewal's `Renewable`
  registration before deleting the vehicle row itself, since DB-level `cascadeOnDelete()` on the
  vehicle-owned tables doesn't reach the polymorphic ones.
- Extends the same offboarding hard block from E2 to cover vehicles.

### E4 — Policy Document Management

- **No separate acknowledgement table** — each acknowledgement *is* a `SignatureEvent` (Section A8)
  against a `PolicyDocumentVersion` with `purpose = 'policy_acknowledgement'`, giving every
  acknowledgement a tamper-evident, exact-version-hashed evidence record for free, and making
  `hasValidSignature()` the natural idempotency check.
- **"Outstanding acknowledgements" (including new-hire enrollment) is a live computation, never a
  materialized/backfilled row**: every active, access-eligible employee minus those with a valid
  signature against the document's current version. The moment a new hire's `Employee`+`User` exist
  and they aren't terminated, they correctly show up in their own outstanding list with zero
  enrollment-event wiring needed — a cleaner solution than spec's own framing of "the system
  automatically enrolls the new employee," which implies (but doesn't require) a materialized row.
- **Private-disk file storage, always**: every `PolicyDocumentVersion` file lives on the `'local'`
  disk (not the app's usual public media disk) and is served exclusively through
  `PolicyDocumentFileController`, never a directly guessable public URL — true for every document,
  restricted category or not, which is simpler than conditionally switching disks per category and
  a reasonable general precaution regardless.
- **Restricted categories** gate via `PolicyFileAccessGrant` (role or named employee), with every
  successful access to a restricted file written to the audit log (denied attempts are not logged,
  matching spec's literal "every access...is written" wording).
- Two real bugs caught during this session's browser testing (general lessons, not E4-specific):
  1. `outstandingFor()` initially listed restricted-category documents as "outstanding" for
     employees who had no grant to even see them — fixed to run the same `canAccessFile()` check the
     browse view already used.
  2. A `wire:model.live` radio combined with a still-**deferred** `wire:model` select can silently
     lose the select's value when the radio's own live request fires first and re-renders from the
     last-synced server state. First found in `custom-field-manager`'s field-type selector (switching
     to "Select" never revealed the options input) and again in the Policy/Company-Document access
     grant forms. **The fix, applied everywhere from here on: once any field in a small form is
     `.live`, make the whole form `.live`** rather than leaving it order-dependent on which field the
     user happens to touch first.

### E5 — Company Registration & Compliance Documents

Phase 4's last module, closing out Domain E. Distinct from E4 (company legal/compliance instruments,
not employee-facing HR policy), and **restricted by default** rather than E4's opt-in-restricted
categories — Admin/HR Admin are the only implicit bypass; every other viewer needs an explicit
`CompanyDocumentFileAccessGrant` scoped to either a whole category or one specific document (spec's
own "external auditor" example).

- Reuses the same additive-versioning and private-disk-file patterns as E4's `PolicyDocumentVersion`.
- Renewable documents register against the shared Renewal & Compliance Reminder Engine under one
  `company_registration_document` `RenewalType` — the same deliberate "one org-wide tier set, not
  per-document-configurable" scoping decision made for Vehicle Renewals in E3, applied consistently
  a third time.
- Every upload, view, download, and renewal is audit-logged (broader than E4, which only logs
  restricted-file access — here, *every* document is restricted, so every access qualifies anyway).
- **No self-service browse screen for non-admin grantees** (a documented scope trade-off, not a
  silent gap): a specifically-granted employee or role reaches a file only via its direct download
  link, never a dedicated "documents I've been granted" listing — building that listing UI for the
  rare non-admin-grantee case was judged disproportionate to its payoff given this module's
  Admin/HR-Admin-centric design.
- Caught the MySQL 64-character identifier limit for a third time in the project: naming the FK
  column `company_registration_document_id` (matching the model name, the obvious first choice)
  pushed the auto-generated constraint name to 82 characters. Fixed by shortening the column to
  `document_id` throughout — and, on the *same* migration, found the access-grants table's own
  auto-generated constraint name sat at *exactly* MySQL's 64-character boundary (technically legal,
  but a fragile margin for any future column rename), so renamed that table too
  (`company_document_file_access_grants` → `document_access_grants`) rather than leave it right on
  the edge.

### Verified this session (summary)

Every migration ran clean on `migrate:fresh --seed` and against a throwaway SQLite file at every
checkpoint; `vendor/bin/pint` and `php artisan test` passed before every commit. Every module's core
service-layer logic (assignment consistency, mutual-exclusivity checks, license validation,
acknowledgement idempotency, restricted-file access control, audit logging, renewal-cycle
retire/register) was verified via tinker with real byte-content files, not Laravel's
`UploadedFile::fake()->create()` test helper — which was discovered mid-E4 to report a fake declared
file size while actually writing zero bytes, a testing-tool quirk unrelated to any of the product
code it was used to exercise. Every screen was additionally exercised through the real browser UI
across multiple demo-user sessions (Admin, HR Admin, a supervisor, and an ordinary ESS employee),
including the full grant → 403 → grant-created → 200 loop for both E4's and E5's restricted-file
paths, and the vehicle offboarding hard block firing with the vehicle correctly named in the error.
Two Livewire component tests (`Livewire::test(...)`) were used to get unambiguous ground truth on
the Policy and Company-Document access-grant forms after repeated browser-automation attempts failed
to reproduce a successful submission — both confirmed the actual component logic was correct throughout;
see the `.live`-field lesson under E4 above for the real (browser-automation-only) cause.

Five commits, each pushed with CI green: `0f38839` (E1+E2) → `dda2ff8` (merge — E1 had already been
pushed as `f58f247` from a prior context window, discovered only when the push was rejected;
resolved with a genuine 3-way merge, not a force-push) → `db2bb5d` (E3) → `8cb6579` (E4) → `b6f38d0`
(E5).

**One process lesson from this phase, unrelated to any single module**: the project's courtesy
mirror at `/home/michael/Downloads/HRIS/app` must only ever be synced **from** the git repo at
`/var/www/html/hris`, never the reverse. Running `rsync` backwards once (mirror → repo) silently
overwrote an entire uncommitted feature's worth of RBAC/route/nav-link edits with the mirror's
stale, last-commit-time snapshot, and reintroduced an already-abandoned draft migration
(`Province`) that had been sitting unreferenced in the mirror. Recovered by manually diffing every
affected file against this conversation's own record of what should have been there. Saved to
persistent memory (`project_rsync_direction.md`) so this doesn't recur in a future session.

**Phase 4 is now complete against spec Section E's deliverable list.** Domain E (Expense Claims
extensions, Asset Management, Vehicle Fleet Management, Policy Document Management, Company
Registration & Compliance Documents) — E1 through E5 — is built, tested, and live. Phase 5 can
begin.

## Phase 5 — Domain F: Engagement, Analytics & Auxiliary Services

Spec Section F in full spans seven modules (F1–F8, F7 explicitly descoped — see below). This phase
covers F3 Corporate Directory, F1 Employee Social Feed ("Buzz"), F4 Helpdesk/Support Ticketing, and
F8 Employee Engagement & Pulse Surveys, built in that order because each is largely independent of
the others — unlike Phase 4, there was no shared-infrastructure dependency chain to sequence around.
F2 (Dashboard & HR Analytics), F5 (Help & Support Integration), and F6 (Mobile API Coverage) remain;
F2 in particular is deliberately being built *after* F1/F4/F8 so its widgets have real data sources
(an eNPS tile, a Helpdesk open-tickets summary, etc.) rather than placeholders.

### F3 — Corporate Directory

The simplest module in the phase: no new tables. A single searchable/filterable listing over the
existing `Employee` table, with two visibility rules baked into one query rather than two code
paths: termination-purged employees (`is_gdpr_purged = true`) are always excluded outright, while
*merely* terminated (not yet purged) employees are excluded from the default browse but *do* appear
once the user searches by name or Employee ID — spec's explicit "still findable by direct lookup,
just not part of the ambient headcount browse" distinction, implemented as a single
`->when(! $this->search, fn ($q) => $q->whereDoesntHave('terminations'))` clause.

### F1 — Employee Social Feed ("Buzz")

The architecturally richest module this phase. Core decision: a `Post` (immutable authored content —
text/photo/video) and every *appearance* of it (`PostShare` — either the original posting or a
reshare) are separate tables, each `PostShare` carrying its own independent like/comment thread. This
makes "reshare" a first-class, cheap operation (a new `PostShare` row pointing at the same `Post`)
without duplicating content, while keeping each share's engagement numbers genuinely separate, as
spec requires.

- Denormalized `like_count`/`comment_count` on `PostShare` for list-view performance, kept correct by
  the mutating service methods (`toggleLike`, `addComment`) rather than recomputed per-request, with
  `ReconcileBuzzCounts` as a daily backstop command (`buzz:reconcile-counts`) against any drift.
- Polymorphic `PostLike` (`likeable_type`/`likeable_id`) covers both post-shares and comments through
  one table and one `unique` constraint, rather than two near-identical like tables.
- **Deleting a post's original posting cascades to delete the underlying `Post` and every reshare of
  it** — a deliberate, spec-driven recursive delete (not a soft-delete or an orphaning), gated by
  `canManagePost()` (author or `scopeFor($user,'buzz') === 'all'` for moderation).
- Two real bugs caught and fixed during build, not left as known issues: a Blade attribute with a
  nested escaped double-quote inside a double-quoted HTML attribute compiled to invalid PHP
  (`ParseError`) — `php -l` on the raw `.blade.php` didn't catch it, since Blade compilation happens
  at a different layer than plain PHP linting; fixed by removing the inner escaped quotes entirely.
  Separately, the "upcoming work anniversaries" widget's date filter was comparing a hire-date to
  itself rather than to today, so it never actually matched anything within the intended 7-day
  window — rewritten to re-year each employee's hire date onto the current year (rolling to next
  year if already past) and filter against a proper `between($today, $today->addDays(7))`.
- Verified live in the browser with real byte-content image uploads (via a JS canvas→blob→File→
  DataTransfer injection, since headless automation has no real file picker), a YouTube embed, an
  independent-counts reshare, comments, like toggling, and ownership-gated delete.

### F4 — Helpdesk / Support Ticketing

Ordinary IT/HR/Facilities tickets plus a dedicated confidential "Grievance / Whistleblower" category
with materially narrower visibility than anything else in the app.

- **Deliberately no `WorkflowEngine`**: ticket status transitions (`open → in_progress → resolved →
  closed`, plus `reopen`) are performed by a single actor class (HR/Admin, or — for confidential
  categories — a small Admin-designated handler list) with no multi-stage/multi-actor handoff to
  justify the generic engine. This is the same judgment already applied to Asset/Vehicle status and
  Company Document activation in Phase 4, made explicit here rather than silently repeated.
- **Confidentiality narrower than ordinary HR/Admin access**: a `HelpdeskCategoryHandler` grant table
  (role-or-employee, mirroring E4/E5's restricted-file-grant shape) gates visibility into confidential
  categories. Admin keeps its usual implicit bypass, but HR Admin/Officer does **not** automatically
  see confidential tickets despite normally having full HR visibility — verified live that a raiser's
  actual reporting-line supervisor (Emeka, not a seeded handler) sees the ticket list as empty.
- Anonymous submission (`is_anonymous`) for confidential categories, with a `revealIdentity()` action
  that is always audit-logged (`action = 'identity_revealed'`) regardless of who calls it or how many
  times, since every reveal is independently loggable evidence.
- Reused E4's acknowledgement-via-`SignatureEvent` pattern's sibling idea for comments: no separate
  notification-fanout table, just a plain `ticket_comments` thread scoped by the same `canView()` gate
  as the ticket itself.

### F8 — Employee Engagement & Pulse Surveys

Short, anonymous, recurring engagement check-ins (eNPS or Likert scale) with a minimum-N
anonymization threshold before any aggregate is shown — the same anonymization pattern already
established for 360° Feedback (D2), reused rather than reinvented.

- **Stronger anonymity guarantee than D2's**: `PulseSurveyResponse` deliberately has *no*
  `employee_id` column at all. Whether a given employee has responded is tracked in a wholly separate
  `PulseSurveyParticipation` table holding only a boolean — two tables that are never joinable back
  into "who said what," rather than one row with a sometimes-nulled identity column.
- **Live computation, no enrollment event**: both `audienceEmployees()` (who's targeted, by
  all/department/location scope) and `nonRespondents()` (who still needs a reminder) are computed
  fresh from current employee state every time, not backfilled once at launch — a new hire who joins
  the targeted department mid-run is automatically included on the next computation, with zero
  explicit backfill code, mirroring E4/F8's shared "outstanding = eligible minus recorded" idea.
  (`PulseSurveyParticipation` rows *are* pre-created at launch for the initial audience so a
  historical run's target list stays stable even if that employee later transfers out.)
- **Lightweight profanity flag, not full moderation**: a keyword-blocklist check on free-text answers
  sets `is_flagged`; flagged comments are counted (`flaggedCount`) but never shown in the plain-text
  list an admin sees — visible enough to know moderation may be needed, without exposing the content
  and its writing style to a small enough group that authorship could be guessed.
- **Recurrence is self-spawning and idempotent**: `closeRunAndSpawnNext()` checks for an existing
  child by `parent_run_id` before creating one, so re-running the daily scheduled command
  (`pulse-surveys:process`) never double-spawns even if a prior day's job somehow ran twice.
- One real bug caught during tinker verification, not left as a known issue: `aggregatedResults()`
  initially read `$run->responses` (the cached Eloquent relation *property*), which memoizes on first
  access — calling it twice on the same PHP object instance within one script (once before any
  responses existed, once after inserting six) returned the stale, empty cached collection the second
  time, incorrectly reporting `sufficient: false` with real data in the database. Fixed by switching
  to `$run->responses()->get()` (a method call, forcing a fresh query) — a general lesson about
  Eloquent relation caching, not specific to this feature, worth remembering anywhere a service method
  might be called more than once against the same model instance after a mutation in between.
- Admin UI is a three-tab shell (Templates — including the admin-editable anonymization-threshold
  setting, Launch a Run, Runs & Results) over three child Livewire components, following the
  established "inert page + child components" pattern; the run-launch form was built `.live`
  throughout from the start (audience-scope selection conditionally reveals department/location
  pickers; the recurring toggle conditionally reveals the recurrence-interval field), applying the
  `.live`-field lesson learned the hard way in Phase 4 rather than rediscovering it a third time.
- **Build-tooling gotcha hit while verifying the results UI in-browser**: a stat-tile row used
  `gap-6`, the first use of that exact utility class anywhere in the codebase — Tailwind's Vite build
  only compiles classes it finds by scanning source files, so the dev box's already-built CSS
  (from before this session's edits) simply didn't contain it, and the tiles rendered bunched
  together with no gap. Diagnosed via `getComputedStyle` showing the browser default instead of the
  Tailwind value, not a Blade/logic bug. Fixed by rerunning `npm run build`. Saved to memory
  (`project_tailwind_build_staleness.md`) since it'll recur with any first-use utility class.

### Verified this session (summary)

`vendor/bin/pint`, `php artisan test`, `migrate:fresh --seed --force`, and a throwaway-SQLite
parity check all passed before every commit. Every module was exercised live in the browser across
multiple demo-user sessions (Admin, HR Admin, a supervisor, and an ordinary ESS employee): Buzz's
full post/reshare/comment/like/delete lifecycle with a real uploaded image; Helpdesk's confidential-
category visibility boundary (confirmed a real reporting-line supervisor sees nothing); and Pulse
Survey's full loop — template creation, run launch, pre-threshold suppression (0 of 5), post-
threshold reveal with a hand-verified eNPS calculation (3 promoters / 2 detractors / 6 responses →
17), a flagged free-text comment correctly hidden from the visible list but counted, and the
self-service responder correctly hiding a run once answered. Recurring-run auto-spawn and reminder
notifications were verified via tinker (scheduled-job behavior, not a UI flow).

Four commits, each pushed with CI green: `2dbfe77` (F3) → `271b544` (F1) → `875e648` (F4) →
`e009bde` (F8).

**F7 (Workspace Notifications) is explicitly descoped per spec and will not be built.**

### F2 — Dashboard & HR Analytics

Built deliberately last among F1/F3/F4/F8, since three of its "New" widgets (eNPS tile, Helpdesk
summary, consolidated Renewals) only have real data to show once the modules they read from exist.
The existing Home page from earlier phases already covered the original spec's baseline widgets
(personal leave/claims stat cards, quick actions, who's-out-today, a team card); this slice extended
it to full spec compliance rather than replacing it.

- **Nightly HR-metrics snapshot** (`HrMetricsSnapshot`, one row per calendar day via `hr-metrics:snapshot`,
  scheduled daily): active headcount, trailing-30-day new hires/terminations, a turnover rate computed
  the standard way (separations ÷ average of start-of-window and current headcount × 100, with the
  start-of-window headcount *reconstructed* from today's count plus who has since joined/left it,
  avoiding a second, slower historical query), open requisitions, pending leave requests, and average
  tenure. `HrMetricsService::monthlyTrend()` collapses daily rows to one point per calendar month
  (last snapshot of each month), giving the "latest snapshot plus up to 12 monthly points" the spec
  asks for from what is still a daily-granularity table — the recommended pattern the spec calls out
  for any future "trend over time" need in the system.
- **Personal/team action summary** (`⚡dashboard-action-summary`): pending leave and timesheet
  approvals reuse `WorkflowEngine::pendingFor()` — the *same* reporting-line-scoped, authoritative
  source the Approvals/Timesheet-Approvals boards themselves already query — rather than a naive
  status count, so the widget's numbers are always exactly what the linked screen would show:
  Discovering this also **fixed a real, pre-existing bug**: the nav sidebar's Approvals badge had
  been computed as an org-wide, unscoped count of every `pending_hr` leave/claim in the system
  (correct only for an HR user, and missing every `pending_manager` item a supervisor could act on
  entirely) rather than what *this* user could actually approve. Replaced with a new
  `⚡approvals-badge` Livewire component built on the same authoritative `pendingFor()` source, with
  `wire:poll.15s` standing in for the spec's WebSocket-pushed "live-updating approval counts" — the
  same honest no-Reverb-server trade-off already documented on the notification bell. Performance
  reviews (`PerformanceReviewer.status = 'pending'`) and upcoming interviews-to-conduct round out the
  widget; each category is included only when its module is enabled (`ModuleToggle::isEnabled()`).
- **Headcount-by-department/location charts** (`⚡dashboard-headcount-charts`): simple, dependency-free
  CSS bar charts (no charting library exists anywhere in this codebase, so none was introduced for
  one widget) grouping active, non-purged employees by sub-unit and by location.
- **"Who's out today" configurable scope**: a new Admin setting (`dashboard_who_is_out_scope`,
  'everyone'|'scoped', default 'scoped' to preserve the existing behavior) toggles between "every
  employee on leave today" and the pre-existing "only employees in your own reporting line" scope,
  per spec's explicit bullet. Added to the Settings screen's new "Dashboard" section, following the
  same `wire:model.live` + `updated<Field>()` auto-save pattern as every other toggle on that page.
- **"Tasks for you" now shows real data** instead of a hardcoded placeholder that (as of this phase)
  had become stale: outstanding policy acknowledgements (reusing `PolicyService::outstandingFor()`
  from E4) and open pulse-survey runs targeted at the viewer who hasn't yet responded (reusing F8's
  own audience/response-check methods) — both modules now exist, closing a gap the Home page's own
  code comment had flagged since Phase 4.
- **New Admin/HR Admin-only insights section** (`⚡dashboard-hr-insights`, plus a separate
  `⚡dashboard-renewals-widget`): latest eNPS score with a trend arrow against the prior scored run;
  open Helpdesk ticket counts by category, excluding confidential categories from any viewer who
  isn't Admin or a designated handler (reusing `TicketService::isHandler()` from F4) exactly as spec
  requires; and the HR-metrics trend chart. The consolidated Renewals & Compliance widget queries the
  *same* generic `Renewable` polymorphic table already shared by Vehicle Renewals (E3), Asset
  warranties (E2), and Company Registration Documents (E5) — since that table was designed as
  shared platform infrastructure back in Phase 4 specifically so a consumer like this could query
  across all three without three separate lookups, this widget needed zero new schema.
- **Judgment call, documented rather than silently decided**: spec asks for widget visibility to be
  "governed by the same data-group permission model...not hardcoded by role," which the Action
  Summary widget genuinely is (each line item's existence is a real permission/data check, not a role
  name). For the five new Admin/HR Admin-only widgets specifically, spec's own bullets explicitly
  parenthesize that exact audience ("Admin/HR Admin only") for three of them; rather than inventing a
  new dedicated data-group to express a restriction the spec already states plainly in role terms,
  this phase used a direct role check (`admin`/`hr_admin`), consistent with existing precedent
  elsewhere in the app (`PolicyService`/`TicketService` both already hardcode an explicit Admin
  bypass alongside their data-group checks). The two additional analytics widgets without an explicit
  spec parenthetical (headcount charts, metrics trend) were extended under the same gate by judgment,
  since organization-wide headcount/turnover data is not meaningfully different in sensitivity from
  the three spec explicitly restricts.
- **Second build-tooling gotcha this phase, same root cause as F8's**: adding a new `.grid-5` rule to
  `resources/css/theme.css` for the dashboard's five-stat-card row had no visible effect in the
  browser until `npm run build` was rerun — confirming the Tailwind-build-staleness lesson from F8
  applies to any change to the compiled CSS bundle, not only new Tailwind utility classes.
- **One real bug caught during browser verification**: the new weekly-clocked-hours stat card and its
  matching quick action were gated on `canView('attendance')`, a screen key that doesn't actually
  exist — the "My Attendance" route is itself gated on the `timesheets` screen permission (see
  `routes/web.php`), so the card silently never appeared for anyone. Caught by comparing a supervisor
  demo user's visible nav links against what the new stat row showed; fixed by gating on `timesheets`
  to match the route's own middleware.

### Verified this session (F2)

`vendor/bin/pint`, `php artisan test`, `migrate:fresh --seed --force`, and the SQLite parity check
all passed. Verified live in the browser across three demo users: as Admin, all five new/extended
widgets render with real seeded-plus-tinker data (headcount bars, a 6-month HR-metrics trend, a
hand-verified eNPS score of 33 from 6 responses, an open-ticket count); as a supervisor (Emeka), the
Action Summary and nav badge both showed exactly one pending leave request, and approving it made
both drop to zero — the nav badge specifically confirmed live via `wire:poll` without a page reload;
as an ordinary ESS/line-manager user (Adaeze), the Admin-only widgets correctly did not render at
all, while her own Action Summary and Approvals badge stayed in sync with each other. The "who's out
today" scope setting was toggled live in Settings and confirmed to persist.

One commit, pushed with CI green: `5ae2b2a` (F2).

### F5 — Help & Support Integration

The smallest module in Phase 5 — spec's own text for it is a single paragraph — implemented exactly
to that scope rather than expanded: a thin, swappable layer providing an in-app Help link and
per-screen contextual deep links into an external help center, deliberately not a built-in knowledge
base.

- **`HelpProviderInterface`** (`app/Services/Help/`) is the whole extension point: `isConfigured()`
  and `urlFor(?string $tag)`. `AppServiceProvider::register()` binds it to one concrete
  implementation — spec's "the destination can be swapped...without touching calling code" made
  literal: swapping providers later means changing one `bind()` call, nothing that calls the
  interface.
- **`ZendeskHelpProvider`** — spec's "reference implementation for a common third-party help-desk/
  knowledge-base platform," modeled on Zendesk Help Center's public URL scheme (a free-text search
  page keyed by a query string) specifically because that needs only an Admin-configured base URL,
  no API key or authenticated call, to resolve a working destination in an environment with no real
  Zendesk account to integrate against.
- **Per-screen contextual mapping**: every `Screen` row got an optional `help_tag` (seeded in
  `RbacSeeder`, one per existing screen); the topbar's Help link resolves the *current* screen not
  from the route name (which doesn't always match a screen key one-to-one — `employees.create` vs.
  screen key `employees`) but from the route's own `screen:<key>` middleware, the same value every
  route already declares for access control (`EnsureScreenAccess`) — reusing an existing source of
  truth rather than inventing a second route→screen mapping that could drift out of sync with the
  first.
- **Graceful fallback, literally two layers of it**: a screen with no tag (or a lookup that resolves
  to nothing specific) returns the provider's default help-center landing page rather than a broken
  search; and if the provider isn't configured at all (`help_provider_base_url` unset or invalid),
  the Help icon doesn't render in the topbar at all — spec's "URL validation before the help link is
  shown at all" enforced at both the read side (`isConfigured()`) and the write side (Settings-page
  form validation, so an Admin gets an inline error rather than silently saving a dead URL).
- No admin UI was built for editing the per-screen tag mapping itself — screens are a fixed, seeded
  catalog in this app (nav_group/sort_order aren't Admin-editable either), so `help_tag` follows the
  same precedent; only the provider's base URL is Admin-configurable, in a new Settings "Help &
  Support" section following the existing form-validation-then-save pattern.

### Verified this session (F5)

`vendor/bin/pint`, `php artisan test`, `migrate:fresh --seed --force`, and the SQLite parity check
all passed. Verified via tinker that `isConfigured()` correctly rejects an empty and a malformed URL
before accepting a valid one, and that `urlFor()` builds the right tagged-search and default URLs.
Verified live in the browser: the Help icon is absent with no URL configured; entering an invalid URL
in Settings shows an inline validation error and does not save; entering a valid URL makes the icon
appear immediately; clicking through on `/leave/apply` opens the exact tagged search URL
(`.../search?query=leave-management`); and `/employees/create` — whose route name doesn't match its
`employees` screen key — still correctly resolves to `employee-records`, confirming the
middleware-based screen resolution (not route-name matching) works as designed.

One commit, pushed with CI green: `88c2f79` (F5).

### F6 — Mobile API Coverage

The largest module in Phase 5 by scope, and the last one. Spec confirms explicitly that no
installable PWA or native app ships — "mobile access today means 'use the responsive web app from a
phone's browser'" — so this module is entirely about API completeness, not any new UI.

**Scope decision, made deliberately and documented rather than assumed:** F6's overview sentence
("every module in Domains B–F exposes the same REST API surface...not a curated subset") reads, in
isolation, as a demand for full API parity with every admin/management screen in the entire system.
But spec's own measurable acceptance criterion (Section 5, Non-Functional Requirements) sets a
narrower, concrete bar: "a mobile experience at minimum equivalent to the reference system's
self-service leave/time/attendance actions (Section F6)." Read together with F6's own "Key features"
bullet — which names a specific, bounded list: leave application/approval, expense claims,
attendance/time clock, asset/vehicle self-view, policy acknowledgement, helpdesk tickets, corporate
directory, notifications, and pulse surveys — the self-service/ESS quick-action surface (spec 3.4:
"Apply for Leave, Submit a Claim, Punch In/Out, Raise a Ticket for ESS; approval queues for
Supervisor/HR") is the actual definitive scope, not a rebuild of every admin screen as a second API
surface. Full parity with Recruitment/Performance-review-management/Discipline/Company-Registration-
Documents/admin-configuration screens was assessed against that bar and scoped out as disproportionate
to a build whose acceptance test is explicitly about self-service actions.

- **Built on the existing Phase 0 scaffold, not a fresh design**: `ApiController`'s success()/failure()
  envelope, `ApiEnvelope`'s shape, Sanctum token auth (standing in for spec's "general-purpose OAuth2
  server," a trade-off already documented on `AuthController` from Phase 0), and
  `LeaveRequestController`'s resource/collection/`model=default|detailed` conventions — spec's own
  "representative slice this Phase 0 service is built and proven against." Every new controller
  follows that exact shape rather than inventing a second one.
- **Reuses the same service layer the web app calls, never a parallel business-logic path**:
  `ExpenseClaimService::submit()`, `TicketService`'s full status/comment/handler-check methods,
  `PolicyService::acknowledge()`/`canAccessFile()`, `PulseSurveyService::respond()`, and
  `WorkflowEngine::apply()` for every approve/reject action — the exact same calls
  `⚡approvals-board.blade.php`, `⚡ticket-manager.blade.php`, `⚡policy-manager.blade.php`, and
  `⚡pulse-survey-responder.blade.php` already make. One exception, called out rather than hidden: the
  Leave/Expense-Claim approve()/reject() domain-column-stamping logic (which approver column gets
  timestamped, the high-value second-approval routing) still lives only on
  `⚡approvals-board.blade.php`'s own methods, not an extracted shared service — `LeaveRequestController`
  and the new `ExpenseClaimController` duplicate that logic deliberately rather than risking a
  refactor of already-proven, shipped approval code for this addition.
- **Authorization is the same data-group scope model, not a parallel API permission system**: every
  list/show endpoint calls `PermissionService::scopeFor()` exactly as `LeaveRequestController` already
  did, so a supervisor's API view of their team's claims is the same `self_subordinates` scope as the
  web Approvals board, and Helpdesk's confidential-category exclusion uses the same
  `TicketService::isHandler()` gate as the web ticket list.
- **Asset/Vehicle self-view is deliberately narrower than the web screens**: spec's own words are
  "asset/vehicle self-view," not "asset/vehicle management" — `AssetController`/`VehicleController`
  always scope to `current_employee_id = caller`, with no manager/all-scope parameter to widen it,
  even for a caller whose web-side data-group scope is `'all'`. A real management API for those
  modules wasn't asked for here.
- **`/menus`**: the same `Screen` registry and `canView()` gate that already drives the web sidebar
  (`layouts/app.blade.php`), grouped and filtered identically, so a mobile client's navigation can
  never drift from the web app's own permission-driven nav. Response also carries a `ready` flag per
  module per spec's "response metadata continues to indicate whether prerequisite configuration
  exists" — though in this codebase both named examples (a leave period, a timesheet period)
  self-initialize on first use (`LeavePeriod::forYear()`, a `Timesheet` row created per week) rather
  than ever staying genuinely unconfigured, so the flag is close to always-true here; the contract
  exists regardless, for a future prerequisite that doesn't self-heal.
- **Two real bugs found and fixed while curl-testing the new surface end-to-end**, both in
  foundational Phase-0 code this module's much larger route count exposed for the first time:
  `ApiEnvelope::success()` ran `array_filter()` over the *entire* envelope, which silently dropped a
  deliberately-null `data` value (e.g. `AttendanceController::current()` returning "no punch
  currently open") and collapsed the whole response to `{}` — indistinguishable from a broken
  response. Fixed to always include `data` unconditionally. Separately, an expired/missing/revoked
  Sanctum token returned Laravel's bare default `{"message": "Unauthenticated."}` rather than the
  app's own error envelope, invisible when only 4 routes needed a token and glaring once 39 did;
  fixed by adding the missing `AuthenticationException` renderer in `bootstrap/app.php` alongside the
  existing Validation/Authorization/NotFound ones.

### Verified this session (F6)

`vendor/bin/pint`, `php artisan test`, `migrate:fresh --seed --force`, and the SQLite parity check all
passed (F6 added zero migrations — a pure API-layer addition). Every one of the 39 routes was
exercised end-to-end via `curl` against the real dev server with real Sanctum tokens across three
demo users: `/menus` returns the correct permission-filtered, module-filtered nav tree; a full
two-stage leave approval (Adaeze submits → Emeka approves as line manager → HR gives final approval)
correctly transitions `pending_manager → pending_hr → approved` and blocks a stale re-approval
attempt with a 403; an expense claim submission succeeds; punch-in/punch-out round-trips correctly
and a double-punch-in is rejected with the same `ValidationException` message the web app shows; a
helpdesk ticket is raised, commented on, and correctly blocked from status changes by its own raiser
(403) but not by HR; a pulse survey is listed, responded to, and correctly disappears from the "open
for me" list with a second response attempt rejected; a policy document is acknowledged and drops out
of `outstanding_only=1`; the directory search and show endpoints return the same projection as the
web Directory; notifications list/read/clear correctly; and logout revokes the token, confirmed by a
following request 401ing in the app's own error envelope shape.

One commit, pushed with CI green: `50e53b3` (F6).

**Phase 5 is now complete against spec Section F's deliverable list** (F1, F2, F3, F4, F5, F6, F8 —
F7 explicitly descoped per spec). Domain F (Engagement, Analytics & Auxiliary Services) is built,
tested, and live. **This also completes every phase in the original roadmap (Phases 0 through 5)** —
see the capstone note at the top of this document.

## Phase 6 — Post-completion hardening (not in the original roadmap)

With all six roadmap phases complete, this phase closes four documented functional gaps flagged in
the Phase 5 "what's outstanding" review, then runs a full security audit of the finished application
and fixes what it finds. Neither of these was a spec-numbered deliverable — they're the natural next
step once "build everything in the spec" is done and "is it actually solid" becomes the question.

### Four functional gaps closed

- **Renewal & Compliance Engine admin UI** (`admin.renewal-configuration`, Admin/HR Admin only): reminder
  tiers and notify-targets were seeded once at Phase 4 and never exposed to a screen — now editable,
  reusing the exact role-or-employee grant pattern already established for Helpdesk handlers and
  Policy access grants. No schema change; every model this screen manages already existed for
  `RenewalReminderEngine::sweep()` to read.
- **2FA enforced on API token issuance**, not just the web session. `Api\AuthController::login()` now
  issues a short-lived, ability-scoped Sanctum token (`2fa:verify` only, 5-minute expiry) to a
  2FA-required user instead of a full-access one; a new `/api/2fa/verify` endpoint (reusing
  `TwoFactorService` exactly as the web flow does — TOTP/email/backup code, trusted-device skip
  carried as a request/response field instead of a cookie) exchanges it for the real token. Every
  other route now requires the `*` ability (new `abilities`/`ability` Sanctum middleware aliases), so
  a pending token genuinely cannot reach anything else meanwhile. API-driven 2FA *enrollment* is a
  deliberate non-goal — a user who hasn't enrolled yet is told to do so via the web app first, rather
  than rebuilding the QR/backup-code UI a second time for API clients.
- **Real password entropy scoring** (`bjeavons/zxcvbn-php` — the project's first new Composer
  dependency), Admin-configurable as a minimum score (0–4, off by default) alongside the existing
  length/character-class rules. Catches a password like `P@ssw0rd1!` that satisfies every
  character-class box while still being one of the first a real attacker tries.
- **Archive-then-delete retention** for audit logs, login/security history, and notifications: a
  daily job (`retention:purge-logs`) writes matching rows to a dated JSON-Lines file on the private
  disk before deleting them, per log type independently. Every window defaults to null ("keep
  forever") — nothing is purged until an Admin explicitly opts a log type in, since this build has no
  legal/compliance sign-off on what the actual windows should be (the user's own framing for this
  work: "since legal/compliance isn't in the scope of this build").

Verified: `vendor/bin/pint`, `php artisan test`, `migrate:fresh --seed --force`, and the SQLite
parity check all passed; the renewal-configuration screen was exercised live in the browser (tier
add, role/employee notify-target add, correctly scoped per renewal type despite three near-identical
repeated sub-forms on one page); the full 2FA-over-API flow was proven end-to-end with a real TOTP
secret via curl — login → `requires_2fa` + pending token → pending token rejected on every other
route (403) → correct/incorrect code accepted/rejected → full token issued → trusted-device token
skips 2FA on a subsequent login; zxcvbn correctly rejected a character-class-compliant-but-common
password and accepted a real passphrase; the retention job was proven to archive-then-delete an
artificially-backdated row while leaving a recent one untouched, with the archive file's content
verified. Commit `2420f0d`, pushed with CI green.

### Security audit and fixes

A full review — dependency audit (`composer audit`: clean), a delegated grep-based pattern sweep
(injection, XSS, mass assignment, hardcoded secrets, dangerous functions, path traversal, CORS/CSRF,
insecure randomness, open redirects, sensitive data in logs), and manual checks (session/cookie
config, security headers, rate limiting, IDOR spot-checks on the new F6 API surface, file-upload
validation) — found several real, exploitable issues beyond the four gaps above. All fixed this
session:

- **CRITICAL — sensitive files were world-readable with no authentication.** Every media collection
  except Policy/Company Documents (E4/E5, built with this in mind from the start) defaulted to
  MediaLibrary's public disk. Confirmed exploitable with a single unauthenticated `curl` request
  against the `public/storage` symlink — no login, no permission check, no audit trail. Affected:
  Employee documents, Disciplinary Case/Response attachments, Candidate CVs, Expense receipts,
  Interview attachments, Signature evidence, Training certificates, Vacancy/JobTitle attachments.
  Fixed by moving all nine to the private disk and building one shared `PrivateMediaController`
  (`/files/{media}`) that resolves the owning model and delegates to that domain's own existing
  visibility rule (the same data-group scope its screen/service already enforces — e.g. Employee
  documents reuse `employee_personal_details` scope, Disciplinary Case attachments mirror
  `⚡discipline-manager.blade.php`'s own visibility query exactly) rather than inventing a parallel
  permission model. Employee avatars, the company logo, and Buzz photos were deliberately left public
  — low sensitivity, and (for the logo) shown pre-login by design; Buzz specifically is a documented,
  lower-priority residual (see below), not an oversight.
- **CRITICAL — password reset codes never expired, and nothing was rate-limited.** A Carbon 3
  `diffInMinutes()` sign quirk (`later->diffInMinutes(earlier)` returns negative, not positive) meant
  `now()->diffInMinutes($record->created_at) > 30` was never once true — proven by direct
  reproduction, not just reading the code. Combined with zero rate limiting anywhere in the
  application (confirmed by grep: no `RateLimiter::for()`, no `throttle:` middleware on any route),
  a 6-digit reset code was brute-forceable at leisure. Fixed the expiry check
  (`addMinutes(30)->isPast()`, no direction-of-comparison ambiguity) and added rate limiting to
  login, 2FA verification, and password-reset request/verify — both web (a new
  `App\Traits\ThrottlesAttempts`, since these run as Livewire actions with no routable path of their
  own for `throttle:` middleware to gate — the same reasoning Laravel's own controller-oriented
  `ThrottlesLogins` trait exists for) and API (plain `throttle:5,1` route middleware, now that real
  routes exist for it post-F6).
- **MEDIUM — a pulse survey response had no server-side audience check.** The web/API listing
  endpoints only ever show runs the caller is actually targeted by, but
  `PulseSurveyService::respond()` itself never re-checked that — any employee who knew (or guessed) a
  department-scoped run's id could respond to it directly via the API, polluting that department's
  aggregate with an outsider's score. Fixed at the service layer so both paths inherit the fix.
- **MEDIUM — a GDPR purge left PII in the audit trail.** `Employee::gdprPurge()` erased PII from the
  live record but every prior `audit_logs` row for that employee still held the same values in plain
  text (the audit trail's whole point, for an ordinary edit) — including the very "updated" row the
  purge's own `forceFill()->save()` had just written. A genuine right-to-erasure purge has to scrub
  those too. New `AuditLog::redactHistoryFor()` does this, called from `gdprPurge()`.
- Six upload forms accepted any file type (`'file', 'max:10240'` with no `mimes:` rule) — a stored-XSS
  vector, since an uploaded `.html`/`.svg` would later be served from the app's own origin. Added
  appropriate `mimes:` allowlists to each.
- TOTP codes could be replayed within their ~90-second validity window (`verifyKey()` accepts the
  same code more than once). Switched to Google2FA's `verifyKeyNewer()` (new
  `users.two_factor_last_totp_timestamp` column) so a captured code can't be reused even while still
  numerically valid.
- Sanctum API tokens never expired once issued. Published `config/sanctum.php` and set a 30-day
  default (matching this app's own trusted-device window), overridable via env.
- The demo seeder (`HrisDemoSeeder` — known-password Admin/HR Admin accounts) ran unconditionally.
  Guarded to `local`/`testing` environments only in `DatabaseSeeder`.
- Four standard defense-in-depth response headers (`X-Content-Type-Options`, `X-Frame-Options`,
  `Referrer-Policy`, `Permissions-Policy`) added app-wide via new middleware. A real
  Content-Security-Policy was deliberately left out — this app relies on inline `style="..."`
  attributes and Livewire's own inline hydration `<script>` payloads throughout, and a CSP strict
  enough to matter would need a proper nonce/hash rollout and real regression testing to add without
  breaking pages, not a same-pass addition.

**Two items initially left as documented trade-offs were closed in a follow-up pass, on request**
(commit `28349d9`): Buzz/Post photos now go through the same private-disk + `PrivateMediaController`
path as the other nine collections, gated on `canView('buzz')` — matching spec F1's own "no other
audience/visibility scoping (company-wide by design)" rule exactly, just no longer reachable by an
unauthenticated outsider via the old public storage URL. This one needed slightly more care than the
other nine: the feed renders photos inline via `<img src>`, not a download `<a href>` link, so the fix
had to be confirmed to actually serve real image bytes for inline display (verified with a real 1×1
PNG rendering correctly in the feed), not just gate a click-through download. The public,
unauthenticated careers/job-application form is now rate-limited too (10 applications/hour, using the
same `ThrottlesAttempts` trait, keyed by IP alone rather than the submitted email — which an abuser
fully controls and would just rotate — since this is a pure anti-spam measure, not an
identity-distinguishing one like the auth endpoints).

**Two items remain genuinely left as-is, not fixed**: CORS allows all origins on `api/*` but
`supports_credentials` is `false`, which is the safe combination for a Bearer-token-only API (no
cookie-riding risk) and was left as Laravel's default; `User::$fillable` includes `role_id` with no
current code path that mass-assigns it from request data, so no active exploit exists, but it's worth
a guard if a future profile-update form ever calls `$user->update($validated)` directly.

**One genuinely unresolved oddity, documented in code rather than silently worked around**: while
building the audit-redaction fix, iterating `AuditLog` rows inside a closure or method whose `$this`
was bound to an `Employee` instance made every `$log->changes` access (a plain Eloquent `array` cast)
return an empty array, while `getRawOriginal('changes')` held the correct JSON the entire time —
reproduced consistently when called from `Employee::gdprPurge()`'s actual call chain, but not
reproducible in an isolated standalone test with the identical code. Not root-caused. Worked around
by having `AuditLog::redactHistoryFor()` read/write the raw column value directly
(`json_decode`/`json_encode` by hand) instead of relying on the cast, which sidesteps it regardless of
underlying cause — documented in that method's own comment in case it recurs elsewhere in this
codebase.

Verified: `vendor/bin/pint`, `php artisan test`, `migrate:fresh --seed --force`, and the SQLite parity
check all passed. The file-access fix was proven end-to-end — a real upload confirmed on the private
disk (not `public/`), the old public URL confirmed no longer serving it, the new route confirmed
redirecting an unauthenticated request to login, and (since the demo dataset's only four login-capable
users each legitimately have some access path to any given record) the authorization matrix's
`self`/`self_subordinates`/`all`/`none` branches were each proven directly via a scoped unit
invocation rather than skipped for lack of a convenient "should be blocked" demo account. Rate
limiting was proven live in the browser (5 failed logins, 6th shows "Too many attempts," a different
email logs in immediately, confirming per-identifier not global scoping) and via curl for the API
(HTTP 429 after 5 attempts, rendered in the standard error envelope). TOTP replay protection and the
password-reset expiry fix were both proven with direct reproductions of the original bug alongside
the fix. Commit `a76254c`, pushed with CI green.

The Buzz-photo fix was verified with a real upload (a genuine 1×1 PNG, not `UploadedFile::fake()` —
see `project_uploadedfile_fake_zero_bytes.md`) rendering correctly inline in the feed for a logged-in
employee, and a direct `curl` against the new route confirming an unauthenticated request still
redirects to login. The careers-apply rate limit was verified live in the browser: nine attempts
pre-seeded via the rate limiter's own cache keys (matched to the browser's actual `::1` request IP,
not an assumed `127.0.0.1` — the two don't share a bucket, a real distinction the first verification
attempt caught), the 10th real browser submission succeeding normally, and the 11th correctly
rejected with a clear retry-after message while leaving the form's entered data in place. Commit
`28349d9`, pushed with CI green.

### Technical specification document updated

`HRIS_Roadmap_and_Technical_Specification.docx` — the source spec this whole build was written
against — has been updated to v9.0: the title-page revision line now states the build is complete,
and a new Section 10 ("Implementation Status & Post-Completion Hardening") summarizes the build
completion (all six phases, Domains A–F) and this phase's two hardening passes, pointing back to this
file for full detail rather than duplicating it. A backup of the prior version was kept
(`HRIS_Roadmap_and_Technical_Specification.v8.2-backup.docx`, alongside the existing `.v6-backup.docx`)
before editing, matching this project's own versioning convention. Edited programmatically
(python-docx) rather than via a text round-trip, to preserve the original document's styling; verified
by converting the result to PDF and visually confirming both the title-page revision note and the new
section render correctly with matching heading/bullet formatting.

## Phase 7 — Post-launch requests

Four small, discrete requests handled after the Phase 6 hardening pass and its documentation update,
each committed and pushed separately.

### Sign-in/verify illustrations restored, demo credentials removed

The login and 2FA-verify screens had drifted from the original UI/UX artifact (`Main.dc.html`,
`TwoFactor.dc.html`): both illustration slots held a simplified placeholder icon instead of the
artifact's org-chart-with-pulse and laptop/phone/shield graphics. Restored the exact SVG markup from
the artifact files into `⚡login.blade.php` and `⚡login-verify.blade.php`. Separately, the login
form's "Demo logins (password `password`): admin@…, hr@…, emeka@…, adaeze@…" block — a onboarding/dev
convenience with no place on a screen anyone could reach — was removed from `⚡login-form.blade.php`.
Verified visually in the browser against the two reference images. Commit `f285134`.

### Google Workspace and Microsoft 365 SSO

The Section A3 SSO button had been a stub since Phase 1 (config fields that were never read, a login
button that just showed a JS alert) — see the A3 section earlier in this file. Replaced it with real
OAuth login via Laravel Socialite: Google is a Socialite core driver; Microsoft 365 (Entra ID v2) uses
`socialiteproviders/microsoft`, registered via an `Event::listen(SocialiteWasCalled::class, ...)` call
in `AppServiceProvider::boot()` since Socialite doesn't ship it directly. A new `SsoController` handles
`/auth/{provider}/redirect` and `/auth/{provider}/callback` (both `guest`-gated, provider constrained
to `google`/`microsoft` at the route and controller level).

The old generic `sso_enabled`/`sso_provider`/`sso_client_id`/`sso_client_secret`/`sso_endpoint`/
`sso_domain` columns (added in Phase 1, never wired to anything) were dropped and replaced with
per-provider columns — `sso_google_*` and `sso_microsoft_*` — so both providers can be configured and
enabled independently. Client secrets are encrypted at rest and stored in the `settings` table, not
`.env` — the same pattern already used for SMTP credentials — so an admin can enable/disable a
provider or rotate a secret from the Settings screen with no redeploy. Each provider's Settings card
shows the exact redirect URI to register with that IdP. The sign-in page only renders a provider's
button once it's both enabled and has credentials saved (`Setting::googleSsoReady()` /
`microsoftSsoReady()`).

Deliberately does not auto-provision accounts: a successful IdP sign-in is matched against an
*existing* `User` by email (case-insensitive); no match means the same rejection UX as a wrong
password, with a `sso_login_rejected` `SecurityEvent` recorded. A successful SSO login also marks the
session `two_factor_verified` — the identity provider is itself the strong-auth factor, the same
reasoning already applied to a trusted-device cookie. Google additionally supports an optional
Workspace-domain restriction (`hd` parameter as a UX hint on Google's own account chooser, plus a
real server-side domain check on the returned email — the actual access control); Microsoft supports
an optional tenant restriction, defaulting to `organizations` (any work/school tenant, excluding
personal Microsoft accounts) when no specific tenant ID is set.

**Bug found and fixed in the same pass**: `EnsurePasswordPolicyMet` forces a "type your current
password" change whenever the org's password policy has tightened since an account was created —
correct for password-based accounts, but a genuine lockout for a user who signs in only via SSO and
may never have known or used a local password (the `users.password` column is `NOT NULL`, so every
account has *some* hash, just not necessarily one its owner knows). Fixed by having the SSO callback
also flag the session `sso_authenticated`, and having the middleware skip its redirect when that flag
is present.

Verified via `tests/Feature/SsoLoginTest.php` (7 tests: unconfigured-provider 404, unknown-provider
404, no-matching-account rejection, successful login with session flags, Google Workspace domain
restriction, and the password-policy exemption both with and without the flag — the latter two as
direct middleware unit tests rather than full HTTP round-trips, since going through the real
`screen:home` RBAC gate would need a seeded Role/Screen the test isn't otherwise exercising) plus a
live browser round-trip against the real `accounts.google.com` (a saved test Client ID correctly
produced Google's own "OAuth client was not found" error, confirming the redirect and config wiring
end-to-end without needing a real registered app). `composer audit` found no advisories in the two new
dependencies. Commit `c99f83f` (feature), `61a3d09` (the lockout fix + tests).

### Notification bell: unread count badge

Requested by a peer Claude session working on the SI PIM portal, so both apps present the same way —
PIM's bell already showed an unread count instead of a dot. Changed `⚡notification-bell.blade.php`
and the matching `theme.css` rules: the dot became a count badge capped at "9+"; the bell's
`aria-label` states the unread count; the panel header shows "Notifications · N unread" and only
shows "Mark all read"/"Clear all" when there's something to act on; unread rows gained a small dot
before the title; and the panel pins to the viewport on screens ≤1024px instead of risking overflow.
Added `tests/Feature/NotificationBellTest.php` (3 tests: badge count, "9+" cap, badge disappearing
after mark-all-read). Checked with the user before pushing, per the requesting session's own ask, and
confirmed back to that session once live. Commit `a5dff34`.

### Full-codebase review

Requested directly: re-reviewed every file touched since the Phase 6 hardening commit (`28349d9`) for
regressions and security issues — `SsoController`, `Setting`, `AppServiceProvider`, the login/settings
Livewire components, `theme.css`, and the new migration — rather than repeating the full Phase 6 audit
from scratch. Found and fixed the SSO/password-policy lockout above; otherwise: `composer audit` clean,
a from-scratch `migrate:fresh` against a throwaway SQLite file to confirm CI parity (CI runs on SQLite,
not this project's MariaDB dev database), `vendor/bin/pint` clean, and the full test suite (12 tests
across both new files plus the pre-existing two) green. No stray references to the dropped generic SSO
columns or the old unread-dot CSS class remained anywhere in the codebase.

## Phase 8 — PIM/HRIS security & usability alignment

Requested by the SI PIM session, citing its own `SI_PIM_HRIS_Production_Integration_Proposal.md` (§5)
and `SI_PIM_Spec_Delta_2026-09-25.md` (§62–§67): bring HRIS's auth/session hardening and one UX pattern
up to parity with PIM's own, more mature implementation, adapted to HRIS's Volt/Livewire structure
rather than ported line-for-line from PIM's Filament codebase. Two scope questions (the faceted-filter
rollout, and whether first-time TOTP setup should gate on an emailed code) were put to the user before
starting; a third item (a shared DD/MM/YYYY date convention) was explicitly deferred, per the requesting
session's own instruction, pending the user's separate confirmation.

### 1 — Per-account lockout

New `AccountLockoutService`: 5 wrong passwords locks the *account* (`users.failed_login_attempts`,
`users.locked_until`) for 15 minutes, independent of the existing per-(email, IP) `ThrottlesAttempts`
throttle — a lock an attacker can't defeat by rotating source IPs. The web login form and the API login
endpoint both go through this one service (there's no separate admin-panel sign-in surface in HRIS to
also wire up, unlike PIM's Filament admin). A locked account rejects even its correct password, logging
`login_failed` (reason: locked) and `account_locked` as `SecurityEvent`s. Clears on the next successful
password check anywhere a password is verified — sign-in, self-service change, or a forgot-password
reset (see item 12).

### 2 — Admin-configurable idle session timeout

New `settings.idle_session_timeout_minutes` (Settings → Security), applied to `config('session.lifetime')`
in `AppServiceProvider::boot()` (skipped for console commands, so `artisan migrate` on a database that
doesn't have the `settings` table yet can't crash on it). A new `<x-idle-session-timeout>` component
warns 10 seconds before that timeout with a "Stay signed in" button hitting a new `/session/keep-alive`
route (`auth` middleware only — the fact that an authenticated request reached it is itself the
keep-alive, since Laravel's session middleware already refreshes last-activity on any request). Built
with plain DOM APIs rather than an Alpine-templated element: Livewire's root-element check (debug mode
only) strips `<script>` tags before counting a page's direct `<body>` children, so a persistent `<div>`
sibling here tripped "multiple root elements" for whichever full-page component the layout wraps — the
same reason `⚡push-notifications.blade.php` is script-only. Resets only on real input events
(mousemove/keydown/scroll/touchstart/click), deliberately not on the notification bell's `wire:poll`
background activity.

### 3 — Forced HTTPS + HSTS

`URL::forceScheme('https')` outside local/testing (`AppServiceProvider::boot()`); `Strict-Transport-
Security: max-age=31536000; includeSubDomains` added to `AddSecurityHeaders`, sent only when the request
is already secure, so this dev box's plain-HTTP Apache setup is untouched.

### 4 — Content-Security-Policy, report-only

New `config/security.php` (`CSP_MODE=report-only|enforce|off`, env-driven rather than admin-UI-driven —
a deployment/ops concern, unlike item 2's timeout) and `AddContentSecurityPolicy` middleware. `report-uri`
points at a new unauthenticated `POST /api/csp-report` (outside any session/CSRF — it sits in the `api`
group, not `web`), throttled 30/minute, logging a trimmed summary (`CspReportController`) rather than
the full report body. `fonts.googleapis.com`/`fonts.gstatic.com` are HRIS's one external resource host
(`⚡layouts/guest.blade.php` and `⚡layouts/app.blade.php`'s Google Fonts `<link>`) — unlike PIM, HRIS has
no external avatar service to account for; every avatar here is already drawn locally as initials.
Browsed the login, home, settings, and employee-list screens in report-only mode afterward and found
zero violations in the log; a literal click-through of every screen in the app wasn't attempted.

### 5 — Collapsible faceted filters (Employee list)

Scoped to the Employee list only, per the user's choice. `⚡employee-list.blade.php`'s job title/sub-
unit/supervisor/location filters became multi-select checkbox facets behind a panel that starts
collapsed, with a "Show filters (N)" toggle and a one-line summary while collapsed. Each facet's count
is computed from every *other* active filter (`filteredQuery($excluding)` builds the same query minus
one facet, then groups by it) — the standard faceted-search behaviour, not the raw unfiltered total.
"Clear all" resets every filter including the status toggle. The open/closed state lives on the
component and is persisted to the session (`employee-list-filters-open`) rather than held in Alpine
alone, so it survives a full page reload — verified live in the browser, along with the count
recomputation and result filtering.

### 6 — Per-account 2FA attempt limit

`TwoFactorService` gained `tooManyVerifyAttempts()`/`recordVerifyFailure()`/`clearVerifyAttempts()`,
keyed purely by user ID (`two-factor-verify:{id}`) via the `RateLimiter` facade directly — deliberately
bypassing `ThrottlesAttempts`' own IP-inclusive key, since keying by IP is exactly what would let an
attacker defeat this limit by rotating source addresses. 5 wrong codes of any method (TOTP, backup,
email) pauses verification for that account, web and API, for 15 minutes; it doesn't reset on a fresh
password sign-in, since the key never included anything password-related to begin with. Reused for item
7's email gate too.

### 7 — Emailed code before first-time TOTP setup

Per the user's go-ahead. `⚡two-factor-setup.blade.php`'s `chooseTotp()` no longer generates a secret
immediately — it sends an emailed code first (new `totp_email_gate` step) and only reveals the QR after
`confirmTotpEmailGate()` verifies it, using item 6's same per-account throttle. This screen is reached
only at an account's first TOTP enrollment or when re-enrolling after an Admin 2FA reset (`mount()`'s own
`needsReEnrollment()` check), so no extra conditional was needed to scope the gate to those two cases —
it's already exactly what this component handles. A voluntary method switch from an already-2FA-verified
session (`⚡account-security.blade.php`) isn't gated the same way, since the threat this closes (a stolen
password alone producing a scannable QR) doesn't apply to a session that already passed 2FA. Verified
live end-to-end: wrong code stays gated, correct code reveals the real QR, and a real TOTP code against
that QR's secret completes enrollment through to the backup-codes screen.

**Bug found in the same pass**: `confirmTotp()`/`confirmTotpSwitch()` in both this component and
`⚡account-security.blade.php` read `$this->secret` — a public Livewire property, not a fresh server
value — when confirming enrollment. Unlocked, a tampered request could substitute a secret the account
owner never saw on the QR. Fixed by adding `#[Locked]` to `$secret`, `$qrDataUri`, `$backupCodes`, and
`$allowedMethods` in both components, plus `$step` (`two-factor-setup`) and `$method`/`$useBackupCode`
(`⚡two-factor-verify.blade.php`), which are server-decided and never meant to be client-writable either.
A full sweep of every Livewire component's public properties for the same class of issue was not
attempted — only the auth-critical ones surfaced by this review were hardened.

### 8 — Push-endpoint allowlist (SSRF)

New `PushEndpointValidator`: `NotificationService::sendPush()` POSTs to whatever endpoint a subscription
stores, so accepting any `https://` URL let a signed-in user aim that server-side request at an internal
address. Restricted to the known browser push services (`fcm.googleapis.com`, `android.googleapis.com`,
`updates.push.services.mozilla.com`, `web.push.apple.com`, and the `.push.services.mozilla.com`/
`.notify.windows.com`/`.push.apple.com` suffixes), checked both in `PushSubscriptionController::store()`
and again in `NotificationService::sendPush()` before ever queuing a send.

### 9 — Remember-me

`config/auth.php`'s `web` guard gained `'remember' => 60 * 24 * 30` (30 days; Laravel's own default is
5 years). A role that may not use trusted devices (`Role::two_factor_trusted_device_allowed`) never gets
the remember-me cookie in the first place — `⚡login-form.blade.php` looks the submitted email's user up
before calling `Auth::attempt()` and suppresses `$remember` for that role, reusing the exact same role
flag `TwoFactorService::trustedDeviceAllowedFor()` already checks elsewhere.

### 10 — Password-reset code limits (confirmed, not changed)

`⚡forgot-password-form.blade.php` already matched PIM's stated requirements exactly: 3 requests per 10
minutes (counted whether or not the address exists), 5 wrong tries per code, and a 30-minute expiry
(fixed earlier this project, see the Carbon `diffInMinutes` sign bug in Phase 6). No change needed.

### 11 — Equal sign-in timing

The web login already gets this for free: `Auth::attempt()` runs inside Laravel's own `Timebox`
(`auth.timebox_duration`, 200ms default), which pads the response to a minimum duration regardless of
whether a user was found. `Api\AuthController::login()` uses `Auth::once()` instead, which skips that
wrapper entirely — a genuine, API-specific gap. Fixed by burning an equivalent `Hash::check()` against a
precomputed dummy hash when the submitted email doesn't match any account, before ever calling
`Auth::once()`.

### 12 — Revoke on password change

New `User::setOwnPassword()` — the one place either `⚡change-password.blade.php` (self-service and the
forced-policy-change flow) or `⚡forgot-password-form.blade.php`'s reset now sets a password — deletes
every Sanctum token and trusted device for that user (a `tokens_and_devices_revoked` `SecurityEvent` if
either existed), on the basis that any of them may have been issued to whoever knew the old password.
`Illuminate\Session\Middleware\AuthenticateSession` was added to the `web` middleware group to handle
the user's *other browser sessions*: it compares each session's stored password hash against the
current one on every request and signs out a stale one — the session performing the change itself is
unaffected, since Laravel refreshes that session's stored hash at the end of the same request. Also
clears the item-1 lockout, matching PIM's `NewPasswordController` behaviour.

### 13 — Smaller hardening

Applied: `config/filesystems.php`'s `local` disk (the one `PrivateMediaController` gates) had Laravel
12's default `'serve' => true`, exposing an unused `/storage/{path}` route that needs a signed URL HRIS
never generates for it — set to `false`. The `#[Locked]` fixes from item 7 above. **Confirmed already
safe, no change needed**: the one-time backup-codes screen (`login.setup`) sits outside the
`password_policy` middleware entirely (see Phase 6/7's own routing notes), so a mid-enrollment policy
bump can't strand a user there. **Not applicable — no existing feature to extend**: HRIS has no
password-re-confirmation ("confirm it's you") flow of any kind on any screen, and no `redirect()->
intended()` mechanism either, so the two sub-items assuming one already existed (extending it to
delete/bulk actions; validating its return URL) don't have anything to act on here. Building either from
scratch was judged a larger feature than a hardening pass, not attempted without a separate discussion.

### Own follow-up — SSO-only accounts and password re-confirmation

Requested alongside the above: when fixing the SSO/password-policy lockout in Phase 7, also exempt
SSO-only accounts from password re-confirmation and expiry, and prefer a fresh SSO sign-in wherever
re-confirmation would otherwise be asked. HRIS has neither a password-expiry policy nor a password-
re-confirmation flow (see item 13 above) for the SSO fix to interact with beyond the forced-password-
change screen Phase 7 already exempted — there is nothing further to change here today; the exemption
already covers everything HRIS actually has.

### Verification

`tests/Feature/AccountLockoutTest.php` (5), `tests/Feature/TwoFactorSetupEmailGateTest.php` (3),
`tests/Feature/EmployeeListFacetedFiltersTest.php` (4), and `tests/Unit/PushEndpointValidatorTest.php`
(3) were added — 15 new tests, full suite 27/27 green. `vendor/bin/pint` clean. A from-scratch
`migrate:fresh` against a throwaway SQLite file confirmed CI parity for the new migration. `composer
audit` stayed clean (no new dependencies this phase). Account lockout, the TOTP email gate through to a
completed enrollment, faceted-filter counts/persistence, and the CSP report-only header were all also
verified live in the browser, not just via the automated suite.

### 14 — Shared date convention (unblocked mid-Phase-8)

Held back initially pending the user's confirmation; unblocked partway through this phase once the SI
PIM session relayed it. New `App\Support\Dates` (`DATE = 'd/m/Y'`, `DATE_TIME = 'd/m/Y H:i'`, `LONG =
'j M Y'`) mirrors PIM's own `Dates.php` constants without mass-rewriting every existing `'j M Y'` call
site that was already correct for its context — only sites that needed to actually change were touched.
Classified each of the ~50 existing date-format call sites by what it actually renders, not just its
format string: genuine table/list columns (the Employee list's Hired column, an audit-log/signature-
verification timestamp column, a `<td>` in Goals) and history/activity-log rows (candidate application
history, attendance punch records, company-document and employee audit trails, a timesheet's action
log) moved to `Dates::DATE`/`DATE_TIME`; one-off descriptive sentences about a single record ("expires
on…", "Signed by X on…", "Trusted…", "This record was purged on…") were deliberately left in the long
"28 Sep 2026" style, matching the confirmed rule's own "sentences where space allows" wording. The
Employee CSV/PDF export (`EmployeeReportService::rowValue()`) moved from raw ISO to `Dates::DATE`. Four
remaining 12-hour (`g:ia`) timestamps were fixed to 24-hour regardless of which style they otherwise
kept. The home page's weekday greeting and a chart tooltip's month-only label were aligned to the
confirmed exact examples ("Monday, 28 Sep 2026"; full month name for a month-only period). Added hover
titles with the exact date/time to four relative-time ("`5 minutes ago`") displays (notification bell,
Buzz shares, careers listing, trusted-device "last used"), per the rule's own requirement for those.
API JSON resources (already ISO 8601) and native `<input type="date">` bindings (which must stay ISO —
that's the HTML spec's own value-attribute format, unrelated to display) were deliberately left
untouched. A custom DD/MM/YYYY-masked replacement for HRIS's 26 native date inputs — matching PIM's own
`<x-date-input>` — was considered and declined by the user as a separate, larger feature; native pickers
already display per the browser's own locale and their stored value is unaffected either way.

**Unrelated but serious, found and fixed in the same pass**: discovered the real dev MariaDB database's
core tables (`users`, `employees`, `roles`, etc.) had been dropped and recreated empty at some point
earlier in this session — MySQL's own table metadata showed the exact timestamp, though this instance
has neither a general query log nor binlog enabled to identify which command caused it; the leading
theory is an earlier CI-parity `migrate:fresh` check against a throwaway SQLite file losing its
`DB_CONNECTION` override. Flagged to the user immediately rather than silently reseeding. Restored via
`php artisan db:seed`, which also surfaced (and fixed) a genuine pre-existing bug in `HrisDemoSeeder`:
its `Setting::create(['id' => 1, ...])` call wasn't idempotent like the rest of the seeder's own
lookups, and failed on a unique-constraint violation against the settings singleton `Setting::current()`
had already recreated elsewhere — changed to `updateOrCreate`, matching the pattern the seeder already
uses for its job title/sub-unit/location lookups.

Verified live in the browser after reseeding: the Employee list's Hired column, the Audit Log's both
tabs, and the home page's weekday greeting all render in the confirmed convention. Full test suite
(27/27) and `vendor/bin/pint` stayed green throughout.

### 15 — `upload()` name collision with Livewire's `$wire.upload()` (from SI PIM)

Flagged as an optional cross-check by the SI PIM session, which had hit the identical bug in its own
codebase: a Livewire action named exactly `upload` (or any of `$wire`'s other built-in helper names —
`uploadMultiple`, `set`, `get`, `call`, `dispatch`, etc.) is shadowed by that built-in in the browser, so
`wire:submit`/`wire:click` bound to it silently calls Livewire's own JS helper instead of the PHP
method. A grep across every Volt (`⚡`) component turned up two real hits — not just the name pattern, but
confirmed broken live in the browser first: `⚡employee-attachments-tab.blade.php`'s document-upload form
(`wire:submit="upload"`) and `⚡vacancies-manager.blade.php`'s attachment upload (`wire:click="upload"`).
Clicking "Upload" in either produced zero server requests and a JS `TypeError: Cannot read properties of
undefined (reading 'name')` inside Livewire's own compiled JS — confirmed, not theoretical. Checked with
the user before changing anything, per the requesting session's own instruction. Fixed by renaming to
`uploadDocument()`/`uploadAttachment()` respectively and updating the matching `wire:submit`/`wire:click`
attribute — no behavior change otherwise. Re-verified live in the browser afterward (a validation error
now correctly appears when submitting with no file selected, proving the PHP action is reached). A
repository-wide scan for the second bug PIM reported — a public method and public property sharing a
name on the same component — found no matches in HRIS.
