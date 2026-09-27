<?php

use App\Models\ExpenseClaim;
use Livewire\Component;

new class extends Component
{
    public function with(): array
    {
        return [
            'nextReference' => 'CLM-'.now()->format('Ymd').'-'.str_pad((string) (ExpenseClaim::max('id') + 1), 3, '0', STR_PAD_LEFT),
        ];
    }
};
?>

<x-layouts.app title="New expense claim">
    <div class="page-header">
        <div>
            <div class="text-muted" style="font-size:13px;margin-bottom:6px;">
                <a href="{{ route('home') }}" wire:navigate class="text-muted">Home</a> <span>/</span> My Claims <span>/</span> <span style="color:var(--color-text);font-weight:600;">New claim</span>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <h1>New expense claim</h1>
                <span class="pill pill-neutral">Draft</span>
                <span class="font-mono text-muted" style="font-size:13px;">{{ $nextReference }}</span>
            </div>
        </div>
    </div>

    <livewire:expense-claim-form />
</x-layouts.app>
