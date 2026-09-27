@props(['title' => 'Sign in', 'headline', 'subtext'])
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
<div class="auth-shell">
    <div class="auth-brand">
        <div class="auth-brand-grid"></div>
        <div class="auth-brand-blob"></div>

        <div class="auth-brand-logo">
            <div class="auth-brand-mark">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 13h3.2l2-6.5 4 13 2.4-9.5 1.8 3h6.6"></path></svg>
            </div>
            <div>
                <div class="auth-brand-word">SYSTEMS</div>
                <div class="auth-brand-word is-accent">INTELLIGENZ</div>
                <div class="auth-brand-sub">HUMAN RESOURCES INFORMATION SYSTEM</div>
            </div>
        </div>

        <div class="auth-brand-art">
            {{ $illustration ?? '' }}
        </div>

        <div class="auth-brand-headline">
            <h1>{{ $headline }}</h1>
            <p>{{ $subtext }}</p>
        </div>
    </div>

    <div class="auth-form-pane">
        <div class="auth-form">
            {{ $slot }}
            <div class="auth-footer">© {{ date('Y') }} Systems Intelligenz Ltd</div>
        </div>
    </div>
</div>
@livewireScripts
<x-timezone-capture />
</body>
</html>
