<?php

use Livewire\Component;

/**
 * Spec Section A7's notification bell + panel. Real-time delivery here is
 * `wire:poll` rather than a WebSocket push over Laravel Reverb — this dev
 * box has no Reverb server running, so short polling is the honest stand-in
 * (same reasoning as MAIL_MAILER=log standing in for a real mail provider).
 */
new class extends Component
{
    public function markAsRead(int $id): void
    {
        auth()->user()->notifications()->where('id', $id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function markAllAsRead(): void
    {
        auth()->user()->notifications()->whereNull('read_at')->whereNull('cleared_at')->update(['read_at' => now()]);
    }

    public function clear(int $id): void
    {
        auth()->user()->notifications()->where('id', $id)->update(['cleared_at' => now()]);
    }

    public function clearAll(): void
    {
        auth()->user()->notifications()->whereNull('cleared_at')->update(['cleared_at' => now()]);
    }

    public function with(): array
    {
        $base = auth()->user()->notifications()->whereNull('cleared_at');

        return [
            'unreadCount' => (clone $base)->whereNull('read_at')->count(),
            'items' => (clone $base)->limit(15)->get(),
        ];
    }
};
?>

<div x-data="{ open: false }" style="position:relative;" @click.outside="open = false">
    <button type="button" aria-label="{{ $unreadCount > 0 ? "Notifications ({$unreadCount} unread)" : 'Notifications' }}" class="icon-btn" @click="open = !open" wire:poll.15s>
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4z"></path><path d="M10 21h4"></path></svg>
        @if($unreadCount > 0)
            <span class="count-badge">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak class="notif-panel">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid var(--color-border);">
            <span style="font-size:var(--fs-sm);font-weight:700;">Notifications{{ $unreadCount > 0 ? " · {$unreadCount} unread" : '' }}</span>
            <div style="display:flex;gap:10px;">
                @if($unreadCount > 0)
                    <button type="button" wire:click="markAllAsRead" class="hint" style="background:none;border:none;padding:0;cursor:pointer;font-weight:600;">Mark all read</button>
                @endif
                @if($items->isNotEmpty())
                    <button type="button" wire:click="clearAll" class="hint" style="background:none;border:none;padding:0;cursor:pointer;font-weight:600;">Clear all</button>
                @endif
            </div>
        </div>

        <button type="button" onclick="window.__enablePush && window.__enablePush()" class="hint" style="display:block;width:100%;text-align:left;background:var(--color-bg);border:none;padding:9px 14px;cursor:pointer;">
            Enable browser push notifications
        </button>

        @forelse($items as $item)
            <div class="notif-item{{ $item->isRead() ? '' : ' unread' }}" style="display:flex;gap:8px;padding:11px 14px;border-bottom:1px solid var(--color-border);{{ $item->isRead() ? '' : 'background:var(--color-primary-light);' }}">
                <a href="{{ $item->deep_link ?? '#' }}" wire:navigate style="flex:1;text-decoration:none;color:inherit;" wire:click="markAsRead({{ $item->id }})">
                    <div class="notif-title" style="font-size:var(--fs-sm);font-weight:600;">{{ $item->title }}</div>
                    <div style="font-size:var(--fs-xs);color:var(--color-text-muted);margin-top:2px;">{{ $item->body }}</div>
                    <div class="hint" style="margin-top:4px;" title="{{ $item->created_at->format(\App\Support\Dates::DATE_TIME) }}">{{ $item->created_at->diffForHumans() }}</div>
                </a>
                <button type="button" wire:click="clear({{ $item->id }})" aria-label="Clear" style="background:none;border:none;cursor:pointer;color:var(--color-text-faint);align-self:flex-start;">&times;</button>
            </div>
        @empty
            <div class="hint" style="padding:20px 14px;text-align:center;">No notifications.</div>
        @endforelse
    </div>
</div>
