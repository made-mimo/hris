<?php

namespace App\Services;

use App\Models\Renewable;
use App\Models\RenewalType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Spec Section 3.2's shared platform service: "this thing has an expiry
 * date; remind the right people at configurable intervals beforehand; keep
 * reminding if it lapses." A consuming module (none exist in this prototype
 * yet — E2/E3/E5 are all Phase 4) only needs to call register()/renew()/
 * retire(); sweep() is what the scheduled job (App\Console\Commands\SweepRenewals)
 * runs daily to actually fire reminders through App\Services\NotificationService.
 */
class RenewalReminderEngine
{
    public function __construct(private NotificationService $notifications) {}

    public function register(Model $renewable, string $typeKey, Carbon $expiryDate): Renewable
    {
        $type = RenewalType::where('key', $typeKey)->firstOrFail();

        return Renewable::create([
            'renewable_type' => get_class($renewable),
            'renewable_id' => $renewable->getKey(),
            'renewal_type_id' => $type->id,
            'expiry_date' => $expiryDate,
            'status' => 'active',
            'fired_tier_ids' => [],
        ]);
    }

    /** A fresh cycle — reminders start counting down again from the new expiry date. */
    public function renew(Renewable $renewable, Carbon $newExpiryDate): void
    {
        $renewable->update([
            'expiry_date' => $newExpiryDate,
            'status' => 'active',
            'fired_tier_ids' => [],
            'last_expired_alert_at' => null,
        ]);
    }

    /** Stops all future reminders — the item is gone/decommissioned, not merely renewed. */
    public function retire(Renewable $renewable): void
    {
        $renewable->update(['status' => 'retired']);
    }

    /**
     * The daily sweep: fires any reminder tier whose threshold has now
     * passed (once per tier per cycle — tracked in `fired_tier_ids`), and
     * once a renewable is actually expired, raises a persistent daily alert
     * rather than going silent after the last configured tier — spec's
     * explicit departure from "a one-shot reminder."
     */
    public function sweep(): void
    {
        $today = Carbon::today();

        Renewable::whereIn('status', ['active', 'expired'])->with(['renewalType.tiers', 'renewalType.notifyTargets.role', 'renewalType.notifyTargets.user'])
            ->chunk(100, function ($renewables) use ($today) {
                foreach ($renewables as $renewable) {
                    $this->sweepOne($renewable, $today);
                }
            });
    }

    private function sweepOne(Renewable $renewable, Carbon $today): void
    {
        $fired = collect($renewable->fired_tier_ids ?? []);

        foreach ($renewable->renewalType->tiers as $tier) {
            if ($fired->contains($tier->id)) {
                continue;
            }

            $fireOn = $renewable->expiry_date->copy()->subDays($tier->days_before_expiry);

            if ($today->gte($fireOn)) {
                $this->notify($renewable, "Renewal due in {$tier->days_before_expiry} day(s)");
                $fired->push($tier->id);
            }
        }

        $renewable->fired_tier_ids = $fired->values()->all();

        if ($renewable->isExpired()) {
            $renewable->status = 'expired';

            if (! $renewable->last_expired_alert_at || $renewable->last_expired_alert_at->lt(now()->subDay())) {
                $this->notify($renewable, 'Expired and not yet renewed');
                $renewable->last_expired_alert_at = now();
            }
        }

        $renewable->save();
    }

    private function notify(Renewable $renewable, string $reason): void
    {
        $label = $renewable->renewalType->label;

        foreach ($this->recipientsFor($renewable) as $recipient) {
            $this->notifications->notify(
                $recipient,
                'renewal.reminder',
                "{$label} renewal reminder",
                "{$reason} for {$label} (expires {$renewable->expiry_date->format('j M Y')}).",
                null,
                'Renewal & Compliance'
            );
        }
    }

    /** @return Collection<int, User> */
    private function recipientsFor(Renewable $renewable): Collection
    {
        $recipients = collect();

        foreach ($renewable->renewalType->notifyTargets as $target) {
            if ($target->user) {
                $recipients->push($target->user);
            }
            if ($target->role) {
                $recipients = $recipients->merge($target->role->users);
            }
        }

        return $recipients->unique('id');
    }
}
