@props(['timeoutMinutes'])

{{--
    PIM/HRIS alignment §3C item 2 — warns 10 seconds before the admin-
    configured idle timeout (App\Providers\AppServiceProvider applies it to
    config('session.lifetime') server-side; this is purely the client-side
    heads-up + "Stay signed in" keep-alive). Deliberately resets only on real
    input events (mousemove/keydown/scroll/touchstart/click), not on every
    network request — the notification bell's wire:poll fires in the
    background regardless of whether anyone is actually at the keyboard, and
    counting that as activity would defeat the point of an idle timeout.

    Built with plain DOM APIs rather than an Alpine-templated element, same
    pattern as ⚡push-notifications.blade.php: Livewire's root-element check
    (config('app.debug') only) strips <script> tags before counting a page's
    direct body children, so a persistent <div> sibling here would trip
    "multiple root elements" for whichever full-page component this layout
    wraps.
--}}
@if(auth()->check())
<script>
(function () {
    var timeoutMs = {{ (int) $timeoutMinutes }} * 60 * 1000;
    var warningMs = 10 * 1000;
    var warnTimer = null;
    var countdownTimer = null;
    var secondsLeft = 10;
    var modal = null;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]').content;
    }

    function buildModal() {
        var overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;z-index:300;background:rgba(15,15,20,0.45);display:flex;align-items:center;justify-content:center;';

        var card = document.createElement('div');
        card.style.cssText = 'background:var(--color-surface);border-radius:12px;padding:26px;max-width:360px;width:calc(100vw - 40px);box-shadow:0 20px 50px rgba(0,0,0,0.28);';

        var heading = document.createElement('h2');
        heading.style.cssText = 'font-size:16px;font-weight:700;margin-bottom:8px;';
        heading.textContent = 'Still there?';

        var message = document.createElement('p');
        message.className = 'text-muted';
        message.style.cssText = 'font-size:var(--fs-sm);line-height:1.5;margin-bottom:18px;';
        message.id = 'idle-timeout-message';

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-primary';
        button.style.cssText = 'width:100%;justify-content:center;';
        button.textContent = 'Stay signed in';
        button.addEventListener('click', staySignedIn);

        card.appendChild(heading);
        card.appendChild(message);
        card.appendChild(button);
        overlay.appendChild(card);

        return overlay;
    }

    function updateMessage() {
        var el = document.getElementById('idle-timeout-message');
        if (el) {
            el.textContent = "You'll be signed out in " + secondsLeft + ' second' + (secondsLeft === 1 ? '' : 's') + ' due to inactivity.';
        }
    }

    function showWarning() {
        if (modal) return;
        secondsLeft = 10;
        modal = buildModal();
        document.body.appendChild(modal);
        updateMessage();

        countdownTimer = setInterval(function () {
            secondsLeft -= 1;
            updateMessage();
            if (secondsLeft <= 0) {
                clearInterval(countdownTimer);
                signOut();
            }
        }, 1000);
    }

    function hideWarning() {
        clearInterval(countdownTimer);
        if (modal) {
            modal.remove();
            modal = null;
        }
    }

    function scheduleWarning() {
        clearTimeout(warnTimer);
        var delay = timeoutMs - warningMs;
        warnTimer = setTimeout(showWarning, delay > 0 ? delay : 0);
    }

    function registerActivity() {
        // Once the warning is showing, only "Stay signed in" should clear
        // it — otherwise a stray background event could dismiss the
        // warning on a tab that's genuinely unattended.
        if (modal) return;
        scheduleWarning();
    }

    function staySignedIn() {
        hideWarning();

        fetch('{{ route('session.keep-alive') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
        }).catch(function () {});

        scheduleWarning();
    }

    function signOut() {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route('logout') }}';

        var csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = csrfToken();

        form.appendChild(csrf);
        document.body.appendChild(form);
        form.submit();
    }

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function (evt) {
        window.addEventListener(evt, registerActivity, { passive: true });
    });

    scheduleWarning();
})();
</script>
@endif
