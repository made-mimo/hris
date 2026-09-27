<?php

namespace Database\Seeders;

use App\Models\ClaimEvent;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\ExpenseClaimLine;
use App\Models\ExpenseType;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data mirroring the UI/UX artifact's own sample content (Adaeze Okafor, Tunde
 * Bakare, the LV-2026-0187 / CLM-20260922-006 items, etc.) so the six prototype screens
 * render with the same realistic numbers as the design — see PLAN.md Section 4/5.
 */
class HrisDemoSeeder extends Seeder
{
    public function run(): void
    {
        $today = now()->startOfDay();

        // 2FA defaults OFF project-wide for local dev/testing, per explicit request.
        // Toggle it back on from /settings once real delivery (Section A1) exists.
        Setting::create(['id' => 1, 'company_name' => 'Systems Intelligenz', 'two_factor_enabled' => false]);

        // ---- Leave types (spec Section C1) ----
        $annual = LeaveType::create(['name' => 'Annual', 'slug' => 'annual', 'minimum_tenure_months' => 12, 'carries_over_at_year_end' => true, 'sort_order' => 1]);
        $sick = LeaveType::create(['name' => 'Sick', 'slug' => 'sick', 'minimum_tenure_months' => 0, 'sort_order' => 2]);
        $compassionate = LeaveType::create(['name' => 'Compassionate', 'slug' => 'compassionate', 'minimum_tenure_months' => 0, 'sort_order' => 3]);
        $study = LeaveType::create(['name' => 'Study / Exam', 'slug' => 'study-exam', 'minimum_tenure_months' => 0, 'sort_order' => 4]);

        // ---- Expense types (spec Section E1) ----
        $etAir = ExpenseType::create(['name' => 'Air travel']);
        $etHotel = ExpenseType::create(['name' => 'Accommodation']);
        $etMeals = ExpenseType::create(['name' => 'Meals (per diem)', 'default_cap' => 12500]);
        $etGround = ExpenseType::create(['name' => 'Ground transport']);
        ExpenseType::create(['name' => 'Communication']);

        // ---- Claim events (spec Section E1) ----
        ClaimEvent::create(['name' => 'Client site visit — Abuja']);
        $evLekki = ClaimEvent::create(['name' => 'Site survey — Lekki']);
        $evIkeja = ClaimEvent::create(['name' => 'Site survey — Ikeja']);
        ClaimEvent::create(['name' => 'Training — Johannesburg']);
        $evAbujaDemo = ClaimEvent::create(['name' => 'Client demo — Abuja']);

        // ---- Role lookups (seeded by RbacSeeder, which must run first) ----
        $roleAdmin = Role::where('slug', 'admin')->firstOrFail();
        $roleHrAdmin = Role::where('slug', 'hr_admin')->firstOrFail();
        $roleEss = Role::where('slug', 'ess')->firstOrFail();

        // ---- Platform Admin login (Settings, Roles & Permissions) ----
        $adminUser = User::create([
            'name' => 'Chuka Okoro',
            'email' => 'admin@systemsintelligenz.com',
            'password' => Hash::make('password'),
            'role_id' => $roleAdmin->id,
        ]);
        Employee::create([
            'user_id' => $adminUser->id, 'employee_id' => 'SIL2201001', 'first_name' => 'Chuka', 'last_name' => 'Okoro',
            'initials' => 'CO', 'job_title' => 'IT & Systems Admin', 'department' => 'IT', 'location' => 'Lagos',
            'hire_date' => $today->copy()->subYears(4),
        ]);

        // ---- HR & Admin login (Approvals queue owner) ----
        $hrUser = User::create([
            'name' => 'Ngozi Chukwu',
            'email' => 'hr@systemsintelligenz.com',
            'password' => Hash::make('password'),
            'role_id' => $roleHrAdmin->id,
        ]);
        $hr = Employee::create([
            'user_id' => $hrUser->id, 'employee_id' => 'SIL2401001', 'first_name' => 'Ngozi', 'last_name' => 'Chukwu',
            'initials' => 'NC', 'job_title' => 'HR & Admin Manager', 'department' => 'HR & Admin', 'location' => 'Lagos',
            'hire_date' => $today->copy()->subYears(4),
        ]);

        // ---- Line managers ----
        // Emeka gets a real login (base role ESS — "Line Manager" is the
        // situational Supervisor role, computed from having reports below,
        // same pattern as Adaeze) so the workflow engine's pending_manager
        // stage is actually reachable end-to-end, not just seeded data.
        $emekaUser = User::create([
            'name' => 'Emeka Nwosu',
            'email' => 'emeka@systemsintelligenz.com',
            'password' => Hash::make('password'),
            'role_id' => $roleEss->id,
        ]);
        $emeka = Employee::create([
            'user_id' => $emekaUser->id,
            'employee_id' => 'SIL2201004', 'first_name' => 'Emeka', 'last_name' => 'Nwosu', 'initials' => 'EN',
            'job_title' => 'Line Manager', 'department' => 'AV Integration', 'location' => 'Lagos', 'hire_date' => $today->copy()->subYears(5),
        ]);
        $kunle = Employee::create([
            'employee_id' => 'SIL2201005', 'first_name' => 'Kunle', 'last_name' => 'Ade', 'initials' => 'KA',
            'job_title' => 'Line Manager', 'department' => 'Service & Support', 'location' => 'Lagos', 'hire_date' => $today->copy()->subYears(6),
        ]);
        $bola = Employee::create([
            'employee_id' => 'SIL2201006', 'first_name' => 'Bola', 'last_name' => 'Martins', 'initials' => 'BM',
            'job_title' => 'Head of Sales', 'department' => 'Sales', 'location' => 'Lagos', 'hire_date' => $today->copy()->subYears(7),
        ]);

        // ---- The logged-in ESS/Supervisor demo user: Adaeze Okafor ----
        $adaezeUser = User::create([
            'name' => 'Adaeze Okafor',
            'email' => 'adaeze@systemsintelligenz.com',
            'password' => Hash::make('password'),
            // Base role is ESS — "Line Manager" is the situational Supervisor
            // role, computed live because she has direct reports below, never
            // assigned directly (spec A2). See App\Services\PermissionService.
            'role_id' => $roleEss->id,
        ]);
        $adaeze = Employee::create([
            'user_id' => $adaezeUser->id, 'supervisor_id' => $emeka->id, 'employee_id' => 'SIL2603001',
            'first_name' => 'Adaeze', 'last_name' => 'Okafor', 'initials' => 'AO', 'job_title' => 'Project Engineer',
            'department' => 'AV Integration', 'location' => 'Lagos', 'hire_date' => $today->copy()->subYears(3),
        ]);

        // ---- Adaeze's direct reports ----
        $tunde = Employee::create([
            'supervisor_id' => $adaeze->id, 'employee_id' => 'SIL2402002', 'first_name' => 'Tunde', 'last_name' => 'Bakare',
            'initials' => 'TB', 'job_title' => 'Project Engineer', 'department' => 'AV Integration', 'location' => 'Lagos',
            'hire_date' => $today->copy()->subYears(2),
        ]);
        $chidi = Employee::create([
            'supervisor_id' => $adaeze->id, 'employee_id' => 'SIL2402003', 'first_name' => 'Chidi', 'last_name' => 'Eze',
            'initials' => 'CE', 'job_title' => 'Field Technician', 'department' => 'AV Integration', 'location' => 'Lagos',
            'hire_date' => $today->copy()->subYears(2),
        ]);
        $ngozi = Employee::create([
            'supervisor_id' => $adaeze->id, 'employee_id' => 'SIL2402004', 'first_name' => 'Ngozi', 'last_name' => 'Obi',
            'initials' => 'NO', 'job_title' => 'Field Technician', 'department' => 'AV Integration', 'location' => 'Lagos',
            'hire_date' => $today->copy()->subYears(1),
        ]);
        $yusuf = Employee::create([
            'supervisor_id' => $adaeze->id, 'employee_id' => 'SIL2402005', 'first_name' => 'Yusuf', 'last_name' => 'Abubakar',
            'initials' => 'YA', 'job_title' => 'Field Technician', 'department' => 'AV Integration', 'location' => 'Lagos',
            'hire_date' => $today->copy()->subYears(1),
        ]);

        // ---- Other employees referenced by the Approvals queue mockup ----
        $ibrahim = Employee::create([
            'supervisor_id' => $kunle->id, 'employee_id' => 'SIL2402006', 'first_name' => 'Ibrahim', 'last_name' => 'Musa',
            'initials' => 'IM', 'job_title' => 'Support Engineer', 'department' => 'Service & Support', 'location' => 'Lagos',
            'hire_date' => $today->copy()->subYears(2),
        ]);
        $seyi = Employee::create([
            'supervisor_id' => $emeka->id, 'employee_id' => 'SIL2402007', 'first_name' => 'Seyi', 'last_name' => 'Ogunleye',
            'initials' => 'SO', 'job_title' => 'Project Coordinator', 'department' => 'Projects', 'location' => 'Lagos',
            'hire_date' => $today->copy()->subYears(2),
        ]);
        $chioma = Employee::create([
            'supervisor_id' => $bola->id, 'employee_id' => 'SIL2402008', 'first_name' => 'Chioma', 'last_name' => 'Nnaji',
            'initials' => 'CN', 'job_title' => 'Sales Executive', 'department' => 'Sales', 'location' => 'Lagos',
            'hire_date' => $today->copy()->subYears(1),
        ]);

        // ---- Leave entitlements for the current leave year (spec C1: calendar-year leave period) ----
        $year = $today->year;
        foreach ([$adaeze, $tunde, $chidi, $ngozi, $yusuf, $ibrahim, $seyi] as $emp) {
            LeaveEntitlement::create(['employee_id' => $emp->id, 'leave_type_id' => $annual->id, 'year' => $year, 'entitled_days' => 20]);
            LeaveEntitlement::create(['employee_id' => $emp->id, 'leave_type_id' => $sick->id, 'year' => $year, 'entitled_days' => 10]);
            LeaveEntitlement::create(['employee_id' => $emp->id, 'leave_type_id' => $compassionate->id, 'year' => $year, 'entitled_days' => 5]);
            LeaveEntitlement::create(['employee_id' => $emp->id, 'leave_type_id' => $study->id, 'year' => $year, 'entitled_days' => 5]);
        }

        // ---- Adaeze's leave history: reproduces Home/ApplyLeave's "12.5 of 20 (6 taken, 1.5 scheduled)" ----
        LeaveRequest::create([
            'reference' => 'LV-'.$year.'-0142', 'employee_id' => $adaeze->id, 'leave_type_id' => $annual->id,
            'reliever_employee_id' => $tunde->id, 'start_date' => $today->copy()->subMonths(3)->startOfWeek(),
            'end_date' => $today->copy()->subMonths(3)->startOfWeek()->addDays(5), 'duration_type' => 'full', 'days' => 4.5,
            'status' => 'approved', 'manager_approved_by' => null, 'manager_approved_at' => $today->copy()->subMonths(3),
            'hr_approved_by' => $hrUser->id, 'hr_approved_at' => $today->copy()->subMonths(3),
        ]);
        LeaveRequest::create([
            'reference' => 'LV-'.$year.'-0165', 'employee_id' => $adaeze->id, 'leave_type_id' => $annual->id,
            'reliever_employee_id' => $tunde->id, 'start_date' => $today->copy()->subDays(27),
            'end_date' => $today->copy()->subDays(26), 'duration_type' => 'full', 'days' => 1.5,
            'status' => 'approved', 'hr_approved_by' => $hrUser->id, 'hr_approved_at' => $today->copy()->subDays(25),
        ]);
        LeaveRequest::create([
            'reference' => 'LV-'.$year.'-0179', 'employee_id' => $adaeze->id, 'leave_type_id' => $annual->id,
            'reliever_employee_id' => $tunde->id, 'start_date' => $today->copy()->addDays(22),
            'end_date' => $today->copy()->addDays(22), 'duration_type' => 'full', 'days' => 1.5,
            'status' => 'approved', 'hr_approved_by' => $hrUser->id, 'hr_approved_at' => $today->copy()->subDays(2),
        ]);
        LeaveRequest::create([
            'reference' => 'LV-'.$year.'-0187', 'employee_id' => $adaeze->id, 'leave_type_id' => $annual->id,
            'reliever_employee_id' => $tunde->id, 'start_date' => $today->copy()->addDays(8),
            'end_date' => $today->copy()->addDays(12), 'duration_type' => 'full', 'days' => 5.0,
            'reason' => 'Family event in Enugu. Handover notes for the Victoria Island boardroom install are in the project folder.',
            'status' => 'pending_hr', 'manager_approved_by' => null, 'manager_approved_at' => $today->copy()->subDays(2),
        ]);
        LeaveRequest::create([
            'reference' => 'LV-'.$year.'-0091', 'employee_id' => $adaeze->id, 'leave_type_id' => $study->id,
            'start_date' => $today->copy()->subMonths(2), 'end_date' => $today->copy()->subMonths(2)->addDay(),
            'duration_type' => 'full', 'days' => 2.0, 'status' => 'approved',
            'hr_approved_by' => $hrUser->id, 'hr_approved_at' => $today->copy()->subMonths(2),
        ]);

        // ---- Others' pending leave, already Line-Manager-approved and waiting on HR (the Approvals queue) ----
        LeaveRequest::create([
            'reference' => 'LV-'.$year.'-0191', 'employee_id' => $ibrahim->id, 'leave_type_id' => $sick->id,
            'start_date' => $today->copy()->addDays(2), 'end_date' => $today->copy()->addDays(3), 'duration_type' => 'full',
            'days' => 2.0, 'status' => 'pending_hr', 'manager_approved_at' => $today->copy()->subHours(20),
        ]);
        // Genuinely at the Line Manager stage (not yet manager-approved) —
        // the workflow engine's pending_manager state, actionable by Emeka
        // (Seyi's real supervisor, with a real login: emeka@systemsintelligenz.com).
        LeaveRequest::create([
            'reference' => 'LV-'.$year.'-0193', 'employee_id' => $seyi->id, 'leave_type_id' => $annual->id,
            'reliever_employee_id' => $yusuf->id, 'start_date' => $today->copy()->addDays(17),
            'end_date' => $today->copy()->addDays(19), 'duration_type' => 'full', 'days' => 3.0,
            'status' => 'pending_manager',
        ]);

        // ---- Currently-out-today rows (Home's "Who's out today" widget) ----
        LeaveRequest::create([
            'reference' => 'LV-'.$year.'-0120', 'employee_id' => $chidi->id, 'leave_type_id' => $annual->id,
            'start_date' => $today->copy()->subDays(3), 'end_date' => $today->copy()->addDays(7), 'duration_type' => 'full',
            'days' => 8.0, 'status' => 'approved', 'hr_approved_by' => $hrUser->id, 'hr_approved_at' => $today->copy()->subDays(5),
        ]);
        LeaveRequest::create([
            'reference' => 'LV-'.$year.'-0121', 'employee_id' => $ngozi->id, 'leave_type_id' => $sick->id,
            'start_date' => $today->copy()->subDays(2), 'end_date' => $today->copy()->addDays(2), 'duration_type' => 'full',
            'days' => 4.0, 'status' => 'approved', 'hr_approved_by' => $hrUser->id, 'hr_approved_at' => $today->copy()->subDays(2),
        ]);
        LeaveRequest::create([
            'reference' => 'LV-'.$year.'-0122', 'employee_id' => $yusuf->id, 'leave_type_id' => $study->id,
            'start_date' => $today, 'end_date' => $today, 'duration_type' => 'pm', 'days' => 0.5,
            'status' => 'approved', 'hr_approved_by' => $hrUser->id, 'hr_approved_at' => $today->copy()->subDay(),
        ]);

        // ---- Expense claims ----
        // Adaeze's already-approved claim awaiting Finance payment (Home's "Claims awaiting payment ₦62,300")
        $claimIkeja = ExpenseClaim::create([
            'reference' => 'CLM-'.$today->copy()->subDays(14)->format('Ymd').'-011', 'employee_id' => $adaeze->id,
            'claim_event_id' => $evIkeja->id, 'currency' => 'NGN', 'status' => 'approved',
            'submitted_at' => $today->copy()->subDays(14), 'manager_approved_at' => $today->copy()->subDays(12),
            'hr_approved_by' => $hrUser->id, 'hr_approved_at' => $today->copy()->subDays(10),
        ]);
        ExpenseClaimLine::create(['expense_claim_id' => $claimIkeja->id, 'expense_type_id' => $etGround->id, 'date' => $today->copy()->subDays(14), 'note' => 'Site survey transfers', 'amount' => 62300]);

        // Tunde's claim, manager-approved, waiting on HR
        $claimLekki = ExpenseClaim::create([
            'reference' => 'CLM-'.$today->copy()->subDays(6)->format('Ymd').'-006', 'employee_id' => $tunde->id,
            'claim_event_id' => $evLekki->id, 'currency' => 'NGN', 'status' => 'pending_hr',
            'submitted_at' => $today->copy()->subDays(6), 'manager_approved_by' => null, 'manager_approved_at' => $today->copy()->subDays(4),
        ]);
        ExpenseClaimLine::create(['expense_claim_id' => $claimLekki->id, 'expense_type_id' => $etGround->id, 'date' => $today->copy()->subDays(6), 'note' => 'Site transfers', 'amount' => 18750]);
        ExpenseClaimLine::create(['expense_claim_id' => $claimLekki->id, 'expense_type_id' => $etMeals->id, 'date' => $today->copy()->subDays(6), 'note' => 'Team lunch', 'amount' => 12000]);
        ExpenseClaimLine::create(['expense_claim_id' => $claimLekki->id, 'expense_type_id' => $etHotel->id, 'date' => $today->copy()->subDays(6), 'note' => 'One night', 'amount' => 18000]);

        // Chioma's claim, manager-approved, waiting on HR, with a flagged over-cap line
        $claimAbuja = ExpenseClaim::create([
            'reference' => 'CLM-'.$today->copy()->subDays(3)->format('Ymd').'-009', 'employee_id' => $chioma->id,
            'claim_event_id' => $evAbujaDemo->id, 'currency' => 'NGN', 'status' => 'pending_hr',
            'submitted_at' => $today->copy()->subDays(3), 'manager_approved_by' => null, 'manager_approved_at' => $today->copy()->subDays(2),
        ]);
        ExpenseClaimLine::create(['expense_claim_id' => $claimAbuja->id, 'expense_type_id' => $etAir->id, 'date' => $today->copy()->subDays(3), 'note' => 'Lagos – Abuja return', 'amount' => 120000]);
        ExpenseClaimLine::create(['expense_claim_id' => $claimAbuja->id, 'expense_type_id' => $etHotel->id, 'date' => $today->copy()->subDays(3), 'note' => 'Hotel, 2 nights', 'amount' => 62000]);
        ExpenseClaimLine::create(['expense_claim_id' => $claimAbuja->id, 'expense_type_id' => $etMeals->id, 'date' => $today->copy()->subDays(2), 'note' => 'Client dinner with prospect', 'amount' => 32000, 'flagged' => true, 'justification' => 'Client dinner with prospect, above per-diem cap']);

        $this->command?->info('Demo users (password "password"): admin@ · hr@ · adaeze@ · emeka@systemsintelligenz.com');
    }
}
