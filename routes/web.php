<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeCsvTemplateController;
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

// Every screen below is gated by the RBAC engine (App\Services\PermissionService),
// not a hard-coded role check — 'screen:<key>' matches a row in the `screens`
// table and is resolved against whichever role(s) the signed-in user's Role
// Permission Matrix grants it to (spec Section 3.2/A2).
Route::middleware(['auth', 'password_policy', 'two_factor'])->group(function () {
    Route::livewire('/', 'pages::home')->name('home')->middleware('screen:home');
    Route::livewire('/leave/apply', 'pages::leave-apply')->name('leave.apply')->middleware('screen:leave.apply');
    Route::livewire('/claims/create', 'pages::expense-claim')->name('claims.create')->middleware('screen:claims.create');
    Route::livewire('/profile', 'pages::profile')->name('profile')->middleware('screen:profile');
    Route::livewire('/approvals', 'pages::approvals')->name('approvals')->middleware('screen:approvals');
    Route::livewire('/employees', 'pages::employees')->name('employees')->middleware('screen:employees');
    Route::livewire('/employees/create', 'pages::employee-create')->name('employees.create')->middleware('screen:employees');
    Route::get('/employees/csv-template', EmployeeCsvTemplateController::class)->name('employees.csv-template')->middleware('screen:employees');
    Route::livewire('/employees/reports', 'pages::employee-reports')->name('employees.reports')->middleware('screen:employees');
    Route::livewire('/employees/{employee}', 'pages::employee-show')->name('employees.show')->middleware('screen:employees');
    Route::livewire('/org-chart', 'pages::org-chart')->name('org-chart')->middleware('screen:employees');
    Route::livewire('/admin/master-data', 'pages::admin-master-data')->name('admin.master-data')->middleware('screen:admin.master-data');
    Route::livewire('/admin/onboarding-templates', 'pages::admin-onboarding-templates')->name('admin.onboarding-templates')->middleware('screen:admin.onboarding-templates');
    Route::livewire('/settings', 'pages::settings')->name('settings')->middleware('screen:settings');
    Route::livewire('/admin/roles', 'pages::admin-roles')->name('admin.roles')->middleware('screen:admin.roles');
    Route::livewire('/admin/roles/{role}', 'pages::admin-role-edit')->name('admin.roles.edit')->middleware('screen:admin.roles');
    Route::livewire('/admin/audit-log', 'pages::admin-audit-log')->name('admin.audit-log')->middleware('screen:admin.audit-log');
    Route::livewire('/admin/signatures', 'pages::admin-signatures')->name('admin.signatures')->middleware('screen:admin.signatures');
});
