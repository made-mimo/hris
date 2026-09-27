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
            ['key' => 'leave.apply', 'label' => 'My Leave', 'nav_group' => 'Workspace', 'sort_order' => 2, 'module_key' => 'leave'],
            ['key' => 'claims.create', 'label' => 'My Claims', 'nav_group' => 'Workspace', 'sort_order' => 3, 'module_key' => 'claims'],
            ['key' => 'profile', 'label' => 'My Profile', 'nav_group' => 'Workspace', 'sort_order' => 4],
            ['key' => 'approvals', 'label' => 'Approvals', 'nav_group' => 'My Team', 'sort_order' => 5],
            ['key' => 'employees', 'label' => 'Employees', 'nav_group' => 'My Team', 'sort_order' => 6],
            ['key' => 'admin.master-data', 'label' => 'Organization & Master Data', 'nav_group' => 'Admin', 'sort_order' => 6],
            ['key' => 'admin.onboarding-templates', 'label' => 'Onboarding/Offboarding Templates', 'nav_group' => 'Admin', 'sort_order' => 7],
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
            'admin' => ['home', 'leave.apply', 'claims.create', 'profile', 'approvals', 'employees', 'admin.master-data', 'admin.onboarding-templates', 'settings', 'admin.roles', 'admin.audit-log', 'admin.signatures', 'admin.health-check'],
            'hr_admin' => ['home', 'leave.apply', 'claims.create', 'profile', 'approvals', 'employees', 'admin.master-data', 'admin.onboarding-templates', 'admin.signatures'],
            'hr_officer' => ['home', 'leave.apply', 'claims.create', 'profile', 'approvals', 'employees'],
            'ess' => ['home', 'leave.apply', 'claims.create', 'profile'],
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
            ],
            'hr_admin' => [
                'employee_personal_details' => ['all', 'view_edit_delete'],
                'compensation' => ['all', 'view_edit_delete'],
                'leave_requests' => ['all', 'view_edit_delete'],
                'expense_claims' => ['all', 'view_edit_delete'],
                'disciplinary_case' => ['all', 'view_edit_delete'],
                'approvals_queue' => ['all', 'view_edit'],
            ],
            'hr_officer' => [
                'employee_personal_details' => ['all', 'view_edit'],
                'compensation' => ['none', 'none'],
                'leave_requests' => ['all', 'view_edit'],
                'expense_claims' => ['all', 'view_edit'],
                'disciplinary_case' => ['none', 'none'],
                'approvals_queue' => ['all', 'view_edit'],
            ],
            'supervisor' => [
                'employee_personal_details' => ['self_subordinates', 'view'],
                'compensation' => ['none', 'none'],
                'leave_requests' => ['self_subordinates', 'view_edit'],
                'expense_claims' => ['self_subordinates', 'view_edit'],
                'disciplinary_case' => ['none', 'none'],
                'approvals_queue' => ['self_subordinates', 'view_edit'],
            ],
            'ess' => [
                'employee_personal_details' => ['self', 'view_edit'],
                'compensation' => ['self', 'view'],
                'leave_requests' => ['self', 'view_edit'],
                'expense_claims' => ['self', 'view_edit'],
                'disciplinary_case' => ['none', 'none'],
                'approvals_queue' => ['none', 'none'],
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
