<?php

use App\Models\Setting;
use App\Services\HealthCheckService;
use Livewire\Component;

/** Spec A5: "the underlying check endpoint should be able to be hidden once initial setup is complete to reduce information disclosure" — hidden means hidden from everyone, Admin included, until re-enabled in Settings; that's the whole point of the toggle. */
new class extends Component
{
    public function mount(): void
    {
        abort_if(Setting::current()->health_check_hidden, 404);
    }

    public function with(): array
    {
        return ['results' => app(HealthCheckService::class)->run()];
    }
};
?>

<x-layouts.app title="System Health Check">
    <div class="page-header">
        <div>
            <h1>System Health Check</h1>
            <p class="text-muted">Spec Section A5 — runtime dependencies, permissions, and connectivity. Can be hidden once initial setup is complete (see Settings).</p>
        </div>
    </div>

    <section class="card" style="padding:0;overflow:hidden;">
        @foreach($results as $result)
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--color-border);">
                <span style="font-size:var(--fs-sm);font-weight:600;">{{ $result['label'] }}</span>
                <span class="pill {{ $result['ok'] ? 'pill-success' : 'pill-danger' }}">{{ $result['ok'] ? 'OK' : $result['detail'] }}</span>
            </div>
        @endforeach
    </section>
</x-layouts.app>
