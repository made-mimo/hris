@props(['title' => 'Home'])
@php
    $me = auth()->user()?->employee;
    $isHr = auth()->user()?->isHr();
    $isSupervisor = $me && $me->subordinates()->exists();
    $permissions = app(\App\Services\PermissionService::class);
    $canApprovals = auth()->user()?->canView('approvals');
    $canEmployees = auth()->user()?->canView('employees');
    $canLeave = auth()->user()?->canView('leave.apply');
    $canClaims = auth()->user()?->canView('claims.create');
    $canMasterData = auth()->user()?->canView('admin.master-data');
    $canOnboardingTemplates = auth()->user()?->canView('admin.onboarding-templates');
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

        <nav class="sidebar-nav" aria-label="Main">
            <div class="nav-section-label">Workspace</div>
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
            @endif
            <span class="nav-link" style="opacity:.5;cursor:default;">
                <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg></span>Time &amp; Attendance
            </span>
            <a href="{{ route('profile') }}" class="nav-link {{ request()->routeIs('profile') ? 'active' : '' }}">
                <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"></circle><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"></path></svg></span>My Profile
            </a>
            <span class="nav-link" style="opacity:.5;cursor:default;">
                <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4z"></path></svg></span>Helpdesk
            </span>

            @if($canApprovals || $canEmployees)
                <div class="nav-section-label">My team</div>
                @if($canApprovals)
                    <a href="{{ route('approvals') }}" class="nav-link {{ request()->routeIs('approvals') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8"></path><path d="M20 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h11"></path></svg></span>
                        <span style="flex:1;">Approvals</span>
                        @if($approvalsBadge > 0)
                            <span class="pill" style="background:var(--color-primary);color:#fff;min-width:20px;justify-content:center;">{{ $approvalsBadge }}</span>
                        @endif
                    </a>
                @endif
                @if($canEmployees)
                    <a href="{{ route('employees') }}" class="nav-link {{ request()->routeIs('employees*') ? 'active' : '' }}">
                        <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
                        Employees
                    </a>
                @endif
            @endif

            <div class="nav-section-label">Company</div>
            <span class="nav-link" style="opacity:.5;cursor:default;">
                <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"></path><path d="M4 21V5"></path></svg></span>Directory
            </span>
            <span class="nav-link" style="opacity:.5;cursor:default;">
                <span class="nav-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"></path><path d="M14 3v5h5M9 13h6M9 17h6"></path></svg></span>Policies
            </span>

            @if($canMasterData || $canOnboardingTemplates || $canSettings || $canRoles || $canAuditLog || $canSignatures || $canHealthCheck)
                <div class="nav-section-label">Admin</div>
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
                <div style="width:1px;height:28px;background:var(--color-border);"></div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="avatar" style="background:#14151A;overflow:hidden;">
                        @if($me?->avatarUrl())
                            <img src="{{ $me->avatarUrl() }}" alt="{{ $me->fullName() }}" style="width:100%;height:100%;object-fit:cover;">
                        @else
                            {{ $me?->initials ?? '—' }}
                        @endif
                    </div>
                    <div style="display:flex;flex-direction:column;line-height:1.25;">
                        <span style="font-size:13.5px;font-weight:600;">{{ $me?->fullName() ?? auth()->user()->name }}</span>
                        <span class="text-muted" style="font-size:12px;">{{ $me?->jobTitleName() ?? auth()->user()->role?->name }}{{ $isSupervisor ? ' · Line Manager' : '' }}</span>
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
