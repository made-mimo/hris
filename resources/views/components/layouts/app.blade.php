@props(['title' => 'Home'])
@php
    $me = auth()->user()?->employee;
    $isHr = auth()->user()?->isHr();
    $isSupervisor = $me && $me->subordinates()->exists();
    $permissions = app(\App\Services\PermissionService::class);
    $canApprovals = auth()->user()?->canView('approvals');
    $canEmployees = auth()->user()?->canView('employees');
    $canRecruitmentScreen = auth()->user()?->canView('recruitment');
    $isHiringManager = $me && \App\Models\Vacancy::where('hiring_manager_id', $me->id)->exists();
    $canRecruitment = $canRecruitmentScreen && (in_array($permissions->scopeFor(auth()->user(), 'recruitment'), ['all'], true) || $isHiringManager);
    $canDiscipline = auth()->user()?->canView('discipline');
    $canLeave = auth()->user()?->canView('leave.apply');
    $canClaims = auth()->user()?->canView('claims.create');
    $canTimesheets = auth()->user()?->canView('timesheets');
    $canApproveTimesheets = $canTimesheets && in_array($permissions->scopeFor(auth()->user(), 'timesheets'), ['all', 'self_subordinates'], true);
    $canPerformance = auth()->user()?->canView('performance');
    $canAssets = auth()->user()?->canView('assets');
    $canVehicles = auth()->user()?->canView('vehicles');
    $canPolicies = auth()->user()?->canView('policies');
    $canMasterData = auth()->user()?->canView('admin.master-data');
    $canOnboardingTemplates = auth()->user()?->canView('admin.onboarding-templates');
    $canLeaveConfiguration = auth()->user()?->canView('admin.leave-configuration');
    $canPerformanceConfiguration = auth()->user()?->canView('admin.performance-configuration');
    $canAssetConfiguration = auth()->user()?->canView('admin.asset-configuration');
    $canVehicleConfiguration = auth()->user()?->canView('admin.vehicle-configuration');
    $canPolicyConfiguration = auth()->user()?->canView('admin.policy-configuration');
    $canClaimsManagement = auth()->user()?->canView('admin.claims-management');
    $canProjects = auth()->user()?->canView('admin.projects');
    $canSettings = auth()->user()?->canView('settings');
    $canRoles = auth()->user()?->canView('admin.roles');
    $canAuditLog = auth()->user()?->canView('admin.audit-log');
    $canSignatures = auth()->user()?->canView('admin.signatures');
    $canHealthCheck = auth()->user()?->canView('admin.health-check');
    $approvalsBadge = $canApprovals ? \App\Models\LeaveRequest::where('status', 'pending_hr')->count()
        + \App\Models\ExpenseClaim::where('status', 'pending_hr')->count() : 0;
    $settings = \App\Models\Setting::current();
    $logoUrl = $settings->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($settings->logo_path) : null;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SI HRIS — {{ $title }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        (function () {
            var stored = localStorage.getItem('theme');
            var dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (dark) document.documentElement.classList.add('dark');
        })();
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&amp;family=Inter:ital,wght@0,400;0,500;0,600;0,700;1,400&amp;family=JetBrains+Mono:wght@500;600&amp;display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body>
<div>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $settings->company_name }}" style="width:34px;height:34px;border-radius:9px;object-fit:cover;flex-shrink:0;">
            @else
                <div class="brand-mark"></div>
            @endif
            <div>
                <div class="font-display" style="font-size:13px;">{{ strtoupper($settings->company_name) }}</div>
                <div class="text-faint" style="font-size:10px;font-weight:700;letter-spacing:1.2px;margin-top:2px;">HRIS</div>
            </div>
        </div>

        <nav class="sidebar-nav" aria-label="Main"
            x-data="{
                sections: (() => { try { return JSON.parse(localStorage.getItem('navSections')) || {}; } catch (e) { return {}; } })(),
                keys: ['workspace', 'myteam', 'company', 'admin'],
                init() { this.keys.forEach(k => { if (!(k in this.sections)) this.sections[k] = true; }); },
                toggle(k) { this.sections[k] = !this.sections[k]; this.save(); },
                collapseAll() { this.keys.forEach(k => this.sections[k] = false); this.save(); },
                expandAll() { this.keys.forEach(k => this.sections[k] = true); this.save(); },
                save() { localStorage.setItem('navSections', JSON.stringify(this.sections)); }
            }">
            <div style="display:flex;justify-content:flex-end;gap:10px;padding:0 12px 8px;">
                <button type="button" @click="expandAll()" style="background:none;border:none;padding:0;cursor:pointer;font-size:10.5px;font-weight:700;color:var(--color-text-faint);">Expand all</button>
                <button type="button" @click="collapseAll()" style="background:none;border:none;padding:0;cursor:pointer;font-size:10.5px;font-weight:700;color:var(--color-text-faint);">Collapse all</button>
            </div>

            <button type="button" @click="toggle('workspace')" class="nav-section-label" style="display:flex;width:100%;align-items:center;justify-content:space-between;background:none;border:none;cursor:pointer;font:inherit;text-align:left;">
                <span>Workspace</span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :style="sections.workspace ? '' : 'transform:rotate(-90deg)'" style="transition:transform .15s ease;"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <div x-show="sections.workspace" x-cloak>
            <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}">
                <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"></path></svg></span>Home
            </a>
            @if($canLeave)
                <a href="{{ route('leave.apply') }}" class="nav-link {{ request()->routeIs('leave.apply') ? 'active' : '' }}">
                    <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M3 10h18M8 3v4M16 3v4"></path></svg></span>My Leave
                </a>
            @endif
            @if($canClaims)
                <a href="{{ route('claims.create') }}" class="nav-link {{ request()->routeIs('claims.create') ? 'active' : '' }}">
                    <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"></path><path d="M9 8h6M9 12h6"></path></svg></span>My Claims
                </a>
                <a href="{{ route('claims.travel-advance') }}" class="nav-link {{ request()->routeIs('claims.travel-advance') ? 'active' : '' }}">
                    <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><path d="M3.27 6.96 12 12l8.73-5.04M12 22.08V12"></path></svg></span>Travel Advance
                </a>
            @endif
            @if($canTimesheets)
                <a href="{{ route('timesheets') }}" class="nav-link {{ request()->routeIs('timesheets') ? 'active' : '' }}">
                    <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg></span>My Timesheets
                </a>
                <a href="{{ route('attendance') }}" class="nav-link {{ request()->routeIs('attendance') ? 'active' : '' }}">
                    <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path><path d="M12 14v3l2 1"></path></svg></span>My Attendance
                </a>
            @endif
            @if($canPerformance)
                <a href="{{ route('performance') }}" class="nav-link {{ request()->routeIs('performance') ? 'active' : '' }}">
                    <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="M18 17V9M13 17V5M8 17v-4"></path></svg></span>Performance
                </a>
            @endif
            @if($canAssets)
                <a href="{{ route('assets') }}" class="nav-link {{ request()->routeIs('assets') ? 'active' : '' }}">
                    <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg></span>Assets
                </a>
            @endif
            @if($canVehicles)
                <a href="{{ route('vehicles') }}" class="nav-link {{ request()->routeIs('vehicles') ? 'active' : '' }}">
                    <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17h14M6 17V9a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8"></path><circle cx="7.5" cy="17.5" r="1.8"></circle><circle cx="16.5" cy="17.5" r="1.8"></circle><path d="M3 13l1.5-4A2 2 0 0 1 6.4 7.5"></path></svg></span>Vehicles
                </a>
            @endif
            <a href="{{ route('profile') }}" class="nav-link {{ request()->routeIs('profile') ? 'active' : '' }}">
                <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"></circle><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"></path></svg></span>My Profile
            </a>
            <span class="nav-link" style="opacity:.5;cursor:default;">
                <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4z"></path></svg></span>Helpdesk
            </span>
            </div>

            @if($canApprovals || $canEmployees || $canRecruitment || $canDiscipline)
                <button type="button" @click="toggle('myteam')" class="nav-section-label" style="display:flex;width:100%;align-items:center;justify-content:space-between;background:none;border:none;cursor:pointer;font:inherit;text-align:left;">
                    <span>My team</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :style="sections.myteam ? '' : 'transform:rotate(-90deg)'" style="transition:transform .15s ease;"><path d="m6 9 6 6 6-6"></path></svg>
                </button>
                <div x-show="sections.myteam" x-cloak>
                @if($canApprovals)
                    <a href="{{ route('approvals') }}" class="nav-link {{ request()->routeIs('approvals') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8"></path><path d="M20 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h11"></path></svg></span>
                        <span style="flex:1;">Approvals</span>
                        @if($approvalsBadge > 0)
                            <span class="pill" style="background:var(--color-primary);color:#fff;min-width:20px;justify-content:center;">{{ $approvalsBadge }}</span>
                        @endif
                    </a>
                @endif
                @if($canApproveTimesheets)
                    <a href="{{ route('timesheets.approvals') }}" class="nav-link {{ request()->routeIs('timesheets.approvals') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg></span>Timesheet Approvals
                    </a>
                @endif
                @if($canEmployees)
                    <a href="{{ route('employees') }}" class="nav-link {{ request()->routeIs('employees*') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
                        Employees
                    </a>
                @endif
                @if($canRecruitment)
                    <a href="{{ route('recruitment') }}" class="nav-link {{ request()->routeIs('recruitment') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle><path d="M16 3.5a4 4 0 0 1 0 7"></path></svg></span>
                        Recruitment
                    </a>
                @endif
                @if($canDiscipline)
                    <a href="{{ route('discipline') }}" class="nav-link {{ request()->routeIs('discipline') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"></path><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path></svg></span>
                        Discipline Cases
                    </a>
                @endif
                </div>
            @endif

            <button type="button" @click="toggle('company')" class="nav-section-label" style="display:flex;width:100%;align-items:center;justify-content:space-between;background:none;border:none;cursor:pointer;font:inherit;text-align:left;">
                <span>Company</span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :style="sections.company ? '' : 'transform:rotate(-90deg)'" style="transition:transform .15s ease;"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <div x-show="sections.company" x-cloak>
            <span class="nav-link" style="opacity:.5;cursor:default;">
                <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"></path><path d="M4 21V5"></path></svg></span>Directory
            </span>
            @if($canPolicies)
                <a href="{{ route('policies') }}" class="nav-link {{ request()->routeIs('policies') ? 'active' : '' }}">
                    <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"></path><path d="M14 3v5h5M9 13h6M9 17h6"></path></svg></span>Policies
                </a>
            @endif
            </div>

            @if($canMasterData || $canOnboardingTemplates || $canLeaveConfiguration || $canPerformanceConfiguration || $canAssetConfiguration || $canVehicleConfiguration || $canPolicyConfiguration || $canClaimsManagement || $canProjects || $canSettings || $canRoles || $canAuditLog || $canSignatures || $canHealthCheck)
                <button type="button" @click="toggle('admin')" class="nav-section-label" style="display:flex;width:100%;align-items:center;justify-content:space-between;background:none;border:none;cursor:pointer;font:inherit;text-align:left;">
                    <span>Admin</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :style="sections.admin ? '' : 'transform:rotate(-90deg)'" style="transition:transform .15s ease;"><path d="m6 9 6 6 6-6"></path></svg>
                </button>
                <div x-show="sections.admin" x-cloak>
                @if($canMasterData)
                    <a href="{{ route('admin.master-data') }}" class="nav-link {{ request()->routeIs('admin.master-data') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V8l9-5 9 5v13"></path><path d="M9 21v-6h6v6"></path></svg></span>Org &amp; Master Data
                    </a>
                @endif
                @if($canOnboardingTemplates)
                    <a href="{{ route('admin.onboarding-templates') }}" class="nav-link {{ request()->routeIs('admin.onboarding-templates') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8"></path><path d="M20 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h11"></path></svg></span>Onboarding Templates
                    </a>
                @endif
                @if($canLeaveConfiguration)
                    <a href="{{ route('admin.leave-configuration') }}" class="nav-link {{ request()->routeIs('admin.leave-configuration') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M3 10h18M8 3v4M16 3v4"></path></svg></span>Leave Configuration
                    </a>
                    <a href="{{ route('leave.reports') }}" class="nav-link {{ request()->routeIs('leave.reports') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="M18 17V9M13 17V5M8 17v-4"></path></svg></span>Leave Reports
                    </a>
                @endif
                @if($canPerformanceConfiguration)
                    <a href="{{ route('admin.performance-configuration') }}" class="nav-link {{ request()->routeIs('admin.performance-configuration') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="M18 17V9M13 17V5M8 17v-4"></path></svg></span>Performance Configuration
                    </a>
                @endif
                @if($canAssetConfiguration)
                    <a href="{{ route('admin.asset-configuration') }}" class="nav-link {{ request()->routeIs('admin.asset-configuration') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg></span>Asset Configuration
                    </a>
                @endif
                @if($canVehicleConfiguration)
                    <a href="{{ route('admin.vehicle-configuration') }}" class="nav-link {{ request()->routeIs('admin.vehicle-configuration') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17h14M6 17V9a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8"></path><circle cx="7.5" cy="17.5" r="1.8"></circle><circle cx="16.5" cy="17.5" r="1.8"></circle></svg></span>Vehicle Configuration
                    </a>
                @endif
                @if($canPolicyConfiguration)
                    <a href="{{ route('admin.policy-configuration') }}" class="nav-link {{ request()->routeIs('admin.policy-configuration') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"></path><path d="M14 3v5h5M9 13h6M9 17h6"></path></svg></span>Policy Configuration
                    </a>
                @endif
                @if($canClaimsManagement)
                    <a href="{{ route('admin.claims-management') }}" class="nav-link {{ request()->routeIs('admin.claims-management') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"></path><path d="M9 8h6M9 12h6"></path></svg></span>Claims Management
                    </a>
                @endif
                @if($canProjects)
                    <a href="{{ route('admin.projects') }}" class="nav-link {{ request()->routeIs('admin.projects') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="14" rx="2"></rect><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></span>Customers &amp; Projects
                    </a>
                    <a href="{{ route('timesheets.reports') }}" class="nav-link {{ request()->routeIs('timesheets.reports') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="M7 15l4-4 3 3 5-6"></path></svg></span>Timesheet Reports
                    </a>
                    <a href="{{ route('attendance.reports') }}" class="nav-link {{ request()->routeIs('attendance.reports') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path><path d="M12 14v3l2 1"></path></svg></span>Attendance Reports
                    </a>
                @endif
                @if($canSettings)
                    <a href="{{ route('settings') }}" class="nav-link {{ request()->routeIs('settings') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg></span>Settings
                    </a>
                @endif
                @if($canRoles)
                    <a href="{{ route('admin.roles') }}" class="nav-link {{ request()->routeIs('admin.roles*') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"></path><path d="M4.5 20.5c1-4 4-6.5 7.5-6.5s6.5 2.5 7.5 6.5"></path><path d="m17 8 1.5 1.5L21.5 6.5"></path></svg></span>Roles &amp; Permissions
                    </a>
                @endif
                @if($canAuditLog)
                    <a href="{{ route('admin.audit-log') }}" class="nav-link {{ request()->routeIs('admin.audit-log') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6M9 16h6M9 8h1"></path><path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"></path><path d="M14 3v5h5"></path></svg></span>Audit Log
                    </a>
                @endif
                @if($canSignatures)
                    <a href="{{ route('admin.signatures') }}" class="nav-link {{ request()->routeIs('admin.signatures') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 17l4-4 3 3 5-5"></path><path d="M14 9h4v4"></path><path d="M4 21h16"></path></svg></span>Signature Verification
                    </a>
                @endif
                @if($canHealthCheck && ! \App\Models\Setting::current()->health_check_hidden)
                    <a href="{{ route('admin.health-check') }}" class="nav-link {{ request()->routeIs('admin.health-check') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg></span>Health Check
                    </a>
                @endif
                </div>
            @endif
        </nav>

        <div class="sidebar-footer">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nav-link" style="width:100%;border:none;background:none;cursor:pointer;font-family:inherit;">
                    <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H4"></path></svg></span>Log out
                </button>
            </form>
        </div>
    </aside>

    <div class="app-content">
        <header class="topbar">
            <label class="topbar-search">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
                <input type="search" aria-label="Search" placeholder="Search people, requests, policies…">
            </label>
            <div class="topbar-actions">
                <livewire:notification-bell />

                <button type="button" aria-label="Toggle theme" class="icon-btn"
                    x-data="{ dark: document.documentElement.classList.contains('dark') }"
                    x-init="$watch('dark', value => { document.documentElement.classList.toggle('dark', value); localStorage.setItem('theme', value ? 'dark' : 'light'); })"
                    @click="dark = !dark">
                    <svg x-show="!dark" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path></svg>
                    <svg x-show="dark" x-cloak width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"></path></svg>
                </button>

                <div style="width:1px;height:28px;background:var(--color-border);"></div>

                <div x-data="{ open: false }" style="position:relative;" @click.outside="open = false">
                    <button type="button" @click="open = !open" style="display:flex;align-items:center;gap:10px;background:none;border:none;cursor:pointer;font-family:inherit;padding:0;">
                        <div class="avatar" style="background:#14151A;overflow:hidden;">
                            @if($me?->avatarUrl())
                                <img src="{{ $me->avatarUrl() }}" alt="{{ $me->fullName() }}" style="width:100%;height:100%;object-fit:cover;">
                            @else
                                {{ $me?->initials ?? '—' }}
                            @endif
                        </div>
                        <div style="display:flex;flex-direction:column;line-height:1.25;text-align:left;">
                            <span style="font-size:13.5px;font-weight:600;color:var(--color-text);">{{ $me?->fullName() ?? auth()->user()->name }}</span>
                            <span class="text-muted" style="font-size:12px;">{{ $me?->jobTitleName() ?? auth()->user()->role?->name }}{{ $isSupervisor ? ' · Line Manager' : '' }}</span>
                        </div>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--color-text-faint);flex-shrink:0;"><path d="m6 9 6 6 6-6"></path></svg>
                    </button>

                    <div x-show="open" x-cloak style="position:absolute;right:0;top:44px;width:220px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:12px;box-shadow:0 12px 32px rgba(0,0,0,0.14);z-index:50;overflow:hidden;">
                        <a href="{{ route('profile') }}" style="display:block;padding:10px 14px;font-size:13.5px;font-weight:600;color:var(--color-text);text-decoration:none;">My Profile</a>
                        <a href="{{ route('account.settings') }}" style="display:block;padding:10px 14px;font-size:13.5px;font-weight:600;color:var(--color-text);text-decoration:none;">Change password &amp; security</a>
                        <div style="border-top:1px solid var(--color-border);"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" style="display:block;width:100%;text-align:left;padding:10px 14px;font-size:13.5px;font-weight:600;color:var(--color-danger);background:none;border:none;cursor:pointer;font-family:inherit;">Log out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="main">
            {{ $slot }}
        </main>

        <footer class="app-footer">© {{ date('Y') }} Systems Intelligenz Ltd · HRIS</footer>
    </div>
</div>

<nav class="bottom-nav">
    <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"></path></svg>
        <span>Home</span>
    </a>
    @if($canLeave)
        <a href="{{ route('leave.apply') }}" class="nav-link {{ request()->routeIs('leave.apply') ? 'active' : '' }}">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M3 10h18M8 3v4M16 3v4"></path></svg>
            <span>Leave</span>
        </a>
    @endif
    @if($canClaims)
        <a href="{{ route('claims.create') }}" class="nav-link {{ request()->routeIs('claims.create') ? 'active' : '' }}">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"></path><path d="M9 8h6M9 12h6"></path></svg>
            <span>Claims</span>
        </a>
    @endif
    @if($canApprovals)
        <a href="{{ route('approvals') }}" class="nav-link {{ request()->routeIs('approvals') ? 'active' : '' }}">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8"></path><path d="M20 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h11"></path></svg>
            <span>Approvals</span>
        </a>
    @endif
</nav>
</div>

@livewireScripts
<x-timezone-capture />
<x-push-notifications />
</body>
</html>
