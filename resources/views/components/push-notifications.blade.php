{{--
    Spec Section A7's Web Push channel: registers the (offline-cache-free)
    service worker and exposes window.__enablePush()/__pushEnabled for the
    notification bell to call — kept as a plain inline script, same pattern
    as x-timezone-capture, since neither needs a Vite build step.
--}}
@if(auth()->check())
<script>
(function () {
    var vapidPublicKey = {{ \Illuminate\Support\Js::from(config('services.vapid.public_key')) }};

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]').content;
    }

    window.__pushSupported = function () {
        return 'serviceWorker' in navigator && 'PushManager' in window && !!vapidPublicKey;
    };

    window.__pushEnabled = async function () {
        if (!window.__pushSupported()) return false;
        var reg = await navigator.serviceWorker.getRegistration('/sw.js');
        if (!reg) return false;
        var sub = await reg.pushManager.getSubscription();
        return !!sub;
    };

    window.__enablePush = async function () {
        if (!window.__pushSupported()) return false;

        var permission = await Notification.requestPermission();
        if (permission !== 'granted') return false;

        var reg = await navigator.serviceWorker.register('/sw.js');
        var sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
        });

        await fetch('{{ route('push.subscribe') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify(sub.toJSON()),
        });

        return true;
    };

    window.__disablePush = async function () {
        if (!('serviceWorker' in navigator)) return;
        var reg = await navigator.serviceWorker.getRegistration('/sw.js');
        if (!reg) return;
        var sub = await reg.pushManager.getSubscription();
        if (!sub) return;

        await fetch('{{ route('push.unsubscribe') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ endpoint: sub.endpoint }),
        });

        await sub.unsubscribe();
    };

    if (window.__pushSupported()) {
        navigator.serviceWorker.register('/sw.js').catch(function () {});
    }
})();
</script>
@endif
