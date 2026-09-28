<?php

namespace Database\Seeders;

use App\Models\DataGroup;
use App\Models\ModuleToggle;
use App\Models\Role;
use App\Models\Screen;
use Illuminate\Database\Seeder;

/**
 * Seeds the concrete role catalog, screen registry, data-group registry, and
 * the Role Permission Matrix exactly as specified in spec Section A2's table
 * — five system roles (four assignable, one situational), each a plain row
 * plus matrix entries, no hard-coded role-name logic anywhere downstream.
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Screens (one per route this app actually has) ----
        $screens = [
            ['key' => 'home', 'label' => 'Home', 'nav_group' => 'Workspace', 'sort_order' => 1],
            // Spec F1: company-wide feed, "no other audience/visibility
            // scoping (company-wide by design)" — granted at base level for
            // every role; the 'buzz' data group below governs only
            // edit/delete-others moderation rights, never what's visible.
            ['key' => 'buzz', 'label' => 'Buzz', 'nav_group' => 'Workspace', 'sort_order' => 2, 'module_key' => 'buzz'],
            // Spec F4: every employee can raise a ticket for themselves;
            // visibility beyond the raiser is governed by the
            // 'helpdesk_tickets' data group below (plus the per-category
            // confidential handler list for Grievance/Whistleblower).
            ['key' => 'helpdesk', 'label' => 'Helpdesk', 'nav_group' => 'Workspace', 'sort_order' => 3, 'module_key' => 'helpdesk'],
            // Spec F8: every employee participates in pulse surveys; results
            // reporting (with the anonymization threshold) is a separate
            // Admin-only screen below.
            ['key' => 'pulse-surveys', 'label' => 'Pulse Surveys', 'nav_group' => 'Workspace', 'sort_order' => 4, 'module_key' => 'pulse_surveys'],
            ['key' => 'leave.apply', 'label' => 'My Leave', 'nav_group' => 'Workspace', 'sort_order' => 2, 'module_key' => 'leave'],
            ['key' => 'claims.create', 'label' => 'My Claims', 'nav_group' => 'Workspace', 'sort_order' => 3, 'module_key' => 'claims'],
            ['key' => 'timesheets', 'label' => 'My Timesheets', 'nav_group' => 'Workspace', 'sort_order' => 4, 'module_key' => 'timesheets'],
            ['key' => 'performance', 'label' => 'Performance', 'nav_group' => 'Workspace', 'sort_order' => 5, 'module_key' => 'performance'],
            // Spec E2: "an ordinary ESS user sees only assets currently
            // assigned to them" — real self-scoped access via this data
            // group's own scope, not a route-level block, so the screen is
            // granted at base ESS level same as Performance/Profile.
            ['key' => 'assets', 'label' => 'Assets', 'nav_group' => 'Workspace', 'sort_order' => 6, 'module_key' => 'assets'],
            // Spec E3: "same for vehicles" — mirrors the Assets screen's
            // self-only-by-default policy exactly.
            ['key' => 'vehicles', 'label' => 'Vehicles', 'nav_group' => 'Workspace', 'sort_order' => 6, 'module_key' => 'vehicles'],
            ['key' => 'profile', 'label' => 'My Profile', 'nav_group' => 'Workspace', 'sort_order' => 6],
            ['key' => 'approvals', 'label' => 'Approvals', 'nav_group' => 'My Team', 'sort_order' => 6],
            ['key' => 'employees', 'label' => 'Employees', 'nav_group' => 'My Team', 'sort_order' => 7],
            ['key' => 'recruitment', 'label' => 'Recruitment', 'nav_group' => 'My Team', 'sort_order' => 8, 'module_key' => 'recruitment'],
            // Spec D3: "HR Officer has no access to Discipline case data at
            // all" — this screen is simply never granted to that role below,
            // a route-level block stronger than data-group scoping alone.
            ['key' => 'discipline', 'label' => 'Discipline Cases', 'nav_group' => 'My Team', 'sort_order' => 9],
            // Spec F3: "a searchable, browsable company directory" — a pure
            // read-only projection over Employee, open to every role same as
            // Policies below.
            ['key' => 'directory', 'label' => 'Directory', 'nav_group' => 'Company', 'sort_order' => 1, 'module_key' => 'directory'],
            // Spec E4: "every employee can browse and download active
            // policy documents they have access to" — granted at base ESS
            // level, unlike the Admin-only management screen below.
            ['key' => 'policies', 'label' => 'Policies', 'nav_group' => 'Company', 'sort_order' => 2, 'module_key' => 'policies'],
            ['key' => 'admin.projects', 'label' => 'Customers & Projects', 'nav_group' => 'Admin', 'sort_order' => 6],
            ['key' => 'admin.master-data', 'label' => 'Organization & Master Data', 'nav_group' => 'Admin', 'sort_order' => 6],
            ['key' => 'admin.onboarding-templates', 'label' => 'Onboarding/Offboarding Templates', 'nav_group' => 'Admin', 'sort_order' => 7],
            ['key' => 'admin.leave-configuration', 'label' => 'Leave Configuration', 'nav_group' => 'Admin', 'sort_order' => 7],
            ['key' => 'admin.performance-configuration', 'label' => 'Performance Configuration', 'nav_group' => 'Admin', 'sort_order' => 7],
            ['key' => 'admin.claims-management', 'label' => 'Claims Management', 'nav_group' => 'Admin', 'sort_order' => 7],
            ['key' => 'admin.asset-configuration', 'label' => 'Asset Configuration', 'nav_group' => 'Admin', 'sort_order' => 7],
            ['key' => 'admin.vehicle-configuration', 'label' => 'Vehicle Configuration', 'nav_group' => 'Admin', 'sort_order' => 7],
            // Spec E4: "only HR/Admin manage categories, documents, and
            // versions" — a route-level block, same as every other
            // Admin-only configuration screen.
            ['key' => 'admin.policy-configuration', 'label' => 'Policy Configuration', 'nav_group' => 'Admin', 'sort_order' => 7],
            // Spec E5: "restricted by default" — unlike Policies (E4), there
            // is no base-ESS browse screen at all; a specifically-granted
            // employee reaches a file only via its direct download link,
            // never a self-service listing (documented scope trade-off).
            ['key' => 'admin.company-documents', 'label' => 'Company Registration Documents', 'nav_group' => 'Admin', 'sort_order' => 7],
            // Spec F4: "category management is HR/Admin only."
            ['key' => 'admin.helpdesk-configuration', 'label' => 'Helpdesk Configuration', 'nav_group' => 'Admin', 'sort_order' => 7],
            // Spec F8: "an aggregated Results snapshot per run...is what
            // HR/Admin ever sees" — Admin/HR Admin manage templates/runs and
            // view results here; raw responses are never individually
            // exposed anywhere, including this screen.
            ['key' => 'admin.pulse-survey-configuration', 'label' => 'Pulse Survey Configuration', 'nav_group' => 'Admin', 'sort_order' => 7],
            ['key' => 'settings', 'label' => 'Settings', 'nav_group' => 'Admin', 'sort_order' => 8],
            ['key' => 'admin.roles', 'label' => 'Roles & Permissions', 'nav_group' => 'Admin', 'sort_order' => 9],
            ['key' => 'admin.audit-log', 'label' => 'Audit Log', 'nav_group' => 'Admin', 'sort_order' => 10],
            ['key' => 'admin.signatures', 'label' => 'Signature Verification', 'nav_group' => 'Admin', 'sort_order' => 11],
            ['key' => 'admin.health-check', 'label' => 'System Health Check', 'nav_group' => 'Admin', 'sort_order' => 12],
        ];
        foreach ($screens as $s) {
            Screen::updateOrCreate(['key' => $s['key']], $s + ['module_key' => null]);
        }

        // ---- Module toggles (spec B1) — only modules with a screen tagged
        // module_key above actually gate anything yet; the rest are listed
        // for completeness and future screens to tag themselves against.
        foreach ([
            ['key' => 'leave', 'label' => 'Leave Management'],
            ['key' => 'claims', 'label' => 'Expense Claims'],
            ['key' => 'timesheets', 'label' => 'Time & Project Tracking'],
            ['key' => 'recruitment', 'label' => 'Recruitment'],
            ['key' => 'performance', 'label' => 'Performance Management'],
            ['key' => 'assets', 'label' => 'Asset Management'],
            ['key' => 'vehicles', 'label' => 'Vehicle Fleet Management'],
            ['key' => 'policies', 'label' => 'Policy Document Management'],
            ['key' => 'directory', 'label' => 'Corporate Directory'],
            ['key' => 'buzz', 'label' => 'Employee Social Feed'],
            ['key' => 'helpdesk', 'label' => 'Helpdesk / Support Ticketing'],
            ['key' => 'pulse_surveys', 'label' => 'Employee Engagement & Pulse Surveys'],
        ] as $m) {
            ModuleToggle::updateOrCreate(['key' => $m['key']], $m);
        }

        // ---- Data groups (the logical resources modules actually expose) ----
        $groups = [
            ['key' => 'employee_personal_details', 'label' => 'Employee Personal Details'],
            ['key' => 'compensation', 'label' => 'Compensation'],
            ['key' => 'leave_requests', 'label' => 'Leave Requests'],
            ['key' => 'expense_claims', 'label' => 'Expense Claims'],
            ['key' => 'disciplinary_case', 'label' => 'Disciplinary Case Data'],
            ['key' => 'approvals_queue', 'label' => 'Approvals Queue'],
            ['key' => 'timesheets', 'label' => 'Timesheets'],
            // Recruitment (spec D1): 'all' scope means "sees every
            // requisition/vacancy/candidate," granted to HR only — everyone
            // else (including a hiring manager with no HR role) is
            // restricted at the query level to records where they're
            // specifically the assigned hiring manager, a per-record fact
            // this data group's scope doesn't otherwise express.
            ['key' => 'recruitment', 'label' => 'Recruitment'],
            // Spec D2: "Access throughout the module follows the
            // reporting-line graph: supervisors act on/see their own
            // reporting line, employees see only their own records, HR/Admin
            // see everything" — a plain self/self_subordinates/all scope,
            // unlike Recruitment's per-record hiring-manager fact.
            ['key' => 'performance', 'label' => 'Performance Management'],
            // Spec E2: full register (all/view_edit_delete) for Admin/HR
            // Admin/HR Officer; an ordinary ESS user sees only assets
            // currently assigned to them (self scope) — deliberately no
            // supervisor tier at all for Assets, unlike every other module's
            // reporting-line scoping.
            ['key' => 'assets', 'label' => 'Asset Management'],
            // Spec E3: "same for vehicles" as Assets — full register for
            // Admin/HR Admin/HR Officer, self-only for everyone else, no
            // supervisor tier.
            ['key' => 'vehicles', 'label' => 'Vehicle Fleet Management'],
            // Spec F1: "ownership-based edit/delete rights (authors manage
            // their own content; administrators can moderate others') via
            // the standard data-group permission model" — 'all' scope means
            // "may moderate anyone's post/share/comment," 'self' means
            // "own content only." This never restricts what's *visible* in
            // the feed (always company-wide), only who may edit/delete.
            ['key' => 'buzz', 'label' => 'Employee Social Feed'],
            // Spec F4: 'self' means "my own tickets only" (every employee's
            // base scope); 'all' means "every non-confidential ticket."
            // Confidential (Grievance/Whistleblower) tickets are gated
            // separately by TicketService::isHandler(), regardless of this
            // scope — even an 'all'-scope HR Admin doesn't see them unless
            // also on that category's specific handler list.
            ['key' => 'helpdesk_tickets', 'label' => 'Helpdesk Tickets'],
        ];
        foreach ($groups as $g) {
            DataGroup::updateOrCreate(['key' => $g['key']], $g);
        }

        // ---- Roles (spec A2's concrete catalog — system, non-deletable) ----
        $roles = [
            'admin' => ['name' => 'Admin', 'description' => 'Overall platform administrative rights.'],
            'hr_admin' => ['name' => 'HR Admin', 'description' => 'Rights to all employee data but not platform administrative rights.'],
            'hr_officer' => ['name' => 'HR Officer', 'description' => 'Support role to HR Admin — no access to sensitive employee data (compensation, disciplinary case).'],
            'ess' => ['name' => 'ESS', 'description' => 'Default role for every user with an employee record — self scope only.'],
        ];
        foreach ($roles as $slug => $r) {
            Role::updateOrCreate(['slug' => $slug], $r + ['is_system_role' => true]);
        }
        // Supervisor: situational, never assigned as a base role — computed
        // live from the reporting-line graph (spec A2).
        Role::updateOrCreate(['slug' => 'supervisor'], [
            'name' => 'Supervisor',
            'description' => 'Computed automatically for anyone with direct/indirect reports — layered on top of their base role, never assigned directly.',
            'is_system_role' => true,
            'is_situational' => true,
        ]);

        // ---- Screen grants per role ----
        $screenGrants = [
            'admin' => ['home', 'buzz', 'helpdesk', 'pulse-surveys', 'leave.apply', 'claims.create', 'timesheets', 'performance', 'assets', 'vehicles', 'discipline', 'directory', 'policies', 'profile', 'approvals', 'employees', 'recruitment', 'admin.master-data', 'admin.onboarding-templates', 'admin.leave-configuration', 'admin.performance-configuration', 'admin.asset-configuration', 'admin.vehicle-configuration', 'admin.policy-configuration', 'admin.company-documents', 'admin.helpdesk-configuration', 'admin.pulse-survey-configuration', 'admin.claims-management', 'admin.projects', 'settings', 'admin.roles', 'admin.audit-log', 'admin.signatures', 'admin.health-check'],
            'hr_admin' => ['home', 'buzz', 'helpdesk', 'pulse-surveys', 'leave.apply', 'claims.create', 'timesheets', 'performance', 'assets', 'vehicles', 'discipline', 'directory', 'policies', 'profile', 'approvals', 'employees', 'recruitment', 'admin.master-data', 'admin.onboarding-templates', 'admin.leave-configuration', 'admin.performance-configuration', 'admin.asset-configuration', 'admin.vehicle-configuration', 'admin.policy-configuration', 'admin.company-documents', 'admin.helpdesk-configuration', 'admin.pulse-survey-configuration', 'admin.claims-management', 'admin.projects', 'admin.signatures'],
            // HR Officer deliberately does NOT get 'discipline' or
            // 'admin.policy-configuration' — spec's own "only HR/Admin
            // manage categories, documents, and versions" reads as Admin/HR
            // Admin specifically. It does get claims-management, assets,
            // vehicles, and the base 'policies' browse/acknowledge screen,
            // since spec never excludes HR Officer from any of those.
            'hr_officer' => ['home', 'buzz', 'helpdesk', 'pulse-surveys', 'leave.apply', 'claims.create', 'timesheets', 'performance', 'assets', 'vehicles', 'directory', 'policies', 'profile', 'approvals', 'employees', 'recruitment', 'admin.claims-management'],
            // Recruitment/Performance/Discipline/Assets/Vehicles/Policies are
            // also granted at the base ESS level (like 'profile') since a
            // hiring manager may be any employee regardless of role, every
            // employee has their own performance records, spec D3 gives a
            // plain employee real (self-scoped) access to their own case,
            // spec E2/E3 give every employee visibility into assets/vehicles
            // assigned to them, and spec E4 gives every employee browse/
            // acknowledge access to policies — each screen scopes its own
            // content down via its data group's self/self_subordinates/all
            // scope (or, for Recruitment, the per-record hiring-manager
            // fact; for Policies, restricted-category file grants) rather
            // than a route-level block.
            'ess' => ['home', 'buzz', 'helpdesk', 'pulse-surveys', 'leave.apply', 'claims.create', 'timesheets', 'performance', 'assets', 'vehicles', 'discipline', 'directory', 'policies', 'profile', 'recruitment'],
            // Now that the workflow engine (WorkflowSeeder) gives supervisors
            // a real pending_manager stage to act on, they need the Approvals
            // screen too — layered on top of their base role same as any
            // other situational grant.
            'supervisor' => ['approvals'],
        ];
        foreach ($screenGrants as $slug => $keys) {
            $role = Role::where('slug', $slug)->first();
            foreach ($keys as $key) {
                $role->screenPermissions()->updateOrCreate(
                    ['screen_id' => Screen::where('key', $key)->value('id')],
                    ['can_view' => true]
                );
            }
        }

        // ---- Data-group matrix per role (spec A2's table, condensed to this
        // prototype's actual data groups) ----
        $matrix = [
            'admin' => [
                'employee_personal_details' => ['all', 'view_edit_delete'],
                'compensation' => ['all', 'view_edit_delete'],
                'leave_requests' => ['all', 'view_edit_delete'],
                'expense_claims' => ['all', 'view_edit_delete'],
                'disciplinary_case' => ['all', 'view_edit_delete'],
                'approvals_queue' => ['all', 'view_edit_delete'],
                'timesheets' => ['all', 'view_edit_delete'],
                'recruitment' => ['all', 'view_edit_delete'],
                'performance' => ['all', 'view_edit_delete'],
                'assets' => ['all', 'view_edit_delete'],
                'vehicles' => ['all', 'view_edit_delete'],
                'buzz' => ['all', 'view_edit_delete'],
                'helpdesk_tickets' => ['all', 'view_edit'],
            ],
            'hr_admin' => [
                'employee_personal_details' => ['all', 'view_edit_delete'],
                'compensation' => ['all', 'view_edit_delete'],
                'leave_requests' => ['all', 'view_edit_delete'],
                'expense_claims' => ['all', 'view_edit_delete'],
                'disciplinary_case' => ['all', 'view_edit_delete'],
                'approvals_queue' => ['all', 'view_edit'],
                'timesheets' => ['all', 'view_edit'],
                'recruitment' => ['all', 'view_edit_delete'],
                'performance' => ['all', 'view_edit_delete'],
                'assets' => ['all', 'view_edit_delete'],
                'vehicles' => ['all', 'view_edit_delete'],
                'buzz' => ['all', 'view_edit_delete'],
                'helpdesk_tickets' => ['all', 'view_edit'],
            ],
            'hr_officer' => [
                'employee_personal_details' => ['all', 'view_edit'],
                'compensation' => ['none', 'none'],
                'leave_requests' => ['all', 'view_edit'],
                'expense_claims' => ['all', 'view_edit'],
                'disciplinary_case' => ['none', 'none'],
                'approvals_queue' => ['all', 'view_edit'],
                'timesheets' => ['self', 'view_edit'],
                'recruitment' => ['all', 'view_edit'],
                'performance' => ['all', 'view_edit'],
                'assets' => ['all', 'view_edit'],
                'vehicles' => ['all', 'view_edit'],
                'buzz' => ['all', 'view_edit_delete'],
                'helpdesk_tickets' => ['all', 'view_edit'],
            ],
            'supervisor' => [
                'employee_personal_details' => ['self_subordinates', 'view'],
                'compensation' => ['none', 'none'],
                'leave_requests' => ['self_subordinates', 'view_edit'],
                'expense_claims' => ['self_subordinates', 'view_edit'],
                // Spec D3: "a supervisor may raise a case only against their
                // own reporting-line subordinates" — corrected from this
                // stub's original ['none','none'] (pre-seeded ahead of D3
                // existing at all) now that the feature is actually built.
                'disciplinary_case' => ['self_subordinates', 'view_edit'],
                'approvals_queue' => ['self_subordinates', 'view_edit'],
                'timesheets' => ['self_subordinates', 'view_edit'],
                'recruitment' => ['none', 'none'],
                'performance' => ['self_subordinates', 'view_edit'],
                // Spec E2: "no supervisor tier at all for Assets, unlike
                // every other module's reporting-line scoping" — a supervisor
                // sees only assets assigned to themselves, same as any ESS.
                'assets' => ['self', 'view'],
                'vehicles' => ['self', 'view'],
                'buzz' => ['self', 'view_edit_delete'],
                'helpdesk_tickets' => ['self', 'view_edit'],
            ],
            'ess' => [
                'employee_personal_details' => ['self', 'view_edit'],
                'compensation' => ['self', 'view'],
                'leave_requests' => ['self', 'view_edit'],
                'expense_claims' => ['self', 'view_edit'],
                // Spec D3: "a plain employee sees only cases about
                // themselves" — this is real access (respond, attach a
                // statement, acknowledge an outcome), just self-scoped, not
                // the same as HR Officer's total exclusion below.
                'disciplinary_case' => ['self', 'view_edit'],
                'approvals_queue' => ['none', 'none'],
                'timesheets' => ['self', 'view_edit'],
                'recruitment' => ['none', 'none'],
                'performance' => ['self', 'view_edit'],
                'assets' => ['self', 'view'],
                'vehicles' => ['self', 'view'],
                'buzz' => ['self', 'view_edit_delete'],
                'helpdesk_tickets' => ['self', 'view_edit'],
            ],
        ];
        foreach ($matrix as $slug => $grants) {
            $role = Role::where('slug', $slug)->first();
            foreach ($grants as $groupKey => [$scope, $level]) {
                $role->dataGroupPermissions()->updateOrCreate(
                    ['data_group_id' => DataGroup::where('key', $groupKey)->value('id')],
                    ['scope' => $scope, 'level' => $level]
                );
            }
        }
    }
}
