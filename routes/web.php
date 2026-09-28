<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CareersFeedController;
use App\Http\Controllers\CompanyDocumentFileController;
use App\Http\Controllers\EmployeeCsvTemplateController;
use App\Http\Controllers\PolicyDocumentFileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\TimezoneController;
use Illuminate\Support\Facades\Route;

// Client-declared timezone capture (browser Intl API only — see PLAN.md).
// Open to guest and authenticated requests alike so it works pre-login too.
Route::post('/timezone', TimezoneController::class)->name('timezone.set');

// Spec Section A7's Web Push subscription lifecycle.
Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe')->middleware('auth');
Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe')->middleware('auth');

Route::livewire('/login', 'pages::login')->name('login')->middleware('guest');
Route::livewire('/forgot-password', 'pages::forgot-password')->name('password.request')->middleware('guest');

// Authenticated (password already checked) but not yet past the 2FA step —
// deliberately outside both 'guest' (they ARE logged in) and 'two_factor'
// (that's the very check this page satisfies).
Route::livewire('/login/verify', 'pages::login-verify')->name('login.verify')->middleware('auth');
Route::livewire('/login/setup', 'pages::login-setup')->name('login.setup')->middleware('auth');

// Same "authenticated but not yet past a gate" placement as the 2FA pages —
// outside 'password_policy' since this route is what that gate redirects to.
Route::livewire('/account/update-password', 'pages::account-update-password')->name('account.update-password')->middleware('auth');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Spec D1's public job board — unauthenticated by design ("unauthenticated
// listing/detail views and an RSS feed limited to published, open
// vacancies; a public application form for online applicants").
Route::livewire('/careers', 'pages::careers')->name('careers');
Route::livewire('/careers/{vacancy}', 'pages::careers-vacancy')->name('careers.show');
Route::get('/careers.rss', CareersFeedController::class)->name('careers.feed');

// Spec D1's offer-letter e-signature: the candidate has no system account at
// this point in the pipeline, so this is a signed URL (Laravel's own
// tamper-proof query-string HMAC), not a login-gated screen.
Route::livewire('/recruitment/offer/{application}/sign', 'pages::offer-sign')->name('recruitment.offer.sign')->middleware('signed');

