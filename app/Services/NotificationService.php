<?php

namespace App\Services;

use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Spec Section A7/3.2's shared Notification & Messaging Framework: "a module
 * only needs to raise one notification event to reach all [three channels],
 * no per-module push logic required." Every module calls notify() and gets
 * an in-app record (always), an email (this dev environment logs it, see
 * TwoFactorCodeMail for the same pattern), and a real VAPID-signed Web Push
 * to every browser the user has granted permission on — no per-category
 * opt-out preferences yet (spec calls this "minimal for v1"; still true
 * here, tracked in PLAN.md).
 *
 * Real-time in-app delivery is Livewire polling (`wire:poll` on the bell),
 * not a WebSocket push over Laravel Reverb — this dev box has no Reverb
 * server running, so polling is the honest stand-in, same reasoning as
 * MAIL_MAILER=log standing in for a real transactional-email provider.
 */
class NotificationService
{
    public function notify(
        User $user,
        string $type,
        string $title,
        string $body,
        ?string $deepLink,
        string $sourceModule,
    ): Notification {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'deep_link' => $deepLink,
            'source_module' => $sourceModule,
        ]);

        // Spec Section 3.2: "a failure in any one channel... must never
        // block the underlying business action or the other channels;
        // failures are logged and retried/skipped, not thrown as errors."
        // The in-app record above is the one channel that's never allowed
        // to fail silently — it has no external dependency to fail on.
        try {
            Mail::to($user->email)->send(new NotificationMail($title, $body, $deepLink));
        } catch (Throwable $e) {
            Log::warning('Notification email failed to send.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        try {
            $this->sendPush($user, $title, $body, $deepLink);
        } catch (Throwable $e) {
            Log::warning('Notification push failed to send.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        return $notification;
    }

    private function sendPush(User $user, string $title, string $body, ?string $deepLink): void
    {
        $subscriptions = $user->pushSubscriptions;

        if ($subscriptions->isEmpty() || ! config('services.vapid.public_key')) {
            return;
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => config('services.vapid.subject'),
            'publicKey' => config('services.vapid.public_key'),
            'privateKey' => config('services.vapid.private_key'),
        ]]);

        $payload = json_encode(['title' => $title, 'body' => $body, 'deepLink' => $deepLink]);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                new Subscription($subscription->endpoint, $subscription->public_key, $subscription->auth_token, $subscription->content_encoding),
                $payload
            );
        }

        foreach ($webPush->flush() as $report) {
            if (! $report->isSuccess() && $report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();
            }
        }
    }
}
