{{--
    Client-declared timezone capture (spec Section C3): reads the browser's own
    IANA timezone via the standard Intl API and reports it to the server so
    timestamps render in the viewer's local time. Deliberately NOT IP-address
    or GPS geolocation — no location permission prompt, no coordinates, just a
    value the browser already exposes about itself. See PLAN.md and
    App\Http\Controllers\TimezoneController.
--}}
<script>
(function () {
    try {
        var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        var known = {{ \Illuminate\Support\Js::from(auth()->check() ? auth()->user()->timezone : session('client_timezone')) }};
        if (tz && tz !== known) {
            fetch('{{ route('timezone.set') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ timezone: tz }),
                keepalive: true,
            });
        }
    } catch (e) {
        // Intl unsupported or blocked — timestamps simply fall back to the
        // application default (GMT+1) for this viewer.
    }
})();
</script>