// Every screen below is gated by the RBAC engine (App\Services\PermissionService),
// not a hard-coded role check — 'screen:<key>' matches a row in the `screens`
// table and is resolved against whichever role(s) the signed-in user's Role
// Permission Matrix grants it to (spec Section 3.2/A2).
Route::middleware(['auth', 'password_policy', 'two_factor'])->group(function () {
    Route::livewire('/', 'pages::home')->name('home')->middleware('screen:home');
    Route::livewire('/leave/apply', 'pages::leave-apply')->name('leave.apply')->middleware('screen:leave.apply');
    Route::livewire('/leave/assign', 'pages::leave-assign')->name('leave.assign')->middleware('screen:approvals');
    Route::livewire('/leave/reports', 'pages::leave-reports')->name('leave.reports')->middleware('screen:admin.leave-configuration');
    Route::livewire('/claims/create', 'pages::expense-claim')->name('claims.create')->middleware('screen:claims.create');
    Route::livewire('/claims/travel-advance', 'pages::claims-travel-advance')->name('claims.travel-advance')->middleware('screen:claims.create');
    Route::livewire('/admin/claims-management', 'pages::claims-management')->name('admin.claims-management')->middleware('screen:admin.claims-management');
    Route::livewire('/timesheets', 'pages::timesheets')->name('timesheets')->middleware('screen:timesheets');
    Route::livewire('/timesheets/approvals', 'pages::timesheet-approvals')->name('timesheets.approvals')->middleware('screen:timesheets');
    Route::livewire('/timesheets/reports', 'pages::timesheet-reports')->name('timesheets.reports')->middleware('screen:admin.projects');
    Route::livewire('/attendance', 'pages::attendance')->name('attendance')->middleware('screen:timesheets');
    Route::livewire('/attendance/reports', 'pages::attendance-reports')->name('attendance.reports')->middleware('screen:admin.projects');
    Route::livewire('/admin/projects', 'pages::admin-projects')->name('admin.projects')->middleware('screen:admin.projects');
    Route::livewire('/recruitment', 'pages::recruitment')->name('recruitment')->middleware('screen:recruitment');
    Route::livewire('/performance', 'pages::performance')->name('performance')->middleware('screen:performance');
    Route::livewire('/discipline', 'pages::discipline')->name('discipline')->middleware('screen:discipline');
    Route::livewire('/assets', 'pages::assets')->name('assets')->middleware('screen:assets');
    Route::livewire('/vehicles', 'pages::vehicles')->name('vehicles')->middleware('screen:vehicles');
    Route::livewire('/policies', 'pages::policies')->name('policies')->middleware('screen:policies');
    Route::get('/policies/versions/{version}/file', PolicyDocumentFileController::class)->name('policies.file')->middleware('screen:policies');
    Route::livewire('/profile', 'pages::profile')->name('profile')->middleware('screen:profile');
    Route::livewire('/account/settings', 'pages::account-settings')->name('account.settings')->middleware('screen:profile');
    Route::livewire('/approvals', 'pages::approvals')->name('approvals')->middleware('screen:approvals');
    Route::livewire('/employees', 'pages::employees')->name('employees')->middleware('screen:employees');
    Route::livewire('/employees/create', 'pages::employee-create')->name('employees.create')->middleware('screen:employees');
    Route::get('/employees/csv-template', EmployeeCsvTemplateController::class)->name('employees.csv-template')->middleware('screen:employees');
    Route::livewire('/employees/reports', 'pages::employee-reports')->name('employees.reports')->middleware('screen:employees');
    Route::livewire('/employees/{employee}', 'pages::employee-show')->name('employees.show')->middleware('screen:employees');
    Route::livewire('/org-chart', 'pages::org-chart')->name('org-chart')->middleware('screen:employees');
    Route::livewire('/admin/master-data', 'pages::admin-master-data')->name('admin.master-data')->middleware('screen:admin.master-data');
    Route::livewire('/admin/onboarding-templates', 'pages::admin-onboarding-templates')->name('admin.onboarding-templates')->middleware('screen:admin.onboarding-templates');
    Route::livewire('/admin/leave-configuration', 'pages::admin-leave-configuration')->name('admin.leave-configuration')->middleware('screen:admin.leave-configuration');
    Route::livewire('/admin/performance-configuration', 'pages::admin-performance-configuration')->name('admin.performance-configuration')->middleware('screen:admin.performance-configuration');
    Route::livewire('/admin/asset-configuration', 'pages::admin-asset-configuration')->name('admin.asset-configuration')->middleware('screen:admin.asset-configuration');
    Route::livewire('/admin/vehicle-configuration', 'pages::admin-vehicle-configuration')->name('admin.vehicle-configuration')->middleware('screen:admin.vehicle-configuration');
    Route::livewire('/admin/policy-configuration', 'pages::admin-policy-configuration')->name('admin.policy-configuration')->middleware('screen:admin.policy-configuration');
    Route::livewire('/admin/company-documents', 'pages::admin-company-documents')->name('admin.company-documents')->middleware('screen:admin.company-documents');
    // No screen middleware here: authorization is entirely inside CompanyDocumentService::canAccessFile()
    // so a specifically-granted non-admin employee (spec E5's "external auditor" case) can still reach the file.
    Route::get('/company-documents/versions/{version}/file', CompanyDocumentFileController::class)->name('company-documents.file');
    Route::livewire('/settings', 'pages::settings')->name('settings')->middleware('screen:settings');
    Route::livewire('/admin/roles', 'pages::admin-roles')->name('admin.roles')->middleware('screen:admin.roles');
    Route::livewire('/admin/roles/{role}', 'pages::admin-role-edit')->name('admin.roles.edit')->middleware('screen:admin.roles');
    Route::livewire('/admin/audit-log', 'pages::admin-audit-log')->name('admin.audit-log')->middleware('screen:admin.audit-log');
    Route::livewire('/admin/signatures', 'pages::admin-signatures')->name('admin.signatures')->middleware('screen:admin.signatures');
    Route::livewire('/admin/health-check', 'pages::admin-health-check')->name('admin.health-check')->middleware('screen:admin.health-check');
});
