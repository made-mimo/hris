@props(['title' => 'Careers'])
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
<body style="background:var(--color-bg);">
<div style="max-width:900px;margin:0 auto;padding:32px 20px;">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:28px;">
        <div class="brand-mark" style="width:34px;height:34px;"></div>
        <div>
            <div class="font-display" style="font-size:14px;font-weight:800;">SYSTEMS <span style="color:var(--color-primary);">INTELLIGENZ</span></div>
            <div class="text-faint" style="font-size:10px;font-weight:700;letter-spacing:1.2px;">CAREERS</div>
        </div>
    </div>

    {{ $slot }}

    <div class="text-faint" style="margin-top:40px;text-align:center;font-size:12px;">© {{ date('Y') }} Systems Intelligenz Ltd</div>
</div>
@livewireScripts
</body>
</html>
